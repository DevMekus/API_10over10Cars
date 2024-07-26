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
                    LEFT JOIN supportmessages
                    ON support.ticketid = supportmessages.ticket
                        WHERE support.userid = :id"
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
    }

    public function create(array $data)
    {
    }

    public function update(array $prev, array $data)
    {
    }

    public function delete(string $id)
    {
    }
}
