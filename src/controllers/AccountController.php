<?php

class AccountController
{
    private $gateway;

    public function __construct(AccountGateway $gateway)
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

    // private function requestReset(array $data)
    // {
    //     $email = $data['email'] ?? '';

    //     if (empty($email)) {
    //         http_response_code(400);
    //         echo json_encode(['message' => 'Email is required.']);
    //         exit;
    //     }

    //     $response = $this->gateway->requestReset($data);

    //     if ($response) {
    //         http_response_code(200);

    //         echo json_encode(['success' => 'Password reset link has been sent to your email.']);
    //     }
    // }

    // private function verifyToken(string $token)
    // {
    //     if (empty($token)) {
    //         http_response_code(400);
    //         echo json_encode(['message' => 'Token is required']);
    //         exit;
    //     }

    //     $response = $this->gateway->verifyToken($token);

    //     if ($response) {
    //         http_response_code(200);
    //         echo json_encode([
    //             'success' => 'Token is valid.',
    //             'action' => 'reset'
    //         ]);
    //     } else {
    //         http_response_code(400);
    //         echo json_encode(['message' => 'Invalid or expired token.']);
    //     }
    // }

    // private function resetPassword(string $token, array $data)
    // {
    //     $newPassword = $data['password'] ?? '';

    //     if (empty($token) || empty($newPassword)) {
    //         http_response_code(400);
    //         echo json_encode(['message' => 'Token and new password are required.']);
    //         exit;
    //     }

    //     $response = $this->gateway->resetPassword($token, $data);

    //     if ($response) {
    //         http_response_code(200);

    //         echo json_encode(['message' => 'Password has been reset successfully.']);
    //     } else {
    //         http_response_code(400);
    //         echo json_encode(['message' => 'Invalid or expired token.']);
    //     }
    // }
}
