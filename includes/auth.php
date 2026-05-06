<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (!isset($_SESSION['owner_id'])) {
        header('Location: /index.php');
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['owner_id']);
}

function getOwner() {
    return $_SESSION['owner'] ?? null;
}
?>
