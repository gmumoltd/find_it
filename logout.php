<?php
// Destroy the session completely and send the user back to the homepage.
session_start();
session_destroy();
header("Location: index.php");
exit();
