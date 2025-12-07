<?php

use Wruczek\TSWebsite\Auth;
use Medoo\Medoo;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

if (!Auth::isLoggedIn() || Auth::getCldbid() !== 3) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "Forbidden: You do not have permission to modify database settings"]);
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

    // If password is empty, try to read from existing config file
    if (empty($dbConfig['password'])) {
        if (file_exists(__CONFIG_FILE)) {
            $existingConfig = require __CONFIG_FILE;
            if (isset($existingConfig['password'])) {
                $dbConfig['password'] = $existingConfig['password'];
            }
        }
    }

    // Test database connection before saving
    try {
        $testConfig = $dbConfig;
        $testConfig['option'] = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ];
        $testDb = new Medoo($testConfig);
        
        // Try a simple query to verify connection
        $testDb->query("SELECT 1");
    } catch (\Exception $e) {
        throw new \Exception("Database connection test failed: " . $e->getMessage());
    }

    // Generate PHP config file content
    $phpCode = <<<'EOT'
<?php
/*
 * TS-website database config file
 * Last modified at %s
 */

return [
%s
];

EOT;

    $configArray = "";
    foreach ($dbConfig as $key => $value) {
        $escapedKey = addcslashes($key, '"\\');
        $escapedValue = addcslashes($value, '"\\');
        $configArray .= "    '{$escapedKey}' => '{$escapedValue}'," . PHP_EOL;
    }

    // Remove trailing comma and newline
    $configArray = rtrim($configArray, "," . PHP_EOL);

    // Format the final PHP code
    $phpCode = sprintf($phpCode, date("Y-m-d H:i:s"), $configArray);

    // Backup existing config if it exists
    if (file_exists(__CONFIG_FILE)) {
        $backupFile = __CONFIG_FILE . '.backup.' . date('YmdHis');
        if (!copy(__CONFIG_FILE, $backupFile)) {
            throw new \Exception("Failed to create backup of existing config file");
        }
    }

    // Write new config file
    if (file_put_contents(__CONFIG_FILE, $phpCode) === false) {
        throw new \Exception("Cannot write to config file. Please check file permissions for: " . __CONFIG_FILE);
    }

    // Set proper permissions (read/write for owner only)
    chmod(__CONFIG_FILE, 0600);

    echo json_encode([
        "ok" => true, 
        "message" => "Database configuration saved successfully. A backup was created if a previous config existed."
    ]);

} catch (\InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Error saving database config: " . $e->getMessage()]);
}
