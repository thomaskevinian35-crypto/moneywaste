<?php
// check_database.php
$host = '127.0.0.1';
$username = 'root';
$password = '';

try {
    // Connect without specifying a database
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Available Databases on 127.0.0.1</h2>";
    
    // Get list of databases
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<ul>";
    foreach ($databases as $db) {
        echo "<li>$db</li>";
    }
    echo "</ul>";
    
    // Check what's in the databases that look similar
    $possibleDbs = ['moneystate', 'moneywaste', 'moneyswaste'];
    
    foreach ($possibleDbs as $db) {
        echo "<h3>Checking database: $db</h3>";
        try {
            $pdo2 = new PDO("mysql:host=$host;dbname=$db", $username, $password);
            $pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Try to get tables
            $stmt2 = $pdo2->query("SHOW TABLES");
            $tables = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($tables) > 0) {
                echo "Tables in $db: ";
                echo "<ul>";
                foreach ($tables as $table) {
                    echo "<li>$table</li>";
                }
                echo "</ul>";
                
                // Check users table structure
                if (in_array('users', $tables)) {
                    echo "Structure of 'users' table:<br>";
                    $stmt3 = $pdo2->query("DESCRIBE users");
                    $columns = $stmt3->fetchAll();
                    
                    echo "<table border='1' cellpadding='5'>";
                    foreach ($columns as $col) {
                        echo "<tr>";
                        echo "<td><strong>{$col['Field']}</strong></td>";
                        echo "<td>{$col['Type']}</td>";
                        echo "<td>{$col['Null']}</td>";
                        echo "<td>{$col['Key']}</td>";
                        echo "<td>{$col['Default']}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
            } else {
                echo "No tables found in $db<br>";
            }
            
        } catch (PDOException $e) {
            echo "Cannot access $db: " . $e->getMessage() . "<br>";
        }
    }
    
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>