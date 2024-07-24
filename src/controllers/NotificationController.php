<?php

class AdminController
{
    private $gateway;

    public function __construct(NotificationGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function processRequest(string $method, ?string $id): void
    {

        if ($id) {
            $this->processResourceRequest($method, $id);
        } else {
            $this->processCollectionRequest($method);
        }
    }

    private function processResourceRequest(string $method, string $id): void
    {
        $account = $this->gateway->get($id);

        if (!$account) {
            http_response_code(404);
            echo json_encode(["message" => "Account not found"]);
            return;
        }

        switch ($method) {
            case "GET":
                echo json_encode($account);
                break;

            case "PATCH":
                $data = (array) json_decode(file_get_contents("php://input"), true);

                /**Validate errors here */
                $row = $this->gateway->update($account, $data);

                echo json_encode([
                    "message" => "Account $id updated",
                    "status" => 'success',
                    "rows" => $row,

                ]);

                break;

            case "DELETE":
                $row = $this->gateway->delete($id);

                if ($row) {
                    echo json_encode([
                        "message" => "Account $id deleted",
                        "status" => 'success',
                        "rows" => $row
                    ]);
                }

            default:
                http_response_code(405); //Method not allowed
                header("Allow: GET, PATCH, DELETE");
        }
    }

    private function processCollectionRequest(string $method): void
    {

        switch ($method) {
            case "GET":
                echo json_encode($this->gateway->getAll());
                break;

            case "POST":
                $data = (array) json_decode(file_get_contents("php://input"), true);

                $this->gateway->create($data);

                break;
            default:
                http_response_code(405); //Method not allowed
                header("Allow: GET, POST");
        }
    }
}
