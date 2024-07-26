<?php

class LogController
{
    private $gateway;

    public function __construct(LogGateway $gateway)
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
        $log = $this->gateway->get($id);

        if (!$log) {
            http_response_code(404);
            echo json_encode(["message" => "Log not found"]);
            return;
        }

        switch ($method) {
            case "GET":
                echo json_encode($log);
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
            default:
                http_response_code(405); //Method not allowed
                header("Allow: GET, POST");
        }
    }
}
