<?php
/**
 * Database Connection Test Script
 * This script tests the database connection and displays diagnostic information
 */

// Load the bootstrap file which includes the database configuration
require_once __DIR__ . '/../bootstrap.php';

// Set content type to HTML for better formatting
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-left: 4px solid #28a745;
            margin: 10px 0;
            border-radius: 4px;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-left: 4px solid #dc3545;
            margin: 10px 0;
            border-radius: 4px;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-left: 4px solid #17a2b8;
            margin: 10px 0;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .code {
            background-color: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Database Connection Test</h1>

        <?php
        // Display configuration (hiding sensitive password)
        echo '<div class="info">';
        echo '<strong>Configuration:</strong><br>';
        echo 'Host: ' . MYSQL_HOST . '<br>';
        echo 'Database: ' . MYSQL_DBNAME . '<br>';
        echo 'User: ' . MYSQL_USER . '<br>';
        echo 'Password: ' . (MYSQL_PASSWORD ? str_repeat('*', strlen(MYSQL_PASSWORD)) : '(empty)') . '<br>';
        echo '</div>';

        try {
            // Test 1: Check if RedBeanPHP is loaded
            if (!class_exists('R')) {
                throw new Exception('RedBeanPHP (R) class not found. Check if bootstrap.php is loading correctly.');
            }

            echo '<div class="success">✓ RedBeanPHP is loaded successfully</div>';

            // Test 2: Test basic database connection
            $testConnection = R::testConnection();
            if ($testConnection) {
                echo '<div class="success">✓ Database connection is working!</div>';
            } else {
                throw new Exception('Database connection test failed');
            }

            // Test 3: Try to execute a simple query
            $dbInfo = R::getAll('SELECT VERSION() as version, DATABASE() as current_db');
            if ($dbInfo) {
                echo '<div class="success">✓ Query execution successful</div>';
                echo '<div class="info">';
                echo '<strong>Database Information:</strong><br>';
                echo 'MySQL Version: ' . $dbInfo[0]['version'] . '<br>';
                echo 'Current Database: ' . $dbInfo[0]['current_db'] . '<br>';
                echo '</div>';
            }

            // Test 4: List all tables in the database
            $allTables = R::inspect();
            echo '<h2>Database Tables</h2>';
            if (empty($allTables)) {
                echo '<div class="info">No tables found in the database. You may need to run the initialization script.</div>';
            } else {
                echo '<table>';
                echo '<tr><th>Table Name</th><th>Columns</th></tr>';

                // R::inspect() returns a simple array of table names
                foreach ($allTables as $tableName) {
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($tableName) . '</strong></td>';
                    echo '<td>';
                    try {
                        // Get column information for this table
                        $columns = R::inspect($tableName);
                        if (is_array($columns)) {
                            echo implode(', ', array_keys($columns));
                        } else {
                            echo 'N/A';
                        }
                    } catch (Exception $e) {
                        echo 'Error inspecting table';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }

            // Test 5: Check specific tables that should exist
            echo '<h2>Expected Tables Check</h2>';
            // These are the tables actually used in current codebase
            $expectedTables = ['wallet', 'userstate'];
            echo '<table>';
            echo '<tr><th>Table Name</th><th>Status</th><th>Row Count</th></tr>';

            foreach ($expectedTables as $tableName) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($tableName) . '</td>';

                if (in_array($tableName, $allTables)) {
                    try {
                        $count = R::count($tableName);
                        echo '<td><span style="color: green;">✓ Exists</span></td>';
                        echo '<td>' . $count . ' row(s)</td>';
                    } catch (Exception $e) {
                        echo '<td><span style="color: orange;">✓ Exists (count error)</span></td>';
                        echo '<td>-</td>';
                    }
                } else {
                    echo '<td><span style="color: red;">✗ Missing</span></td>';
                    echo '<td>-</td>';
                }
                echo '</tr>';
            }
            echo '</table>';

            // Inform about previously expected tables if user saw earlier version
            $legacySuggested = ['user', 'telegram_update', 'transaction'];
            $missingLegacy = array_diff($legacySuggested, $allTables);
            if ($missingLegacy) {
                echo '<div class="info">';
                echo '<strong>Note:</strong> Tables previously listed (user, telegram_update, transaction) are <em>not</em> currently used by the existing code. You can safely ignore them unless you plan to implement those features (user stats, bet tracking, transaction logging).';
                echo '</div>';
            }

            // Test 6: Check if we can write to the database using existing table (wallet)
            echo '<h2>Write Test</h2>';
            if (in_array('wallet', $allTables)) {
                try {
                    $testBean = R::dispense('wallet');
                    $testBean->telegram_user_id = -1; // sentinel test id
                    $testBean->address = 'TEST_ADDRESS';
                    $testBean->private_key = 'TEST_KEY';
                    $testBean->trx_balance = 0;
                    $testBean->usd_balance = 0;
                    $testBean->created_at = date('Y-m-d H:i:s');
                    $testBean->updated_at = date('Y-m-d H:i:s');
                    $id = R::store($testBean);

                    $retrievedBean = R::load('wallet', $id);
                    if ($retrievedBean->id) {
                        echo '<div class="success">✓ Write & read operations OK (wallet test row ID: ' . $id . ')</div>';
                        R::trash($retrievedBean);
                        echo '<div class="info">Test row cleaned up</div>';
                    } else {
                        echo '<div class="error">✗ Could not read back wallet test row</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="error">✗ Write test failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            } else {
                echo '<div class="error">✗ Cannot perform write test because wallet table is missing</div>';
            }
            // Test 7: Check PDO driver
            echo '<h2>PDO Information</h2>';
            $drivers = PDO::getAvailableDrivers();
            echo '<div class="info">';
            echo '<strong>Available PDO Drivers:</strong> ' . implode(', ', $drivers) . '<br>';
            if (in_array('mysql', $drivers)) {
                echo '<span style="color: green;">✓ MySQL driver is available</span>';
            } else {
                echo '<span style="color: red;">✗ MySQL driver is NOT available</span>';
            }
            echo '</div>';

        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>❌ Database Connection Error:</strong><br>';
            echo htmlspecialchars($e->getMessage()) . '<br><br>';
            echo '<strong>Stack Trace:</strong><br>';
            echo '<div class="code">' . nl2br(htmlspecialchars($e->getTraceAsString())) . '</div>';
            echo '</div>';

            echo '<div class="info">';
            echo '<strong>Troubleshooting Tips:</strong><br>';
            echo '1. Make sure MySQL server is running<br>';
            echo '2. Verify that the database "' . MYSQL_DBNAME . '" exists<br>';
            echo '3. Check that user "' . MYSQL_USER . '" has access to the database<br>';
            echo '4. Verify the password is correct<br>';
            echo '5. Check if the MySQL server is accessible from localhost<br>';
            echo '6. Try running the database initialization script: php scripts/init_database.php<br>';
            echo '</div>';
        }
        ?>

        <hr style="margin: 30px 0;">
        <p style="text-align: center; color: #666;">
            <small>Generated on <?php echo date('Y-m-d H:i:s'); ?></small>
        </p>
    </div>
</body>
</html>
