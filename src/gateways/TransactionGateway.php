<?php
class TransactionGateway
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
         * Fetch the Transactions associated to this $id(ref)
         */
        $stmt = $this->conn->prepare(
            "SELECT * 
                    FROM transaction_tbl
                        WHERE ref = :ref"
        );

        $stmt->bindValue(':ref', $id);
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
                    FROM transaction_tbl
                    ORDER BY id DESC"
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
         * Save a new transaction report
         */
        $ref = substr(str_shuffle(MD5(microtime())), 0, 10);

        /**
         * Checking if transaction already exists
         */
        $stmt = $this->conn->prepare("SELECT COUNT(*) 
        FROM transaction_tbl 
            WHERE ref = :id 
                OR invoiceid  = :invoice");

        $stmt->bindValue(":id", $ref);
        $stmt->bindValue(":invoiceid", $data['invoiceid']);
        $stmt->execute();

        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode([
                'message' => 'Transaction already exists.',
                'status' => 'error'
            ]);
            exit;
        }

        $sql = "INSERT INTO transaction_tbl(ref, invoiceid, userid, cart_items, tstatus, sdate, tstamp)
        VALUES(:ref, :invoiceid, :userid, :cart_items, :tstatus, :sdate, :tstamp)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":ref", $ref);
        $stmt->bindValue(":invoiceid", $data['invoiceid']);
        $stmt->bindValue(":userid", $data['userid']);
        $stmt->bindValue(":cart_items", $data['cart_items']);
        $stmt->bindValue(":tstatus", $data['tstatus']);
        $stmt->bindValue(":", date("d-m-Y", time()));
        $stmt->bindValue(":", time());

        if ($stmt->execute()) {
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $data['userid'],
                'types' => 'Transaction',
                'messages' => 'New transaction saved',
            ]);

            http_response_code(201);
            echo json_encode([
                "message" => "Transaction successful!",
                'status' => 'success',
            ]);
            exit;
        }
        http_response_code(500);
        echo json_encode([
            'message' => 'Transaction failed!',
            'status' => 'error'
        ]);
    }

    public function update(array $prev, array $data)
    {
        /**
         * Updating the transaction status
         */
        $stmt = $this->conn->prepare("UPDATE transaction_tbl SET tstatus = :tstatus WHERE ref = :ref");

        $stmt->bindValue(":tstatus", $data[0]['tstatus'] ?? $prev['tstatus']);
        $stmt->bindValue(":ref", $data[0]['ref'] ?? $prev['ref']);

        if ($row = $stmt->execute()) {
            $userid = $prev['userid'];
            $id = $prev['ref'];
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $userid,
                'types' => 'Update',
                'messages' => 'Transaction status updated.',
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
                'message' => 'Transaction update failed.',
                'status' => 'error'
            ]
        );
    }

    public function delete(string $id)
    {
        /**
         * Delete transaction record
         */
        $transaction = $this->get($id);

        $stmt = $this->conn->prepare("DELETE FROM transaction_tbl WHERE ref = :id");

        $stmt->bindValue(':id', $id);

        if ($stmt->execute()) {
            $row = $stmt->rowCount();

            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $transaction['userid'],
                'types' => 'Delete',
                'messages' => "Transaction ref: $id deleted",
            ]);

            echo json_encode([
                "message" => "Transaction ref: $id deleted",
                "status" => 'success',
                "rows" => $row
            ]);
            exit;
        }
        
        http_response_code(500);
        echo json_encode([
            'message' => 'Transaction delete failed.',
            'status' => 'error'
        ]);
    }
}
