<?php
session_start();

// Clear all session variables
session_unset();

// Destroy the session
session_destroy();

// Redirect to the homepage
echo "<script>window.location.href = 'index.php';</script>";
exit;
?>
