<?php
require_once 'includes/db.php';

echo "<pre style='background: #f5f5f5; padding: 20px; font-family: monospace; overflow: auto;'>";

// Check database connection
echo "=== DATABASE CONNECTION ===\n";
echo "Status: " . ($conn->connect_error ? "FAILED ❌\n" . $conn->connect_error : "CONNECTED ✓\n");
echo "Selected DB: " . DB_NAME . "\n\n";

// Check if owner table exists
echo "=== CHECKING TABLES ===\n";
$result = $conn->query("SHOW TABLES");
if ($result) {
    $tables = [];
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    echo "Tables found: " . implode(", ", $tables) . "\n\n";
    
    if (in_array('owner', $tables)) {
        echo "=== OWNER TABLE STRUCTURE ===\n";
        $columns = $conn->query("DESCRIBE owner");
        while ($col = $columns->fetch_assoc()) {
            echo $col['Field'] . " | " . $col['Type'] . " | " . $col['Null'] . " | " . $col['Key'] . "\n";
        }
        
        echo "\n=== OWNER RECORDS ===\n";
        $owners = $conn->query("SELECT id, username, full_name, email FROM owner");
        $count = $owners->num_rows;
        echo "Total records: $count\n";
        while ($owner = $owners->fetch_assoc()) {
            echo "  - ID: {$owner['id']}, Username: {$owner['username']}, Name: {$owner['full_name']}\n";
        }
    } else {
        echo "❌ 'owner' table NOT found!\n";
    }
} else {
    echo "Error checking tables: " . $conn->error . "\n";
}

// Show recent logs
echo "\n=== DEBUG LOGS ===\n";
$logFile = __DIR__ . '/logs/debug.log';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    $lines = array_slice(explode("\n", $logs), -20); // Last 20 lines
    echo implode("\n", $lines);
} else {
    echo "No logs found yet. Try logging in first.\n";
}

echo "</pre>";
?>
