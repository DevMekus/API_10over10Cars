<?php

declare(strict_types=1);

$directories = [
    __DIR__ . "/src/controllers",
    __DIR__ . "/src/gateways",
    __DIR__ . "/src/config",
];

spl_autoload_register(function ($class) use ($directories) {
    foreach ($directories as $directory) {
        $file = $directory . DIRECTORY_SEPARATOR . $class . '.php';

        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});


set_error_handler("ErrorHandler::handleError");
set_exception_handler("ErrorHandler::handleException");

header("Content-type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Origin: *");



$parts = explode('/', $_SERVER['REQUEST_URI']);
$id = $parts[3] ?? null;
$featureId = $parts[4] ?? null;

$database =  new Database("localhost", "10over10", "root", "");


/**
 * ! REMEMBER TO ADD THE ACTIVITY LOG ON GATES
 */

switch ($parts[2]) {
    case "account":
        $accountGate = new AccountGateway($database);
        $account = new AccountController($accountGate);
        $account->processRequest($_SERVER['REQUEST_METHOD'], $id);
        break;
    case "admin":
        $adminGate = new AdminGateway($database);
        $admin = new AdminController($adminGate);
        $admin->processRequest($_SERVER['REQUEST_METHOD'], $id);
        break;
    case "vehicle":
        $vehicleGate = new CarGateway($database);
        $vehicle = new CarController($vehicleGate);
        $vehicle->processRequest($_SERVER['REQUEST_METHOD'], $id, $featureId);
        break;
    case "report":
        $reportGate = new ReportGateway($database);
        $report = new ReportController($reportGate);
        $report->processRequest($_SERVER['REQUEST_METHOD'], $id);
        break;
    case "transaction":
        $transactionGate = new TransactionGateway($database);
        $transaction = new TransactionController($transactionGate);
        $transaction->processRequest($_SERVER['REQUEST_METHOD'], $id);
        break;
    case "features":
        $featureGate = new FeatureGateway($database);
        $features = new FeatureController($featureGate);
        $features->processRequest($_SERVER['REQUEST_METHOD'], $id, $featureId);
        break;

    case "test":
        echo json_encode(['message' => 'API working and found: FeatureID:' . $featureId]);
        break;

    default:
        http_response_code(404);
        exit();
        break;
}
