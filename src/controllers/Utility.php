<?php
class Utility
{
    private $conn;

    public function __construct(Database $database)
    {
        $this->conn = $database->getConnection();
    }

    public function logActivity(array $data)
    {
        /**
         * Saves activity log
         */

        $stmt = $this->conn->prepare("INSERT INTO log_tbl(log_id, userid, types, messages, save_date, time_stamp)VALUES(:id, :userid, :types, :messages, :saveDate, :stamp)");

        $stmt->bindValue(":id", substr(str_shuffle(MD5(microtime())), 0, 9));
        $stmt->bindValue(":userid", $data['userid']);
        $stmt->bindValue(":types", $data['types']);
        $stmt->bindValue(":messages", $data['messages']);
        $stmt->bindValue(":saveDate", date('d-m-Y', time()));
        $stmt->bindValue(":stamp", time());
        return $stmt->execute();
    }

    public function makeDirectory(string $userDir)
    {
        /**
         * Create a new directory
         */

        if (!file_exists($userDir)) {
            if (mkdir($userDir, 0777, true)) {
                chmod($userDir, 0777);
                return true;
            } else {
                echo json_encode([
                    'message' => 'New directory failed',
                    'path' => $userDir,
                    'status' => 'error'
                ]);
            }
        }
    }

    public function uploadDocument(array $data)
    {
        $allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/jpg',
            'application/pdf',
            'application/msword', //Word documents (.doc)
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // Word documents (.docx)
            'application/vnd.ms-excel', // Excel files (.xls)
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // Excel files (.xlsx)                    
        ];

        if (!in_array($data['fileType'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode(
                [
                    'message' => 'Invalid file type.',
                    'status' => 'error'
                ]
            );
            exit;
        }

        list($type, $fileContent) = explode(';', $data['fileContent']);
        list(, $fileContent) = explode(',', $fileContent);

        $fileContent = base64_decode($fileContent);

        // Ensure the upload directory exists and is writable
        if (!is_dir($data['uploadDir']) && !mkdir($data['uploadDir'], 0777, true)) {
            http_response_code(500);
            echo json_encode([
                'message' => 'Failed to create upload directory.',
                'status' => 'error'
            ]);
            exit;
        }

        /**Generate a unique file name */
        $fileName = uniqid() . '_' . $data['fileName'];
        $filepath = $data['uploadDir'] . $fileName;

        if (file_put_contents($filepath, $fileContent)) {
            return $fileName;
        }
    }

    public function downloadFile($filePath)
    {
        /**
         * Download a zipped file
         */
        if (file_exists($filePath)) {
            /**
             * Set headers to initiate file download
             */
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename=' . basename($filePath));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));

            /**
             * Read the file and output its contents
             */
            readfile($filePath);
            exit;
        } else {
            /**File not found */
            http_response_code(404);
            echo json_encode([
                'message' => 'File not found',
                'status' => 'error'
            ]);
        }
    }

    public function saveCarImage(string $fileName, string $vin)
    {
        $sql = "INSERT INTO car_gallery(vin, file_name)VALUES(:vin, :vname)";
        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(":vin", $vin);
        $stmt->bindValue(":file_name", $fileName);

        return $stmt->execute();
    }

    public function deleteCarImage(string $vin)
    {
        /**
         * Delete car Image data and unlink image from system
         */

        $stmt = $this->conn->prepare("SELECT * FROM car_gallery WHERE vin = :id");
        $stmt->bindValue(':id', $vin);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            unlink("../UPLOADS/Images/" . $row['file_name']);
        }

        $stmt = $this->conn->prepare("DELETE FROM car_gallery WHERE vin = :id");
        $stmt->bindValue(':id', $vin);
        return $stmt->execute();
    }
}
