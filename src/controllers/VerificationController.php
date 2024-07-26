<?php

class VerificationController
{
    private $gateway;

    public function __construct(VerificationGateway $gateway)
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
        $request = $this->gateway->get($id);

        if (!$request) {
            http_response_code(404);
            echo json_encode(["message" => "Request not found"]);
            return;
        }

        switch ($method) {
            case "GET":
                echo json_encode($request);
                break;

            case "PATCH":
                $data = (array) json_decode(file_get_contents("php://input"), true);

                $this->gateway->update($request, $data);
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
