<?php
/**
 * Test Base URL Generation
 * This script tests if the base URL is being generated correctly
 */

require_once __DIR__ . "/../private/php/load.php";
require_once __DIR__ . "/../private/php/Utils/StatusDisplayManager.php";

use Wruczek\TSWebsite\Utils\StatusDisplayManager;

echo "<h1>Base URL Test</h1>";
echo "<style>body{font-family:monospace;padding:20px;} .pass{color:green;} .fail{color:red;}</style>";

// Get the manager instance
$manager = StatusDisplayManager::i();

// Use reflection to access private method
$reflection = new ReflectionClass($manager);
$method = $reflection->getMethod('getBaseUrl');
$method->setAccessible(true);
$baseUrl = $method->invoke($manager);

echo "<h2>Base URL Generated:</h2>";
echo "<p style='font-size:20px;'><strong>" . htmlspecialchars($baseUrl) . "</strong></p>";

echo "<h2>Expected URLs:</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr><th>Type</th><th>Generated URL</th><th>Status</th></tr>";

// Test profile URL
$profileUrl = $baseUrl . "/profile.php?cldbid=116527";
$expectedProfile = "http://web.reape.rs/profile.php?cldbid=116527";
$profileMatch = ($profileUrl === $expectedProfile || $profileUrl === str_replace('http://', 'https://', $expectedProfile));

echo "<tr>";
echo "<td>Profile URL</td>";
echo "<td>" . htmlspecialchars($profileUrl) . "</td>";
echo "<td class='" . ($profileMatch ? "pass" : "fail") . "'>" . ($profileMatch ? "✓ PASS" : "✗ FAIL") . "</td>";
echo "</tr>";

// Test avatar URL
$avatarUrl = $baseUrl . "/img/avatars/116527_1766940753.webp";
$expectedAvatar = "http://web.reape.rs/img/avatars/116527_1766940753.webp";
$avatarMatch = ($avatarUrl === $expectedAvatar || $avatarUrl === str_replace('http://', 'https://', $expectedAvatar));

echo "<tr>";
echo "<td>Avatar URL</td>";
echo "<td>" . htmlspecialchars($avatarUrl) . "</td>";
echo "<td class='" . ($avatarMatch ? "pass" : "fail") . "'>" . ($avatarMatch ? "✓ PASS" : "✗ FAIL") . "</td>";
echo "</tr>";

echo "</table>";

echo "<h2>Server Variables:</h2>";
echo "<pre>";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "\n";
echo "HTTPS: " . ($_SERVER['HTTPS'] ?? 'not set') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'not set') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'not set') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set') . "\n";
echo "</pre>";

if ($profileMatch && $avatarMatch) {
    echo "<h2 class='pass'>✓ All Tests Passed!</h2>";
    echo "<p>The base URL is now correct. Run the bot to update the channel.</p>";
} else {
    echo "<h2 class='fail'>✗ Some Tests Failed</h2>";
    echo "<p>The base URL needs adjustment. Expected: <code>http://web.reape.rs</code></p>";
}

echo "<hr>";
echo "<a href='status-display.php'>Back to Status Display Admin</a>";
?>
