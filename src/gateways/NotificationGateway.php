<?php
class NotificationGateway
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
         * Fetch the report associated to this $id(Vin)
         */
        $stmt = $this->conn->prepare(
            "SELECT * 
                    FROM notifications
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
                    FROM notifications
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
         * Save a new theft report
         */
        $ref = substr(str_shuffle(MD5(microtime())), 0, 9);

        /**
         * Checking if Report already exists
         */
        $stmt = $this->conn->prepare("SELECT COUNT(*) 
        FROM notifications 
            WHERE ref = :id");


        $stmt->bindValue(":id", $ref);
        $stmt->execute();

        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode([
                'message' => 'Notification already exists.',
                'status' => 'error'
            ]);
            exit;
        }

        $sql = "INSERT INTO notifications(userid, ref, title, messages, sdate, tstamp)
        VALUES(:userid, :ref, :title, :messages, :sdate, :tstamp)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":userid ", $data['userid ']);
        $stmt->bindValue(":ref", $ref);
        $stmt->bindValue(":title", $data['title']);
        $stmt->bindValue(":messages", $data['messages']);
        $stmt->bindValue(":sdate", date("d-m-Y", time()));
        $stmt->bindValue(":tstamp", time());

        if ($stmt->execute()) {
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $data['userid '],
                'types' => 'Notification',
                'messages' => 'Notification posted',
            ]);

            http_response_code(201);
            echo json_encode([
                "message" => "New Notification posted",
                'status' => 'success',
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'Notification failed',
            'status' => 'error'
        ]);
    }

    public function update(array $prev, array $data)
    {
        $stmt = $this->conn->prepare(
            "UPDATE notifications
                SET title = :title,
                    messages = :messages
                    WHERE ref = :ref"
        );
        $newdata = $data[0];

        $stmt->bindValue(":ref", $newdata['ref'] ?? $prev['ref']);
        $stmt->bindValue(":title", $newdata['title'] ?? $prev['title']);
        $stmt->bindValue(":messages", $newdata['messages'] ?? $prev['messages']);

        if ($row = $stmt->execute()) {
            $id = $prev['ref'];
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $prev['userid'],
                'types' => 'Update',
                'messages' => "Notification $id updated",
            ]);

            echo json_encode([
                "message" => "Notification $id updated",
                "status" => 'success',
                "rows" => $row,
            ]);
            exit;
        }
        http_response_code(400);
        echo json_encode(
            [
                'message' => 'Notification update failed.',
                'status' => 'error'
            ]
        );
    }

    public function delete(string $id)
    {
        /**
         * Delete a notification
         */
        $notice = $this->get($id);

        $stmt = $this->conn->prepare(
            "DELETE FROM notifications 
                WHERE ref = :id"
        );

        $stmt->bindValue(':id', $id);

        if ($stmt->execute()) {
            $row = $stmt->rowCount();
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $notice['userid'],
                'types' => 'Delete',
                'messages' => "Notification $id deleted",
            ]);

            echo json_encode([
                "message" => "Notification $id deleted",
                "status" => 'success',
                "rows" => $row
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'Notification delete failed.',
            'status' => 'error'
        ]);
    }
}
