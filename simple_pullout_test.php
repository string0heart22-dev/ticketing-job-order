<?php
// Super simple test to see what's failing
header('Content-Type: text/plain');

echo "=== PULLOUT DIAGNOSTIC TEST ===\n\n";

// Test 1: Basic PHP
echo "1. PHP is working: YES\n";

// Test 2: POST data
echo "2. POST method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . "\n";
echo "3. POST data received: " . (isset($_POST['pullout_id']) ? $_POST['pullout_id'] : 'NONE') . "\n";

// Test 3: Database connection
echo "4. Testing database connection...\n";
try {
    $conn = new mysqli("localhost", "root", "", "user_db");
    if ($conn->connect_error) {
        echo "   Database connection FAILED: " . $conn->connect_error . "\n";
    } else {
        echo "   Database connection: SUCCESS\n";
        
        // Test 4: Check if table exists
        echo "5. Checking pullout_tickets table...\n";
        $result = $conn->query("SHOW TABLES LIKE 'pullout_tickets'");
        if ($result && $result->num_rows > 0) {
            echo "   Table exists: YES\n";
            
            // Test 5: Check table structure
            echo "6. Checking table structure...\n";
            $columns = $conn->query("DESCRIBE pullout_tickets");
            if ($columns) {
                echo "   Table columns:\n";
                while ($row = $columns->fetch_assoc()) {
                    echo "   - " . $row['Field'] . " (" . $row['Type'] . ")\n";
                }
            }
            
            // Test 6: Check if we can select from table
            echo "7. Testing SELECT query...\n";
            $test_select = $conn->query("SELECT COUNT(*) as count FROM pullout_tickets");
            if ($test_select) {
                $count = $test_select->fetch_assoc()['count'];
                echo "   Total records: " . $count . "\n";
            } else {
                echo "   SELECT failed: " . $conn->error . "\n";
            }
            
        } else {
            echo "   Table exists: NO - THIS IS THE PROBLEM!\n";
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "   Database test FAILED: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>