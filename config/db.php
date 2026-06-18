<?php
// =====================================================================
// Database connection.
// Same pattern as connect.php in the school system project — change
// these 4 values to match your own MySQL setup (XAMPP/WAMP default is
// "root" with an empty password).
// =====================================================================

$conn = mysqli_connect(
    "localhost",        // host
    "root",              // username
    "",                  // password
    "lost_and_found_db"  // database name (import database/schema.sql first)
);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

// Make sure emojis / special characters in messages and descriptions
// are stored correctly.
mysqli_set_charset($conn, "utf8mb4");
