<?php
class ReportGateway
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
                    FROM theft_report_tbl
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
         * Fetch all the theft report
         */
        $stmt = $this->conn->prepare(
            "SELECT * 
                    FROM theft_report_tbl"
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
        $reportId = substr(str_shuffle(MD5(microtime())), 0, 9);

        /**
         * Checking if Report already exists
         */
        $stmt = $this->conn->prepare("SELECT COUNT(*) 
        FROM theft_report_tbl 
            WHERE theft_id = :id 
                OR vin = :vin");

        $stmt->bindValue(":id", $reportId);
        $stmt->bindValue(":vin", $data['vin']);
        $stmt->execute();

        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode([
                'message' => 'Report already exists.',
                'status' => 'error'
            ]);
            exit;
        }

        /**
         * Upload all the vehicle Documents, including Photos
         */

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
        }

        $sql = "INSERT INTO theft_report_tbl(userid, theft_id, vin, vtype, vbrand, vmodel, theft_location, theft_day, theft_month, theft_year, report_date, time_stamp)
        VALUES(:userid, :theft_id, :vin, :vtype, :vbrand, :vmodel, :theft_location, :theft_day, :theft_month, :theft_year, :report_date, :time_stamp)";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(":userid", $data['userid']);
        $stmt->bindValue(":theft_id", $reportId);
        $stmt->bindValue(":vin", $data['vin']);
        $stmt->bindValue(":vtype", $data['vtype']);
        $stmt->bindValue(":vbrand", $data['vbrand']);
        $stmt->bindValue(":vmodel", $data['vmodel']);
        $stmt->bindValue(":theft_location", $data['theft_location']);
        $stmt->bindValue(":theft_day", $data['theft_day']);
        $stmt->bindValue(":theft_month", $data['theft_month']);
        $stmt->bindValue(":theft_year", $data['theft_year']);
        $stmt->bindValue(":report_date", date("d-m-Y", time()));
        $stmt->bindValue(":time_stamp", time());

        if ($stmt->execute()) {
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $data['userid'],
                'types' => 'Report',
                'messages' => 'Reported theft',
            ]);

            http_response_code(201);
            echo json_encode([
                "message" => "Theft reported successfully",
                'status' => 'success',
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'Theft reporting failed.',
            'status' => 'error'
        ]);
    }

    public function update(array $prev, array $data)
    {
        $sql = "UPDATE theft_report_tbl SET
                           vtype = :vtype,
                           vbrand = :vbrand,
                           vmodel = :vmodel,
                           theft_location = :theft_location,
                           theft_day = :theft_day,
                           theft_month = :theft_month,
                           theft_year = :theft_year                          
                           WHERE vin = :vin";
        $stmt = $this->conn->prepare($sql);
        $newData = $data[0];
        $stmt->bindValue(":vtype", $newData['vtype'] ?? $prev['vtype']);
        $stmt->bindValue(":vbrand", $newData['vbrand'] ?? $prev['vbrand']);
        $stmt->bindValue(":vmodel", $newData['vmodel'] ?? $prev['vmodel']);
        $stmt->bindValue(":theft_location", $newData['theft_location'] ?? $prev['theft_location']);
        $stmt->bindValue(":theft_day", $newData['theft_day'] ?? $prev['theft_day']);
        $stmt->bindValue(":theft_month", $newData['theft_month'] ?? $prev['theft_month']);
        $stmt->bindValue(":theft_year", $newData['theft_year'] ?? $prev['theft_year']);
        $stmt->bindValue(":vin", $newData['vin'] ?? $prev['vin']);

        if ($row = $stmt->execute()) {
            $userid = $prev['userid'];
            $id = $prev['theft_id'];
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $userid,
                'types' => 'Update',
                'messages' => 'Theft report updated.',
            ]);

            echo json_encode([
                "message" => "Theft report $id updated",
                "status" => 'success',
                "rows" => $row,
            ]);

            exit;
        }

        http_response_code(400);
        echo json_encode(
            [
                'message' => 'Report update failed.',
                'status' => 'error'
            ]
        );
    }

    public function delete(string $id)
    {
        /**
         * Delete report and associated documents
         */

        $this->utility->deleteCarImage($id);

        $report = $this->get($id);
        $stmt = $this->conn->prepare("DELETE FROM theft_report_tbl WHERE vin = :id");

        $stmt->bindValue(':id', $id);

        if ($stmt->execute()) {
            $row = $stmt->rowCount();
            /**
             * Save Activity
             */
            $this->utility->logActivity([
                'userid' => $report['userid'],
                'types' => 'Delete',
                'messages' => "Report $id deleted",
            ]);

            echo json_encode([
                "message" => "Report $id deleted",
                "status" => 'success',
                "rows" => $row
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'message' => 'Report delete failed.',
            'status' => 'error'
        ]);
    }
}
