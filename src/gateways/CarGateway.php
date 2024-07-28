<?php
class CarGateway
{
    private PDO $conn;
    private $utility;
    private $carInformation = [
        'gallery' => 'gallery',
        'analysis' => 'analysis',
        'odometer' => 'odometer',
        'ownershiphistory' => 'ownershiphistory',
        'saleshistory' => 'saleshistory',
        'syscheck' => 'syscheck',
    ];

    private $createFunctions = [
        "vehicleImages",
        "marketAnalysis",
        "newSystemCheck",
        "newSalesHistory",
        "newOdometer",
        "ownershipHistory",
        "carInfo",
        "verifyvin",
        "newCarInfo"
    ];

    private $updateFunctions = [
        "updateAnalysis",
        "updateSysCheck",
        "updateSalesHistory",
        "updateOdometer",
        "updateOwnership",
        "updateCarInfo",
        "updateCarInfo"
    ];

    public function __construct(Database $database)
    {
        $this->conn = $database->getConnection();
        $this->utility =  new Utility($database);
    }

    public function get(string $id)
    {
        /**
         * Get all the car data, 
         * filter out the particular verifying feature
         * $Id is the feature being searched for
         */

        foreach ($this->carInformation as $verify => $table) {
            if ($verify == $id) {
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
         * fetch all the vehicle and also all the data associated
         */
        $vehicleArray = [];

        foreach ($this->carInformation as $title => $table) {
            $stmt = $this->conn->prepare(
                "SELECT * FROM $table 
                    ORDER BY id DESC"
            );

            $stmt->execute();

            $data = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data[] = $row;
            }

            array_push($vehicleArray, [
                "$title" => $data
            ]);
        }

        return $vehicleArray;
    }

    public function create(array $data)
    {

        foreach ($this->createFunctions as $function) {
            if ($data['stage'] == $function) {
                $function($prev = null, $data);
            } else {
                http_response_code(400);
                echo json_encode(
                    [
                        'message' => 'Request not understood',
                        'status' => 'error'
                    ]
                );
            }
        }
    }

    private function verifyVin($data)
    {
        $vin = $data['vin'];

        /**
         * Check if vin exists in vehicles, 
         * if True: return the vin
         * Else:
         * Create a new vehicle profile
         */
        $stmt = $this->conn->prepare("SELECT COUNT(*) 
        FROM vehicles 
            WHERE vin = :vin");

        $stmt->bindValue(":vin", $vin);
        $stmt->execute();

        if ($stmt->fetchColumn() > 0) {
            http_response_code(200);
            echo json_encode([
                'message' => 'Vin already exists.',
                'status' => 'success',
                'vin' => "$vin"
            ]);
            exit;
        }
        if ($this->registerVin($data)) {
            http_response_code(200);
            echo json_encode([
                'message' => 'Vehicle vin registered.',
                'status' => 'success',
                'vin' => "$vin"
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'An error occurred during vin register.',
            'status' => 'error',
        ]);
    }

    private function registerVin(array $data)
    {
        /**
         * Register a new Vin
         */
        $vin = $data['vin'];

        $stmt = $this->conn->prepare(
            "INSERT INTO vehicles(vin, userid, create_date, car_status, time_stamp)
            VALUES(:vin, :userid, :sdate, :vstatus, :tstamp)"
        );


        $stmt->bindValue(":vin", $vin);
        $stmt->bindValue(":userid", $data['userid']);
        $stmt->bindValue(":sdate", date("d-m-Y", time()));
        $stmt->bindValue(":vstatus", 'unverified');
        $stmt->bindValue(":tstamp", time());
        /**
         * Save Activity
         */
        $this->utility->logActivity([
            'userid' => $data['userid'],
            'types' => 'Verification',
            'messages' => "vehicle $vin registered",
        ]);

        return $stmt->execute();
    }

    private function newCarInfo(array $data)
    {
        /**
         * Vehicle information can only have one record associated to a vin
         */
        $vin = $data['vin'];

        /**
         * Check if vin exists in vinfo, 
         * if True: return the vin
         * Else:
         * Create a new vehicle profile
         */
        $stmt = $this->conn->prepare("SELECT COUNT(*) 
        FROM vinfo 
            WHERE vin = :vin");

        $stmt->bindValue(":vin", $vin);
        $stmt->execute();

        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode([
                'message' => 'Duplicate entries not allowed.',
                'status' => 'error',
                'vin' => "$vin"
            ]);
            exit;
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO vinfo(vin, brand, model, color, price, fuel, body, engines, country)
            VALUES(:vin, :brand, :model, :color, :price, :fuel, :body, :engines, :country)"
        );

        $stmt->bindValue(":vin", $data['vin']);
        $stmt->bindValue(":brand", $data['brand']);
        $stmt->bindValue(":model", $data['model']);
        $stmt->bindValue(":color", $data['color']);
        $stmt->bindValue(":price", $data['price']);
        $stmt->bindValue(":fuel", $data['fuel']);
        $stmt->bindValue(":body", $data['body']);
        $stmt->bindValue(":engines", $data['engines']);
        $stmt->bindValue(":country", $data['country']);

        if ($stmt->execute()) {
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $data['userid'],
                'types' => 'Verification',
                'messages' => "vehicle $vin info data saved",
            ]);

            http_response_code(200);
            echo json_encode([
                'message' => 'Vehicle Information registered.',
                'status' => 'success',
                'vin' => "$vin"
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'An error occurred during registration.',
            'status' => 'error',
        ]);
    }

    private function ownershipHistory(array $data)
    {
        /**
         * Ownership History
         */
        $vin = $data['vin'];

        $stmt = $this->conn->prepare(
            "INSERT INTO ownershiphistory(vin, fullname, miles_driven, odometer_read, purchase_date, use_length)
            VALUES(:vin, :fullname, :miles, :odometer, :purchaseDate, :uselength)"
        );
        $stmt->bindValue(":vin", $data['vin']);
        $stmt->bindValue(":fullname", $data['fullname']);
        $stmt->bindValue(":miles", $data['miles']);
        $stmt->bindValue(":odometer", $data['odometer']);
        $stmt->bindValue(":purchaseDate", $data['purchaseDate']);
        $stmt->bindValue(":uselength", $data['uselength']);
        if ($stmt->execute()) {
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $data['userid'],
                'types' => 'Verification',
                'messages' => "vehicle $vin owner's data saved",
            ]);

            http_response_code(200);
            echo json_encode([
                'message' => 'New ownership data saved',
                'status' => 'success',
                'vin' => "$vin"
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'An error occurred during registration.',
            'status' => 'error',
        ]);
    }

    private function newOdometer(array $data)
    {
        /**
         * Odometer Readings
         */
        $vin = $data['vin'];

        $stmt = $this->conn->prepare(
            "INSERT INTO odometer(vin, title, verdict, note)
            VALUES(:vin, :title, :verdict, :note)"
        );

        $stmt->bindValue(":vin", $vin);
        $stmt->bindValue(":title", $data['title']);
        $stmt->bindValue(":verdict", $data['verdict']);
        $stmt->bindValue(":note", $data['note']);

        if ($stmt->execute()) {
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $data['userid'],
                'types' => 'Verification',
                'messages' => "vehicle $vin Odometer reading saved",
            ]);

            http_response_code(200);
            echo json_encode([
                'message' => "vehicle $vin Odometer reading saved",
                'status' => 'success',
                'vin' => "$vin"
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'An error occurred during registration.',
            'status' => 'error',
        ]);
    }

