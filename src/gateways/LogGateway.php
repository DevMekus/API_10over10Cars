<?php
class LogGateway
{
    private PDO $conn;
    private $utility;
    private $table = 'log_tbl';

    public function __construct(Database $database)
    {
        $this->conn = $database->getConnection();
        $this->utility =  new Utility($database);
    }

    public function get(string $id)
    {
        /**
         * Fetch the log associated to this $id(userid)
         */
        $stmt = $this->conn->prepare(
            "SELECT * 
                    FROM log_tbl
                        WHERE userid = :id
                        ORDER BY id DESC"
        );

        $stmt->bindValue(':id', $id);
        $stmt->execute();

        $data = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[] = $row;
        }

        return $data;
    }

    public function lastSeen(string $id)
    {
        $stmt = $this->conn->prepare(
            "SELECT time_stamp 
                    FROM log_tbl
                        WHERE userid = :id
                        ORDER BY time_stamp DESC LIMIT 1 OFFSET 1"
        );

        $stmt->bindValue(':id', $id);
        $stmt->execute();

        $secondLastLogin = $stmt->fetchColumn();

        if ($secondLastLogin) {
            return Date('F j, Y', $secondLastLogin);
        }
    }

    public function getAll()
    {
        /**
         * Fetch all the theft report
         */
        $stmt = $this->conn->prepare(
            "SELECT * 
                    FROM log_tbl
                    ORDER BY id DESC"
        );
        $stmt->execute();

        $data = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[] = $row;
        }

        return $data;
    }

    // public function getAll($page, $limit)
    // {
    //     /**
    //      * Fetch all the theft report
    //      */
    //     $offset = ($page - 1) * $limit;


    //     $stmt = $this->conn->prepare(
    //         "SELECT * 
    //                 FROM {$this->table}
    //                 ORDER BY id DESC LIMIT :limits OFFSET :offset"
    //     );
    //     $stmt->bindParam(':limits', $limit, PDO::PARAM_INT);
    //     $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    //     $stmt->execute();

    //     $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //     /**Get the total number of items */

    //     $totalQuery = "SELECT COUNT(*) as total FROM {$this->table}";
    //     $totalStmt = $this->conn->query($totalQuery);
    //     $totalItems = $totalStmt->fetchColumn();

    //     return [
    //         'data' => $data,
    //         'totalItems' => $totalItems,
    //         'currentPage' => $page,
    //         'totalPages' => ceil($totalItems / $limit)
    //     ];
    // }

    public function create(array $data)
    {
        $stmt = $this->conn->prepare("INSERT INTO log_tbl(log_id, userid, log_type, messages, save_date, time_stamp, ip, device, geolocation)
        VALUES(:id, :userid, :types, :messages, :saveDate, :stamp, :ip, :device, :geolocation)");
        $ip = $this->utility->getUserIpAddr();

        $stmt->bindValue(":id", substr(str_shuffle(MD5(microtime())), 0, 9));
        $stmt->bindValue(":userid", $data['userid']);
        $stmt->bindValue(":types", $data['types']);
        $stmt->bindValue(":messages", $data['messages']);
        $stmt->bindValue(":saveDate", date('d-m-Y', time()));
        $stmt->bindValue(":stamp", time());
        $stmt->bindValue(":ip", $ip);
        $stmt->bindValue(":device", $this->utility->getDeviceType());
        $stmt->bindValue(":geolocation", json_encode($this->utility->getUserLocation($ip)));
        return $stmt->execute();
    }

    public function delete(string $id)
    {
        /**
         * Delete a log using its log_id
         */

        $stmt = $this->conn->prepare("DELETE FROM log_tbl WHERE log_id = :id");

        $stmt->bindValue(':id', $id);

        if ($stmt->execute()) {
            $row = $stmt->rowCount();
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => 'Admin',
                'types' => 'Delete',
                'messages' => "Log $id deleted",
            ]);

            echo json_encode([
                "message" => "Log $id deleted",
                "status" => 'success',
                "rows" => $row
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'Log delete failed.',
            'status' => 'error'
        ]);
    }
}
