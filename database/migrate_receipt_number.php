<?php
$conn = new mysqli('localhost', 'root', '', 'textile_commission');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Check if receipt_number column already exists
$result = $conn->query("SHOW COLUMNS FROM payments LIKE 'receipt_number'");
if ($result->num_rows == 0) {
    // Add the column
    if ($conn->query('ALTER TABLE payments ADD COLUMN receipt_number VARCHAR(100) DEFAULT NULL AFTER payment_type')) {
        echo "✓ Column receipt_number added successfully" . PHP_EOL;
    } else {
        echo "✗ Error adding column: " . $conn->error . PHP_EOL;
    }
} else {
    echo "✓ Column receipt_number already exists" . PHP_EOL;
}

$conn->close();
?>
