<?php
/**
 * Diagnostic script for Status Display feature
 * Visit: http://web.reape.rs/admin/check-status-display.php
 */

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Status Display Feature - Diagnostic Check</h1>";
echo "<style>body{font-family:monospace;padding:20px;} .pass{color:green;} .fail{color:red;} .warn{color:orange;}</style>";

// Check 1: Can we load the framework?
echo "<h2>1. Loading TS-Website Framework</h2>";
try {
    require_once __DIR__ . "/../private/php/load.php";
    echo "<p class='pass'>✓ Framework loaded successfully</p>";
} catch (Exception $e) {
    echo "<p class='fail'>✗ Failed to load framework: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Error: Cannot continue without framework</p>";
    exit;
}

// Check 2: Check if classes exist
echo "<h2>2. Checking Required Classes</h2>";

$classes = [
    'Wruczek\TSWebsite\Auth',
    'Wruczek\TSWebsite\Config',
    'Wruczek\TSWebsite\Utils\DatabaseUtils',
    'Wruczek\TSWebsite\Utils\TemplateUtils',
    'Wruczek\TSWebsite\CacheManager',
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "<p class='pass'>✓ Class exists: $class</p>";
    } else {
        echo "<p class='fail'>✗ Class missing: $class</p>";
    }
}

// Check 3: StatusDisplayManager class
echo "<h2>3. Checking StatusDisplayManager Class</h2>";
$managerFile = __DIR__ . "/../private/php/Utils/StatusDisplayManager.php";
if (file_exists($managerFile)) {
    echo "<p class='pass'>✓ File exists: StatusDisplayManager.php</p>";
    
    try {
        require_once $managerFile;
        echo "<p class='pass'>✓ File loaded successfully</p>";
        
        if (class_exists('Wruczek\TSWebsite\Utils\StatusDisplayManager')) {
            echo "<p class='pass'>✓ Class exists: StatusDisplayManager</p>";
            
            // Try to instantiate
            try {
                $manager = Wruczek\TSWebsite\Utils\StatusDisplayManager::i();
                echo "<p class='pass'>✓ Class instantiated successfully</p>";
            } catch (Exception $e) {
                echo "<p class='fail'>✗ Cannot instantiate: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p class='fail'>✗ Class not found after loading file</p>";
        }
    } catch (Exception $e) {
        echo "<p class='fail'>✗ Error loading file: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
} else {
    echo "<p class='fail'>✗ File not found: StatusDisplayManager.php</p>";
    echo "<p>Expected location: $managerFile</p>";
}

// Check 4: Database connection
echo "<h2>4. Checking Database Connection</h2>";
try {
    $db = Wruczek\TSWebsite\Utils\DatabaseUtils::i()->getDb();
    echo "<p class='pass'>✓ Database connected</p>";
    
    // Check if table exists
    $dbConfig = Wruczek\TSWebsite\Config::i()->getDatabaseConfig();
    $prefix = isset($dbConfig["prefix"]) ? $dbConfig["prefix"] : "";
    $tableName = $prefix . "channel_status_display";
    
    $stmt = $db->query("SHOW TABLES LIKE '" . addslashes($tableName) . "'");
    $exists = $stmt && $stmt->fetchColumn();
    
    if ($exists) {
        echo "<p class='pass'>✓ Table exists: $tableName</p>";
        
        // Count records
        $count = $db->count("channel_status_display");
        echo "<p class='pass'>✓ Table has $count configuration(s)</p>";
    } else {
        echo "<p class='warn'>⚠ Table does not exist: $tableName</p>";
        echo "<p>You need to create the table. Run the SQL from INSTALL_NOW.txt</p>";
    }
} catch (Exception $e) {
    echo "<p class='fail'>✗ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check 5: Authentication
echo "<h2>5. Checking Authentication</h2>";
try {
    if (Wruczek\TSWebsite\Auth::isLoggedIn()) {
        echo "<p class='pass'>✓ You are logged in</p>";
        $cldbid = Wruczek\TSWebsite\Auth::getCldbid();
        echo "<p>Your CLDBID: $cldbid</p>";
        
        // Check admin status
        $adminGroups = Wruczek\TSWebsite\Config::get("adminstatus_groups", []);
        $userGroups = Wruczek\TSWebsite\Auth::getServerGroups();
        
        $isAdmin = false;
        foreach ($adminGroups as $adminGroup) {
            if (in_array($adminGroup, $userGroups)) {
                $isAdmin = true;
                break;
            }
        }
        
        if ($isAdmin) {
            echo "<p class='pass'>✓ You have admin permissions</p>";
        } else {
            echo "<p class='warn'>⚠ You don't have admin permissions</p>";
            echo "<p>Admin groups: " . implode(", ", $adminGroups) . "</p>";
            echo "<p>Your groups: " . implode(", ", $userGroups) . "</p>";
        }
    } else {
        echo "<p class='warn'>⚠ You are not logged in</p>";
        echo "<p>You need to log in to access the admin panel</p>";
    }
} catch (Exception $e) {
    echo "<p class='fail'>✗ Auth error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check 6: File permissions
echo "<h2>6. Checking File Permissions</h2>";
$botFile = __DIR__ . "/../private/php/status-display-bot.php";
if (file_exists($botFile)) {
    echo "<p class='pass'>✓ Bot file exists</p>";
    
    if (is_executable($botFile)) {
        echo "<p class='pass'>✓ Bot file is executable</p>";
    } else {
        echo "<p class='warn'>⚠ Bot file is not executable</p>";
        echo "<p>Run: chmod +x src/private/php/status-display-bot.php</p>";
    }
} else {
    echo "<p class='fail'>✗ Bot file not found</p>";
}

// Check 7: PHP Version
echo "<h2>7. PHP Environment</h2>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
$requiredExtensions = ['pdo', 'pdo_mysql', 'json'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p class='pass'>✓ Extension loaded: $ext</p>";
    } else {
        echo "<p class='fail'>✗ Extension missing: $ext</p>";
    }
}

// Summary
echo "<h2>Summary</h2>";
echo "<p>If all checks passed above, the status display feature should work.</p>";
echo "<p>If you see errors, please fix them and try accessing <a href='status-display.php'>status-display.php</a> again.</p>";

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Fix any errors shown above</li>";
echo "<li>If table doesn't exist, create it using SQL from INSTALL_NOW.txt</li>";
echo "<li>Make sure you're logged in as admin</li>";
echo "<li>Try accessing <a href='status-display.php'>status-display.php</a></li>";
echo "</ol>";
?>
