<?php
class FeatureGateway
{
    private PDO $conn;
    private $utility;
    private $features = [
        'colors' => 'colors',
        'models' => 'vmodel',
        'bodys' => 'vbody',
        'brands' => 'vbrand',
        'fuels' => 'vfuel',
        'transmissions' => 'vtransmission',
        'engines' => 'vengine',
    ];


    public function __construct(Database $database)
    {
        $this->conn = $database->getConnection();
        $this->utility =  new Utility($database);
    }

    public function get(string $id)
    {
        /**
         * Fetch a particular feature. 
         * The ID is the name of the Feature
         */
        foreach ($this->features as $feature => $table) {
            if ($feature == $id) {
                $stmt = $this->conn->prepare(
                    "SELECT * FROM $table"
                );

                $stmt->execute();

                $data = [];

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $data[] = $row;
                }

                return $data;
            }
        }
    }

    public function getAll()
    {
        /**
         * Gets all the feature and sends it in a huge array
         * $this->features array holds the feature title and its table name
         */

        $featuresArray = [];

        foreach ($this->features as $feature => $table) {
            $data = $this->get($feature);

            array_push($featuresArray, [
                "$feature" => $data
            ]);
        }

        return $featuresArray;
    }

    public function create(array $data)
    {
        /**
         * Saves a new feature
         */
        $table = $data['tbl'];
        $column = $data['column'];

        /**
         * Check if feature Exists
         */
        $stmt = $this->conn->prepare("SELECT COUNT(*) 
        FROM $table
            WHERE $column = :$column
                ");

        $stmt->bindValue(":$column", $column);
        $stmt->execute();

        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode([
                'message' => 'Feature already exists.',
                'status' => 'error'
            ]);
            exit;
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO $table($column)VALUES(:$column)"
        );
        $stmt->bindValue(":$column", $column);
        if ($stmt->execute()) {
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $data['userid'],
                'types' => 'Feature',
                'messages' => 'New vehicle feature added',
            ]);

            http_response_code(201);
            echo json_encode([
                "message" => "New vehicle feature added",
                'status' => 'success',
            ]);
            exit;
        }
        http_response_code(500);
        echo json_encode([
            'message' => 'New feature failed.',
            'status' => 'error'
        ]);
    }


    public function delete(string $id, string $featureId)
    {
        /**
         * Deletes a feature from a table whose id is $featureId
         * $id = feature/3
         * Ex: Color/3
         */
        foreach ($this->features as $feature => $table) {
            if ($feature == $id) {
                $stmt = $this->conn->prepare(
                    "DELETE FROM $table WHERE id = :$featureId"
                );

                $stmt->bindValue(":id", $featureId);

                if ($row = $stmt->execute()) {
                    /**
                     * Save Activity
                     */
                    $this->utility->logActivity([
                        'userid' => 'Admin',
                        'types' => 'Delete',
                        'messages' => "$id $featureId deleted",
                    ]);

                    echo json_encode([
                        "message" => "$id id: $featureId deleted",
                        "status" => 'success',
                        "rows" => $row
                    ]);
                    exit;
                }
                http_response_code(500);
                echo json_encode([
                    'message' => 'Feature delete failed.',
                    'status' => 'error'
                ]);
            }
        }
    }
}
