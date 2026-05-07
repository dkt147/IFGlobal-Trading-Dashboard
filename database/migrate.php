<?php
$conn = new mysqli('localhost', 'root', '', 'textile_commission');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Check if contract_id column already exists
$result = $conn->query("SHOW COLUMNS FROM payments LIKE 'contract_id'");
if ($result->num_rows == 0) {
    // Add the column
    if ($conn->query('ALTER TABLE payments ADD COLUMN contract_id int(11) DEFAULT NULL AFTER customer_id')) {
        echo "✓ Column contract_id added successfully" . PHP_EOL;
    } else {
        echo "✗ Error adding column: " . $conn->error . PHP_EOL;
    }
} else {
    echo "✓ Column contract_id already exists" . PHP_EOL;
}

$conn->close();
?>
