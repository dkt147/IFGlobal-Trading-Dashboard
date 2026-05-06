<?php
require_once 'includes/db.php';

$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE owner SET password = ? WHERE username = 'admin'");
$stmt->bind_param("s", $hash);
$result = $stmt->execute();

if ($result) {
    echo "✓ Password updated successfully!\n";
    echo "Hash: " . $hash . "\n";
    echo "\nTest verification:\n";
    echo "Password: admin123\n";
    echo "Verify: " . (password_verify($password, $hash) ? "✓ PASS" : "✗ FAIL") . "\n";
} else {
    echo "Error: " . $conn->error;
}

$stmt->close();
$conn->close();
?>
