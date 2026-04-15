<?php
require_once "session_init.php";

// Simple session keep-alive endpoint (no timeout checks)
if (isset($_SESSION["userID"])) {
    $_SESSION["LAST_ACTIVITY"] = time();
    echo "OK";
} else {
    echo "NO_SESSION"; // Don't return 401 to avoid triggering logout
}
?>