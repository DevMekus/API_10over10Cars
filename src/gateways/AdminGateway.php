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
        return [];
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
