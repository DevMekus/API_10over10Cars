<?php
class AccountGateway
{
    private PDO $conn;
    private $utility;

    public function __construct(Database $database)
    {
        $this->conn = $database->getConnection();
        $this->utility =  new Utility($database);
    }

    public function get(string $id)
    {
        /**
         * Fetch the account associated to this userid
         */
        $sql = "SELECT * 
        FROM user_profile_tbl user
            INNER JOIN accounts_tbl account
                ON user.userid = account.userid
                   WHERE account.userid = :userid";


        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':userid', $id);
        $stmt->execute();

        $data = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[] = $row;
        }

        return $data;
    }

    public function getAll()
    {
        /**
         * Fetch all the accounts in the database
         */
        $sql = "SELECT * 
        FROM user_profile_tbl user
            INNER JOIN accounts_tbl account
                ON user.userid = account.userid
                    ORDER BY account.id DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $data = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[] = $row;
        }

        return $data;
    }

    public function create(array $data)
    {
        /**
         * Create handles Login, NewAccount, Reset, ConfirmReset
         */
        switch ($data['action']) {
            case "LOGIN":
                $this->login($data);
                break;
            case "NEW":
                $this->new($data);
                break;
            case "RESET":
                $this->reset($data);
                break;
            case "TOKEN":
                $this->verifytoken($data);
                break;
            case "CONFIRMRESET":
                $this->creset($data);
                break;
            default:
                http_response_code(400);
                echo json_encode(
                    [
                        'message' => 'Request not understood',
                        'status' => 'error'
                    ]
                );
        }
    }

    private function login(array $data)
    {
        /**
         * Login a user or an admin.
         * Check if the user exist, if not, then admin.
         */
        if (!$this->checkUser($data)) {
            if (!$this->checkAdmin($data)) {
                http_response_code(401);

                echo json_encode(
                    [
                        'message' => 'Invalid email address or password.',
                        'status' => 'error'
                    ]
                );
            }
        }
    }

    private function checkUser(array $data)
    {
        /**
         * Checks if the login is a user, then login.
         */
        $stmt = $this->conn->prepare("SELECT * 
        FROM user_profile_tbl 
            WHERE email_address = :email");

        $stmt->bindValue(':email', $data['email_address']);

        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($data['user_password'], $user['user_password'])) {
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $user['userid'],
                'types' => 'Login',
                'messages' => 'Login to account successful',
            ]);
            http_response_code(200);
            echo json_encode(
                [
                    'message' => 'Login successful.',
                    'userid' => $user['userid'],
                    'status' => 'success',
                    'role' => 'user'
                ]
            );
            exit;
        }

        return false;
    }

    private function checkAdmin(array $data)
    {
        /**
         * Checks if the login is an admin, then login.
         */
        $stmt = $this->conn->prepare("SELECT * 
        FROM control_tbl 
            WHERE admin_email = :email");

        $stmt->bindValue(':email', $data['email_address']);

        $stmt->execute();

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);


        if ($admin && password_verify($data['user_password'], $admin['admin_password'])) {
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $admin['admin_id'],
                'types' => 'Login',
                'messages' => 'Login to account successful',
            ]);

            http_response_code(200);
            echo json_encode(
                [
                    'message' => 'Login successful.',
                    'admin' => $admin['admin_id'],
                    'status' => 'success',
                    'role' => 'admin'
                ]
            );
            exit;
        }

        return false;
    }

    private function new(array $data)
    {
        $userid = substr(str_shuffle(MD5(microtime())), 0, 9);

        /**
         * Checking if this user already exists
         */
        $stmt = $this->conn->prepare("SELECT COUNT(*) 
        FROM user_profile_tbl 
            WHERE email_address = :email 
                OR userid = :userid");

        $stmt->bindValue(":email", $data['email_address']);
        $stmt->bindValue(":userid", $userid);
        $stmt->execute();

        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode([
                'message' => 'User already exists.',
                'status' => 'error'
            ]);
            exit;
        }

        if ($this->createAccount($data, $userid) && $this->userProfile($data, $userid)) {
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $userid,
                'types' => 'Register',
                'messages' => 'Registration successful',
            ]);

            http_response_code(201);
            echo json_encode([
                "message" => "User registered successfully",
                "userid" => $userid,
                'status' => 'success',
                'account' => 'user'
            ]);
            exit;
        }
        http_response_code(500);
        echo json_encode([
            'message' => 'Account registration failed.',
            'status' => 'error'
        ]);
    }

    private function userProfile(array $data, string $userid)
    {
        /**
         * Creates a user profile
         */
        $hashedPassword = password_hash($data['user_password'], PASSWORD_DEFAULT);

        /**Create User */
        $sql = "INSERT INTO user_profile_tbl(userid, fullname, home_address, home_state, home_city, country, email_address, phone_number, user_password)
         VALUES(:userid, :fullname, :homeAddress, :homeState, :homeCity, :country, :emailAddress, :phone, :passwords)";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(':userid', $userid);
        $stmt->bindValue(':fullname', $data['fullname']);
        $stmt->bindValue(':homeAddress', "");
        $stmt->bindValue(':homeState', "");
        $stmt->bindValue(':homeCity', "");
        $stmt->bindValue(':country', "");
        $stmt->bindValue(':phone', "");
        $stmt->bindValue(':emailAddress', $data['email_address']);
        $stmt->bindValue(':passwords', $hashedPassword);
    }

    private function createAccount(array $data, string $userid)
    {
        /**
         * Create a new user account
         */
        $tStamp = time();
        $createDate = date('d-m-Y', $tStamp);
        $reset_token_expiration = "";
        $reset_token = "";
        $status = "pending";

        $sql = "INSERT INTO accounts_tbl(userid, account_status, create_date, time_stamp, reset_token, reset_token_expiration)VALUES(:userid, :accountStatus, :createDate, :tStamp, :reset_token, :reset_token_expiration)";

        $stmt =  $this->conn->prepare($sql);

        $stmt->bindValue(":userid", $userid);
        $stmt->bindValue(":accountStatus", $status);
        $stmt->bindValue(":createDate", $createDate);
        $stmt->bindValue(":tStamp", $tStamp);
        $stmt->bindValue(':reset_token', $reset_token);
        $stmt->bindValue(':reset_token_expiration', $reset_token_expiration);

        return $stmt->execute();
    }

    private function reset(array $data)
    {
        $stmt = $this->conn->prepare("SELECT * FROM user_profile_tbl WHERE email_address = :email");

        $stmt->bindParam(':email', $data['email']);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $resetToken = bin2hex(random_bytes(16));
            $resetTokenExpiration = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $this->conn->prepare("UPDATE account_tbl SET reset_token = :reset_token, reset_token_expiration = :reset_token_expiration WHERE userid = :userid ");

            $stmt->bindValue(':reset_token', $resetToken);
            $stmt->bindValue(':reset_token_expiration', $resetTokenExpiration);
            $stmt->bindValue(':userid', $user['userid']);

            $stmt->execute();

            /**Send an Email to the User */

            $resetLink = "http://localhost:3000/auth/reset?token=$resetToken";

            mail($data['email'], 'Password Reset', "Click here to reset your password: $resetLink");

            http_response_code(200);
            echo json_encode(
                [
                    'message' => 'Password reset link has been sent to your email.',
                    'status' => 'success'
                ]
            );
        } else {
            http_response_code(404);
            echo json_encode(
                [
                    'message' => 'Email not found',
                    'status' => 'error'
                ]
            );
        }
    }

    private function verifytoken(array $data)
    {
        /**
         * Verify the reset account token
         */
        $stmt = $this->conn->prepare("SELECT * FROM account_tbl WHERE reset_token  = :reset_token AND reset_token_expiration > NOW()");

        $stmt->bindValue(':reset_token', $data['token']);
        $stmt->execute();

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(200);
            echo json_encode([
                'message' => 'Change a new password.',
                'status' => 'success'
            ]);
        } else {
            http_response_code(400);
            echo json_encode(
                [
                    'message' => 'Invalid or expired token.',
                    'status' => 'error'
                ]
            );
        }
    }
    private function creset(array $data)
    {
        /**
         * Confirm Reset and reset the account password
         */
        $stmt = $this->conn->prepare("SELECT * FROM account_tbl WHERE reset_token = :reset_token AND reset_token_expiration > NOW()");
        $stmt->bindValue(':reset_token', $data['token']);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

            $stmt = $this->conn->prepare("UPDATE user_profile_tbl SET user_password = :passwords WHERE userid = :userid");
            $stmt->bindValue(':userid', $user['userid']);
            $stmt->bindValue(':passwords', $hashedPassword);

            if ($stmt->execute()) {
                /**Update account table */
                $stmt = $this->conn->prepare("UPDATE account_tbl SET reset_token = NULL, reset_expiration_token = NULL WHERE reset_token = :token");

                $stmt->bindValue(':token', $data['token']);
                $stmt->execute();
                /**
                 * Save Activity
                 */
                $this->utility->logActivity([
                    'userid' => $user['userid'],
                    'types' => 'Reset',
                    'messages' => 'Account reset successful',
                ]);

                http_response_code(200);

                echo json_encode(
                    [
                        'message' => 'Password has been reset successfully.',
                        'status' => 'success'
                    ]
                );
            }
        } else {
            http_response_code(400);
            echo json_encode(
                [
                    'message' => 'Invalid or expired token.',
                    'status' => 'error'
                ]
            );
        }
    }

    public function update(array $prev, array $data)
    {
        /**
         * Update the user information
         */
        $sql = "UPDATE user_profile_tbl 
                    SET fullname = :fullname, 
                        home_address = :home_address,
                        home_state = :home_state,
                        home_city = :home_city,
                        country = :country,
                        phone_number = :phone_number                       
                    WHERE userid = :userid";

        $stmt = $this->conn->prepare($sql);
        $newData = $data[0];
        $stmt->bindValue(":fullname", $newData['fullname'] ?? $prev['fullname']);
        $stmt->bindValue(":home_address", $newData['home_address'] ?? $prev['home_address']);
        $stmt->bindValue(":home_state", $newData['home_state'] ?? $prev['home_state']);
        $stmt->bindValue(":home_city", $newData['home_city'] ?? $prev['home_city']);
        $stmt->bindValue(":country", $newData['country'] ?? $prev['country']);
        $stmt->bindValue(":phone_number", $newData['phone_number'] ?? $prev['phone_number']);
        $stmt->bindValue(":userid", $newData['userid'] ?? $prev['userid']);

        $stmt->execute();

        /**Updating Accounts table */
        $sql = "UPDATE accounts_tbl
                    SET account_status = :account_status
                    WHERE userid = :userid";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(":account_status", $newData['account_status'] ?? $prev['account_status']);
        $stmt->bindValue(":userid", $newData['userid'] ?? $prev['userid']);

        $row = $stmt->execute();
        $userid = $data['userid'];

        /**
         * Save Activity
         */
        $this->utility->logActivity([
            'userid' => $userid,
            'types' => 'Update',
            'messages' => 'Account update successful',
        ]);

        echo json_encode([
            "message" => "Account $userid updated",
            "status" => 'success',
            "rows" => $row,

        ]);
    }

    public function delete(string $id)
    {
        /**
         * Delete the user using the Id
         */
        $stmt = $this->conn->prepare("DELETE FROM user_profile_tbl WHERE userid = :userid");

        $stmt->bindValue(':userid', $id);

        if ($stmt->execute()) {
            $stmt = $this->conn->prepare("DELETE FROM accounts_tbl WHERE userid = :userid");

            $stmt->bindValue(':userid', $id);

            $stmt->execute();
            $row = $stmt->rowCount();
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $id,
                'types' => 'Delete',
                'messages' => 'Account delete successful',
            ]);
            echo json_encode([
                "message" => "Account $id deleted",
                "status" => 'success',
                "rows" => $row
            ]);
            exit;
        }

        echo json_encode([
            "message" => "Account $id delete failed",
            "status" => 'failed',
        ]);
    }
}
