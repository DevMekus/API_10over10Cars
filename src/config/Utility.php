<?php
class Utility
{
    private $conn;
    private $database;

    public function __construct(Database $database)
    {
        $this->conn = $database->getConnection();
        $this->database = $database;
    }

    public function logActivity(array $data)
    {
        /**
         * Saves activity log
         */

        $stmt = $this->conn->prepare("INSERT INTO log_tbl(log_id, userid, log_type, messages, save_date, time_stamp)
        VALUES(:id, :userid, :types, :messages, :saveDate, :stamp)");

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
            'application/pdf'
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

    public function saveCarImage(string $vin, string $fileName)
    {
        $sql = "INSERT INTO gallery(vin, file_name)VALUES(:vin, :vname)";
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

    public function badRequest()
    {
        http_response_code(400);
        echo json_encode(
            [
                'message' => 'Request not understood',
                'status' => 'error',
                'title' => 'Bad Request'
            ]
        );
    }

    public function accountData(string $id)
    {
        /**
         * Fetch data to store in the storage
         * $id is userid
         */
        $account = new AccountGateway($this->database);
        $logs = new LogGateway($this->database);
        $user = $account->get($id);

        if ($user) {
            return [
                'fullname' => $user[0]['fullname'],
                'email' => $user[0]['email_address'],
                'last-seen' => $logs->lastSeen($id)
            ];
        }
    }

    public function getUserIpAddr()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            // Check if IP is from shared internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Check if IP is passed from a proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            // Use REMOTE_ADDR as fallback
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // If there's a list of IPs (e.g., from a proxy), get the first one
        $ip = explode(',', $ip)[0];

        return trim($ip);
    }

    public function getDeviceType()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $isMobile = preg_match('/(android|iphone|ipad|ipod|mobile|silk|kindle|blackberry|opera mini|opera mobi|palm os|windows phone|iemobile)/i', $userAgent);

        return $isMobile ? 'Mobile' : 'PC';
    }

    public function getUserLocation($ip)
    {
        $apiUrl = "http://ipinfo.io/{$ip}/json";
        $locationData = file_get_contents($apiUrl);
        return json_decode($locationData, true);
    }

    public function testLocation()
    {
        $ip = $this->getUserIpAddr();

        echo json_encode([
            'ip' => $ip,
            'device' => $this->getDeviceType(),
            'location' => $this->getUserLocation($ip)
        ]);
    }
}
