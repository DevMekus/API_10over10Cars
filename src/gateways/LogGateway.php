<?php
class LogGateway
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
         * Fetch the log associated to this $id(userid)
         */
        $stmt = $this->conn->prepare(
            "SELECT * 
                    FROM log_tbl
                        WHERE userid = :id"
        );

        $stmt->bindValue(':id', $id);
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
                'userid' => 'Admin ',
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