    private function newSalesHistory(array $data)
    {
        /**
         * Sales history
         */
        $vin = $data['vin'];

        $stmt = $this->conn->prepare(
            "INSERT INTO saleshistory(vin, cost, locations, odometer, sdate)
            VALUES(:vin, :cost, :locations, :odometer, :sdate)"
        );

        $stmt->bindValue(":vin", $vin);
        $stmt->bindValue(":cost", $data['cost']);
        $stmt->bindValue(":locations", $data['locations']);
        $stmt->bindValue(":odometer", $data['odometer']);
        $stmt->bindValue(":sdate", $data['sdate']);

        if ($stmt->execute()) {
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $data['userid'],
                'types' => 'Verification',
                'messages' => "vehicle $vin Sales history saved",
            ]);

            http_response_code(200);
            echo json_encode([
                'message' => "vehicle $vin Sales history saved",
                'status' => 'success',
                'vin' => "$vin"
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'An error occurred during registration.',
            'status' => 'error',
        ]);
    }
    private function newSystemCheck(array $data)
    {
        /**
         * System checks
         */
        $vin = $data['vin'];

        $stmt = $this->conn->prepare(
            "INSERT INTO syscheck(vin, title, note, sdate)
            VALUES(:vin, :title, :note, :sdate)"
        );

        $stmt->bindValue(":vin", $vin);
        $stmt->bindValue(":title", $data['title']);
        $stmt->bindValue(":note", $data['note']);
        $stmt->bindValue(":sdate", $data['sdate']);

        if ($stmt->execute()) {
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $data['userid'],
                'types' => 'Verification',
                'messages' => "vehicle $vin system check saved",
            ]);

            http_response_code(200);
            echo json_encode([
                'message' => "vehicle $vin system check saved",
                'status' => 'success',
                'vin' => "$vin"
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'An error occurred during registration.',
            'status' => 'error',
        ]);
    }

    private function marketAnalysis(array $data)
    {
        /**
         * System checks
         */
        $vin = $data['vin'];

        $stmt = $this->conn->prepare(
            "INSERT INTO analysis(vin, note, sdate)
            VALUES(:vin, :note, :sdate)"
        );

        $stmt->bindValue(":vin", $vin);
        $stmt->bindValue(":note", $data['note']);
        $stmt->bindValue(":sdate", $data['sdate']);

        if ($stmt->execute()) {
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $data['userid'],
                'types' => 'Verification',
                'messages' => "vehicle $vin market analysis saved",
            ]);

            http_response_code(200);
            echo json_encode([
                'message' => "vehicle $vin market analysis saved",
                'status' => 'success',
                'vin' => "$vin"
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'An error occurred during registration.',
            'status' => 'error',
        ]);
    }

    private function vehicleImages(array $data)
    {
        /**
         * Uploading vehicle Images
         */
        $vin = $data['vin'];

        if (isset($data['file_upload'])) {
            $uploadDIR = "../UPLOADS/Images/";

            foreach ($data['file_upload'] as $file) {
                $fileName = $file['file_name'];
                $fileType = $file['file_type'];
                $fileContent = $file['fileContent'];

                $filename = $this->utility->uploadDocument([
                    $fileName,
                    $fileType,
                    $fileContent,
                    $uploadDIR
                ]);

                $this->utility->saveCarImage($filename, $data['vin']);
            }
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $data['userid'],
                'types' => 'Verification',
                'messages' => "vehicle $vin images saved",
            ]);

            http_response_code(200);
            echo json_encode([
                'message' => "vehicle $vin images saved",
                'status' => 'success',
                'vin' => "$vin"
            ]);
            exit;
        }
    }


    public function update(array $prev, array $data)
    {
        /**
         * Updating the data
         */
        foreach ($this->updateFunctions as $updatefunction) {
            if ($data['update'] == $updatefunction) {
                $updatefunction($prev, $data[0]);
            } else {
                http_response_code(400);
                echo json_encode(
                    [
                        'message' => 'Request not understood',
                        'status' => 'error'
                    ]
                );
            }
        }
    }

    public function delete(string $id, string $vId)
    {
        /**
         * Deletes a record from a verify table whose id is $featureId
         * $id = Verify/3
         * Ex: Odometer/3
         */
        foreach ($this->carInformation as $verify => $table) {
            if ($verify == $id) {
                $stmt = $this->conn->prepare(
                    "DELETE FROM $table WHERE id = :$vId"
                );

                $stmt->bindValue(":id", $vId);
                if ($row = $stmt->execute()) {
                    /**
                     * Save Activity
                     */
                    $this->utility->logActivity([
                        'userid' => 'Admin',
                        'types' => 'Delete',
                        'messages' => "$id id: $vId deleted",
                    ]);

                    echo json_encode([
                        "message" => "$id id: $vId deleted",
                        "status" => 'success',
                        "rows" => $row
                    ]);
                    exit;
                }
                http_response_code(500);
                echo json_encode([
                    'message' => 'Delete failed.',
                    'status' => 'error'
                ]);
            }
        }
    }
}
