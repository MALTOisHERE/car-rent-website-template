<?php
// Start the session
session_start();

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to the homepage or login page
header("Location: index.php"); // Change "index.php" to your desired destination
exit(); // Ensure no further code is executed
?>