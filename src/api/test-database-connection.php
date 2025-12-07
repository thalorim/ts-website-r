<?php

use Wruczek\TSWebsite\Auth;
use Medoo\Medoo;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

if (!Auth::isLoggedIn() || Auth::getCldbid() !== 3) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "Forbidden"]);
    exit;
}

try {
    // Collect database configuration from POST
    $dbConfig = [
        'database_type' => trim($_POST['database_type'] ?? 'mysql'),
        'server' => trim($_POST['server'] ?? ''),
        'username' => trim($_POST['username'] ?? ''),
        'password' => trim($_POST['password'] ?? ''),
        'database_name' => trim($_POST['database_name'] ?? ''),
        'prefix' => trim($_POST['prefix'] ?? 'tsw_'),
        'port' => (int)($_POST['port'] ?? 3306),
        'charset' => trim($_POST['charset'] ?? 'utf8mb4')
    ];

    // Validate required fields
    if (empty($dbConfig['server'])) {
        throw new \InvalidArgumentException("Database server/hostname is required");
    }
    if (empty($dbConfig['username'])) {
        throw new \InvalidArgumentException("Database username is required");
    }
    if (empty($dbConfig['database_name'])) {
        throw new \InvalidArgumentException("Database name is required");
    }

    // If password is empty and testing, try to use existing password from config
    if (empty($dbConfig['password'])) {
        if (file_exists(__CONFIG_FILE)) {
            $existingConfig = require __CONFIG_FILE;
            if (isset($existingConfig['password'])) {
                $dbConfig['password'] = $existingConfig['password'];
            }
        }
    }

    // Test database connection
    $testConfig = $dbConfig;
    $testConfig['option'] = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5, // 5 second timeout
    ];
    
    $testDb = new Medoo($testConfig);
    
    // Try a simple query to verify connection
    $result = $testDb->query("SELECT 1 as test")->fetchAll();
    
    // Check if we can access tables with the prefix
    $tables = $testDb->query("SHOW TABLES LIKE '{$dbConfig['prefix']}%'")->fetchAll();
    $tableCount = count($tables);

    echo json_encode([
        "ok" => true, 
        "message" => "Connection successful!",
        "details" => [
            "database" => $dbConfig['database_name'],
            "server" => $dbConfig['server'],
            "tables_found" => $tableCount,
            "prefix" => $dbConfig['prefix']
        ]
    ]);

} catch (\PDOException $e) {
    http_response_code(400);
    $errorMsg = "Database connection failed: " . $e->getMessage();
    
    // Provide helpful hints for common errors
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        $errorMsg .= " - Please check your username and password.";
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        $errorMsg .= " - The database does not exist. Please create it first.";
    } elseif (strpos($e->getMessage(), "Can't connect") !== false || strpos($e->getMessage(), 'Connection refused') !== false) {
        $errorMsg .= " - Cannot reach the database server. Check hostname and port.";
    }
    
    echo json_encode(["ok" => false, "error" => $errorMsg]);
} catch (\InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Error testing connection: " . $e->getMessage()]);
}
