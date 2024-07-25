<?php
class AdminGateway
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
         * Get all the admin
         */
        $sql = "SELECT * 
        FROM control_tbl
            WHERE admin_id = :id            
                    ORDER BY control_tbl.id DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":id", $id);
        $stmt->execute();

        $data = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[] = $row;
        }

        return $data;
    }

    public function getAll()
    {
        $sql = "SELECT * 
        FROM control_tbl            
                    ORDER BY control_tbl.id DESC";

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
        $adminID = substr(str_shuffle(MD5(microtime())), 0, 9);

        /**
         * Check if admin already exist
         */
        $stmt = $this->conn->prepare("SELECT COUNT(*) 
                    FROM control_tbl 
                        WHERE admin_email = :email 
                            OR admin_id = :id");

        $stmt->bindValue(":email", $data['email_address']);
        $stmt->bindValue(":id", $adminID);
        $stmt->execute();

        /**
         * Save Activity
         */
        $this->utility->logActivity([
            'userid' => $adminID,
            'types' => 'Register',
            'messages' => 'New account registration',
        ]);

        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode([
                'message' => 'Admin already exists.',
                'status' => 'error'
            ]);

            exit;
        }

        $sql = "INSERT INTO control_tbl(admin_id, fullname, username, admin_email, admin_password, op_status, access_level, save_date, time_stamp)
        VALUES(:admin_id, :fullname, :username, :admin_email, :admin_password, :op_status, :access_level, :save_date, :time_stamp)";

        $hashedPassword = password_hash($data['admin_password'], PASSWORD_DEFAULT);

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":admin_id", $adminID);
        $stmt->bindValue(":fullname", $data['fullname']);
        $stmt->bindValue(":username", $data['username']);
        $stmt->bindValue(":admin_email", $data['admin_email']);
        $stmt->bindValue(":admin_password", $hashedPassword);
        $stmt->bindValue(":op_status", 'active');
        $stmt->bindValue(":access_level", $data['access_level']);
        $stmt->bindValue(":save_date", date('d-m-Y', time()));
        $stmt->bindValue(":", time());

        if ($stmt->execute()) {
            http_response_code(201);

            echo json_encode([
                "message" => "New admin registered successfully",
                "adminId" => $adminID,
                'status' => 'success',
            ]);
            exit;
        }
        http_response_code(500);

        echo json_encode([
            'message' => 'Could not create admin account.',
            'status' => 'error'
        ]);
    }

    public function update(array $prev, array $data)
    {
        /**
         * Updating the admin data.
         */
        $sql = "UPDATE control_tbl SET
                fullname = :fullname,
                username = :username,
                admin_password = :admin_password,
                op_status = :op_status,
                access_level = :access_level
                WHERE admin_id = :id";

        $stmt = $this->conn->prepare($sql);

        if ($data['admin_password']) {
            $hashedPassword = password_hash($data['admin_password'], PASSWORD_DEFAULT);
        }

        $newData = $data[0];
        $stmt->bindValue(":fullname", $newData['fullname'] ?? $prev['fullname']);
        $stmt->bindValue(":", $newData[''] ?? $prev['']);
        $stmt->bindValue(":username", $newData['username'] ?? $prev['username']);
        $stmt->bindValue(":admin_password", $hashedPassword ?? $prev['admin_password']);
        $stmt->bindValue(":op_status", $newData['op_status'] ?? $prev['op_status']);
        $stmt->bindValue(":access_level", $newData['access_level'] ?? $prev['access_level']);
        $stmt->bindValue(":id", $newData['id'] ?? $prev['id']);

        $row = $stmt->execute();
        $adminId = $newData['admin_id'];

        /**
         * Save Activity
         */
        $this->utility->logActivity([
            'userid' => $adminId,
            'types' => 'Update',
            'messages' => 'Admin account update',
        ]);

        echo json_encode([
            "message" => "Admin $adminId updated",
            "status" => 'success',
            "rows" => $row,

        ]);
    }

    public function delete(string $id)
    {
        $stmt = $this->conn->prepare("DELETE FROM control_tbl WHERE admin_id = :id");

        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $row = $stmt->rowCount();
        /**
         * Save Activity
         */
        $this->utility->logActivity([
            'userid' => $id,
            'types' => 'Delete',
            'messages' => 'Admin account delete',
        ]);

        echo json_encode([
            "message" => "Admin: $id deleted",
            "status" => 'success',
            "rows" => $row
        ]);
    }
}
