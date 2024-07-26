<?php
class VerificationGateway
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
         * Fetch the Verification request associated to this $id(Vin)
         */
        $stmt = $this->conn->prepare(
            "SELECT * 
                    FROM verification_request
                        WHERE vin = :vin"
        );

        $stmt->bindValue(':vin', $id);
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
         * Fetch all the Verification request
         */
        $stmt = $this->conn->prepare(
            "SELECT * 
                    FROM verification_request"
        );
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
         * Save a new theft report
         */
        $requestId = substr(str_shuffle(MD5(microtime())), 0, 9);

        /**
         * Checking if Report already exists
         */
        $stmt = $this->conn->prepare("SELECT COUNT(*) 
        FROM verification_request 
            WHERE request_id = :id 
                OR vin = :vin");

        $stmt->bindValue(":id", $requestId);
        $stmt->bindValue(":vin", $data['vin']);
        $stmt->execute();

        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode([
                'message' => 'Verification already exists.',
                'status' => 'error'
            ]);
            exit;
        }

        $sql = "INSERT INTO verification_request(request_id, userid, payment_id, vin, request_status, request_date, time_stamp)
        VALUES(:request_id, :userid, :payment_id, :vin, :request_status, :request_date, :time_stamp)";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(":request_id", $requestId);
        $stmt->bindValue(":userid", $data['userid']);
        $stmt->bindValue(":payment_id", $data['payment_id']);
        $stmt->bindValue(":vin", $data['vin']);
        $stmt->bindValue(":request_status", $data['request_status']);
        $stmt->bindValue(":request_date", date('d-m-Y', time()));
        $stmt->bindValue(":time_stamp", time());

        if ($stmt->execute()) {
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $data['userid'],
                'types' => 'Verification',
                'messages' => 'Verification requested',
            ]);

            http_response_code(201);
            echo json_encode([
                "message" => "Verification requested successfully",
                'status' => 'success',
            ]);
            exit;
        }
        http_response_code(500);
        echo json_encode([
            'message' => 'Verification failed to save.',
            'status' => 'error'
        ]);
    }

    public function update(array $prev, array $data)
    {
        /**
         * Updating the transaction status
         */
        $stmt = $this->conn->prepare("UPDATE verification_request 
                    SET rstatus = :rstatus  
                    WHERE vin = :vin");

        $stmt->bindValue(":rstatus", $data[0]['rstatus'] ?? $prev['rstatus']);
        $stmt->bindValue(":vin", $data[0]['vin'] ?? $prev['vin']);

        if ($row = $stmt->execute()) {
            $id = $prev['ref'];
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $prev['userid'],
                'types' => 'Update',
                'messages' => 'Verification status updated.',
            ]);

            echo json_encode([
                "message" => "Transaction ref $id updated",
                "status" => 'success',
                "rows" => $row,
            ]);
            exit;
        }
        http_response_code(400);
        echo json_encode(
            [
                'message' => 'Verification update failed.',
                'status' => 'error'
            ]
        );
    }

    public function delete(string $id)
    {
        /**
         * Delete transaction record
         */
        $request = $this->get($id);

        $stmt = $this->conn->prepare("DELETE FROM verification_request WHERE vin = :id");

        $stmt->bindValue(':id', $id);

        if ($stmt->execute()) {
            $row = $stmt->rowCount();

            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $request['userid'],
                'types' => 'Delete',
                'messages' => "Verification request: $id deleted",
            ]);

            echo json_encode([
                "message" => "Verification request: $id deleted",
                "status" => 'success',
                "rows" => $row
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'Verification request delete failed.',
            'status' => 'error'
        ]);
    }
}
