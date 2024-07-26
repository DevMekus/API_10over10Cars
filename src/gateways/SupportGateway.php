<?php
class SupportGateway
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
         * Fetch the Support associated to this $id(Vin)
         */
        $stmt = $this->conn->prepare(
            "SELECT *                   
                FROM support                           
                    LEFT JOIN supportmessages reply
                        ON support.ticketid = reply.ticket
                            WHERE support.ticketid = :id 
                                OR support.userid = :userid                           
                                ORDER BY support.time_stamp DESC"
        );

        $stmt->bindValue(':id', $id);
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
         * Fetch all the support messages
         */
        $stmt = $this->conn->prepare(
            "SELECT *                   
                FROM support                           
                    LEFT JOIN supportmessages reply
                        ON support.ticketid = reply.ticket                                                 
                            ORDER BY support.time_stamp DESC"
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
        switch ($data['support_type']) {
            case 'newticket':
                $this->NewTicket($data);
                break;
            case 'reply':
                $this->ticketReply($data);
                break;
            default:
                echo json_encode([
                    'message' => 'Request not understood.',
                    'status' => 'error'
                ]);
                break;
        }
    }

    public function NewTicket(array $data)
    {
        /**
         * Creates a new support ticket
         */
        $ticketId = substr(str_shuffle(MD5(microtime())), 0, 9);

        /**Check if ticket exists */

        $stmt = $this->conn->prepare("SELECT COUNT(*) 
         FROM support 
         WHERE ticketid = :id 
         AND userid = :userid");

        $stmt->bindValue(":userid", $data['userid']);
        $stmt->bindValue(":id", $ticketId);
        $stmt->execute();

        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode([
                'message' => 'Ticket already exists.',
                'status' => 'error'
            ]);

            exit;
        }

        $sql = "INSERT INTO support(ticketid, userid, title, ticket_status, priority, create_date, time_stamp)
        VALUES(:ticketid, :userid, :title, :ticket_status, :priority, :create_date, :time_stamp)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":ticketid", $ticketId);
        $stmt->bindValue(":userid", $data['userid']);
        $stmt->bindValue(":title", $data['subject']);
        $stmt->bindValue(":ticket_status", 'open');
        $stmt->bindValue(":priority", $data['priority']);
        $stmt->bindValue(":create_date", Date('d-m-Y', time()));
        $stmt->bindValue(":time_stamp", time());

        if ($stmt->execute()) {
            /**
             * Saving the message to comments
             */
            $supportData = [
                'ticket_id' => $ticketId,
                'userid' => $data['userid'],
                'messages' => $data['messages']
            ];

            if ($this->supportCoversation($supportData)) {
                /**
                 * Save Activity
                 */
                $this->utility->logActivity([
                    'userid' => $data['userid'],
                    'types' => 'Support',
                    'messages' => 'Support ticket created',
                ]);

                echo json_encode([
                    'message' => 'New support ticket created',
                    'status' => 'success',
                    'row' => $stmt->rowCount()
                ]);
                exit;
            }
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'New support ticket failed.',
            'status' => 'error'
        ]);
    }

    private function supportCoversation(array $data)
    {
        /**
         * Adds to the support conversation
         */
        $sql = "INSERT INTO supportmessages(ticket, posters, messages, message_time)
        VALUES(:ticket, :posters, :messages, :message_time)";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(":ticket", $data['ticket_id']);
        $stmt->bindValue(":posters", $data['userid']);
        $stmt->bindValue(":messages", $data['messages']);
        $stmt->bindValue(":message_time",  time());

        return $stmt->execute();
    }

    public function ticketReply(array $data)
    {
        /**
         * Continued support Conversation 
         */
        if ($this->supportCoversation($data)) {
            $ticketId = $data['ticket_id'];

            $this->utility->logActivity([
                'message' => "Replied to ticket: $ticketId",
                'userid' => $data['userid'],
                'type' => 'support'
            ]);

            echo json_encode([
                'message' => 'Support comment posted.',
                'status' => 'success',
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'Support comment failed.',
            'status' => 'error'
        ]);
    }

    public function update(array $prev, array $data)
    {
        /**
         * Update the status of the Ticket
         */

        $stmt = $this->conn->prepare("UPDATE support 
                    SET ticket_status = :stat 
                        WHERE ticketid = :id");

        $stmt->bindValue(":id", $data['ticket_id']);
        $stmt->bindValue(":stat", $data['status']);

        if ($stmt->execute()) {
            /**
             * Save Activity
             */
            $ticketId = $data['ticket_id'];
            $status = $data['status'];

            $this->utility->logActivity([
                'userid' => $data['userid'],
                'types' => 'Support',
                'messages' => "Ticket $ticketId updated to $status",
            ]);

            http_response_code(201);
            echo json_encode([
                "message" => "Ticket $ticketId updated to $status",
                'status' => 'success',
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'Ticket update failed.',
            'status' => 'error'
        ]);
    }

    public function delete(string $id)
    {
        /**
         * Delete support message and conversation
         */
        $prev = $this->get($id);
        $userid = "";

        foreach ($prev as $data) {
            if ($data['ticketid'] == $id) {
                $userid = $data['userid'];
            }
        }

        $stmt = $this->conn->prepare(
            "DELETE from support 
                WHERE ticketid = :id"
        );

        $stmt->bindValue("id", $id);
        if ($stmt->execute()) {
            $stmt = $this->conn->prepare(
                "DELETE from supportmessages WHERE ticket = :id"
            );
            $stmt->bindValue("id", $id);

            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $userid,
                'types' => 'Support',
                'messages' => "Ticket: $id deleted",
            ]);

            http_response_code(201);
            echo json_encode([
                "message" => "Ticket: $id deleted",
                'status' => 'success',
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => "Ticket: $id deleted",
            'status' => 'error'
        ]);
    }
}
