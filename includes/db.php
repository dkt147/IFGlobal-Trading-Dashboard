<?php
// Database Configuration - Update these values
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'textile_commission');

// Logging function
function logDebug($message, $data = null) {
    $logFile = __DIR__ . '/../logs/debug.log';
    @mkdir(dirname($logFile), 0755, true);
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data !== null) {
        $logMessage .= ' | ' . json_encode($data);
    }
    $logMessage .= "\n";
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

logDebug('Database connection attempt', ['host' => DB_HOST, 'user' => DB_USER, 'db' => DB_NAME]);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    logDebug('Database connection FAILED', ['error' => $conn->connect_error]);
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

logDebug('Database connection SUCCESS');
$conn->set_charset("utf8mb4");

function db() {
    global $conn;
    return $conn;
}
?>
