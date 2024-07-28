<?php

class CarController
{
    private $gateway;

    public function __construct(CarGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function processRequest(
        string $method,
        ?string $id,
        ?string $vId
    ): void {

        if ($id) {
            $this->processResourceRequest($method, $id, $vId);
        } else {
            $this->processCollectionRequest($method);
        }
    }

    private function processResourceRequest(
        string $method,
        string $id,
        ?string $vId
    ): void {
        $vehicle = $this->gateway->get($id);

        if (!$vehicle) {
            http_response_code(404);
            echo json_encode(["message" => "Vehicle data not found"]);
            return;
        }

        switch ($method) {
            case "GET":
                echo json_encode($vehicle);
                break;

            case "PATCH":
                $data = (array) json_decode(file_get_contents("php://input"), true);

                $this->gateway->update($vehicle, $data);
                break;

            case "DELETE":
                $this->gateway->delete($id, $vId);
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
