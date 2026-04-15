<?php
session_start(); // Start the session to access session variables

// Check if a local user is logged in
if (isset($_SESSION['user_id'])) {
    // Clear local user session data
    session_unset();  // Unset all session variables
    session_destroy(); // Destroy the session
}

// Check if a Google user is logged in
if (isset($_SESSION['google_logged_in'])) {
    // Unset Google user session data
    unset($_SESSION['google_logged_in']);
    unset($_SESSION['google_name']);
    unset($_SESSION['google_picture']);
}

// Redirect to index page after logout
header('Location: index.php');
exit; // Ensure the script stops here
?>
