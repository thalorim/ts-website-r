<?php
/**
 * Check Social Media Icons
 * Verifies all social media icon files are present and accessible
 */

echo "<!DOCTYPE html><html><head><title>Social Media Icons Check</title>";
echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css'>";
echo "<style>body{padding:20px;} .icon-preview{width:64px;height:64px;object-fit:contain;} .pass{color:green;} .fail{color:red;}</style>";
echo "</head><body><div class='container'>";

echo "<h1>Social Media Icons Check</h1>";
echo "<p class='text-muted'>Checking if all social media icon files exist and are accessible...</p>";

$baseDir = __DIR__ . "/..";
$iconsDir = $baseDir . "/img/icons";
$baseUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

// Get base path
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
$basePath = '';

if (!empty($documentRoot) && !empty($scriptFilename)) {
    $relativePath = str_replace($documentRoot, '', dirname(dirname($scriptFilename)));
    if (strpos($relativePath, '/admin') !== false) {
        $parts = explode('/admin', $relativePath);
        $basePath = $parts[0];
    }
}

$baseUrl = $baseUrl . $basePath;

$requiredIcons = [
    'Instagram' => 'ts3-instagram.png',
    'Facebook' => 'ts3-facebook.png',
    'YouTube' => 'ts3-youtube.png',
    'Twitter' => 'ts3-twitter.png',
    'Steam' => 'ts3-steam.png',
    'SoundCloud' => 'ts3-soundcloud.png',
    'GitHub' => 'ts3-github.png',
    'Telegram' => 'ts3-telegram.png',
    'Twitch' => 'ts3-twitch.png',
    'Discord' => 'ts3-discord.png',
];

echo "<h2>Icon Files Check</h2>";
echo "<table class='table table-bordered'>";
echo "<thead class='thead-dark'>";
echo "<tr><th>Platform</th><th>Filename</th><th>Status</th><th>Preview</th><th>URL</th></tr>";
echo "</thead><tbody>";

$allPresent = true;

foreach ($requiredIcons as $platform => $filename) {
    $filePath = $iconsDir . '/' . $filename;
    $fileExists = file_exists($filePath);
    $fileUrl = $baseUrl . '/img/icons/' . $filename;
    
    echo "<tr>";
    echo "<td><strong>{$platform}</strong></td>";
    echo "<td><code>{$filename}</code></td>";
    
    if ($fileExists) {
        $fileSize = filesize($filePath);
        $readable = is_readable($filePath);
        
        echo "<td class='pass'>✓ EXISTS (" . number_format($fileSize/1024, 1) . " KB)</td>";
        echo "<td>";
        if ($readable) {
            echo "<img src='{$fileUrl}' class='icon-preview' alt='{$platform}' onerror='this.style.border=\"2px solid red\"'>";
        } else {
            echo "<span class='fail'>Not readable</span>";
        }
        echo "</td>";
        echo "<td><small><a href='{$fileUrl}' target='_blank'>{$fileUrl}</a></small></td>";
    } else {
        echo "<td class='fail'>✗ MISSING</td>";
        echo "<td>-</td>";
        echo "<td><small class='text-muted'>{$fileUrl}</small></td>";
        $allPresent = false;
    }
    
    echo "</tr>";
}

echo "</tbody></table>";

if ($allPresent) {
    echo "<div class='alert alert-success'>";
    echo "<h4>✓ All Icons Present!</h4>";
    echo "<p>All social media icon files are present and should display correctly.</p>";
    echo "<p><strong>Next step:</strong> Run the bot to update channel descriptions.</p>";
    echo "<code>php src/private/php/status-display-bot.php --once</code>";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<h4>✗ Missing Icons</h4>";
    echo "<p>Some icon files are missing. Upload them to complete the setup.</p>";
    echo "<h5>Upload Instructions:</h5>";
    echo "<pre>";
    echo "# Navigate to icons directory\n";
    echo "cd " . realpath($iconsDir) . "\n\n";
    echo "# Upload your icon files (via SCP, FTP, or direct copy)\n";
    echo "# Make sure files are named exactly as shown above\n\n";
    echo "# Set proper permissions\n";
    echo "chmod 644 ts3-*.png\n";
    echo "chown www-data:www-data ts3-*.png\n";
    echo "</pre>";
    echo "</div>";
}

echo "<h2>Directory Information</h2>";
echo "<dl class='row'>";
echo "<dt class='col-sm-3'>Icons Directory:</dt>";
echo "<dd class='col-sm-9'><code>" . realpath($iconsDir) . "</code></dd>";
echo "<dt class='col-sm-3'>Directory Exists:</dt>";
echo "<dd class='col-sm-9'>" . (is_dir($iconsDir) ? "<span class='pass'>Yes</span>" : "<span class='fail'>No</span>") . "</dd>";
echo "<dt class='col-sm-3'>Directory Writable:</dt>";
echo "<dd class='col-sm-9'>" . (is_writable($iconsDir) ? "<span class='pass'>Yes</span>" : "<span class='fail'>No</span>") . "</dd>";
echo "<dt class='col-sm-3'>Base URL:</dt>";
echo "<dd class='col-sm-9'><code>{$baseUrl}</code></dd>";
echo "</dl>";

echo "<h2>Example BBCode Output</h2>";
echo "<p>When icons are present, the channel description will use:</p>";
echo "<pre>";
echo "[size=10][b]Social Media:[/b][/size]\n";
echo "[url=https://instagram.com/user][img]{$baseUrl}/img/icons/ts3-instagram.png[/img][/url] ";
echo "[url=https://twitter.com/user][img]{$baseUrl}/img/icons/ts3-twitter.png[/img][/url]\n";
echo "</pre>";

echo "<hr>";
echo "<a href='status-display.php' class='btn btn-primary'>Back to Status Display Admin</a> ";
echo "<a href='test-base-url.php' class='btn btn-secondary'>Test Base URL</a>";

echo "</div></body></html>";
?>
