<?php

class FeatureController
{
    private $gateway;

    public function __construct(FeatureGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function processRequest(
        string $method,
        ?string $id,
        ?string $featureId
    ): void {

        if ($id) {
            $this->processResourceRequest($method, $id, $featureId);
        } else {
            $this->processCollectionRequest($method);
        }
    }

    private function processResourceRequest(
        string $method,
        string $id,
        ?string $featureId
    ): void {
        $feature = $this->gateway->get($id);

        if (!$feature) {
            http_response_code(404);
            echo json_encode(["message" => "Feature not found"]);
            return;
        }

        switch ($method) {
            case "GET":
                echo json_encode($feature);
                break;

            case "DELETE":
                $row = $this->gateway->delete($id, $featureId);

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
