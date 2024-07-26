<?php

class TransactionController
{
    private $gateway;

    public function __construct(TransactionGateway $gateway)
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
        $transaction = $this->gateway->get($id);

        if (!$transaction) {
            http_response_code(404);
            echo json_encode(["message" => "Transaction not found"]);
            return;
        }

        switch ($method) {
            case "GET":
                echo json_encode($transaction);
                break;

            case "PATCH":
                $data = (array) json_decode(file_get_contents("php://input"), true);

                /**Validate errors here */
                $this->gateway->update($transaction, $data);
                break;

            case "DELETE":
                $this->gateway->delete($id);
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
