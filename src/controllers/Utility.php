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
}
