<?php
class CarGateway
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
        /**
         * Creates a New car with the basic car Information.
         * Returns the Vin for further addition to the Database
         */
        switch ($data['create']) {
            case "VEHICLE":
                break;
            case "FEATURE":
                break;
        }
    }

    private function newCarInfo(array $data)
    {
    }

    private function newOwnershipInfo(array $data)
    {
    }

    private function newOdometer(array $data)
    {
    }

    private function newSalesHistory(array $data)
    {
    }
    private function newSystemCheck(array $data)
    {
    }


    public function update(array $prev, array $data)
    {
    }

    public function delete(string $id)
    {
    }
}
