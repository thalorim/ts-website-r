<?php
/**
 * Test Steam Signature Extraction
 * This script tests if Steam IDs are being extracted correctly from user profiles
 */

require_once __DIR__ . "/../private/php/load.php";
require_once __DIR__ . "/../private/php/Utils/StatusDisplayManager.php";

use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\StatusDisplayManager;

echo "<!DOCTYPE html><html><head><title>Steam Signature Test</title>";
echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css'>";
echo "<style>body{padding:20px;font-family:monospace;} .pass{color:green;} .fail{color:red;} pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;}</style>";
echo "</head><body><div class='container'>";

echo "<h1>Steam Signature Test</h1>";
echo "<p class='text-muted'>Testing Steam ID extraction from user profiles</p>";

// Get all profiles with social media
$db = DatabaseUtils::i()->getDb();

try {
    $profiles = $db->select("profiles", ["cldbid", "nickname", "socials_json"], [
        "socials_json[!]" => null
    ]);
    
    echo "<h2>Found " . count($profiles) . " profiles with social media</h2>";
    
    if (empty($profiles)) {
        echo "<div class='alert alert-warning'>No profiles found with social media links.</div>";
        echo "<p>Add a Steam profile link via: <a href='../edit-profile.php'>Edit Profile</a></p>";
    } else {
        // Use reflection to access private extractSteamId method
        $manager = StatusDisplayManager::i();
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('extractSteamId');
        $method->setAccessible(true);
        
        echo "<table class='table table-bordered'>";
        echo "<thead class='thead-dark'>";
        echo "<tr><th>CLDBID</th><th>Nickname</th><th>Steam URL</th><th>Extracted Steam ID</th><th>Signature URL</th><th>Status</th></tr>";
        echo "</thead><tbody>";
        
        foreach ($profiles as $profile) {
            $cldbid = (int) $profile['cldbid'];
            $nickname = htmlspecialchars($profile['nickname'] ?? 'Unknown');
            $socials = json_decode($profile['socials_json'], true);
            
            $hasSteam = isset($socials['steam']) && !empty($socials['steam']);
            
            if ($hasSteam) {
                $steamUrl = $socials['steam'];
                $steamId = $method->invoke($manager, $steamUrl);
                
                echo "<tr>";
                echo "<td>{$cldbid}</td>";
                echo "<td>{$nickname}</td>";
                echo "<td><small>" . htmlspecialchars($steamUrl) . "</small></td>";
                
                if ($steamId) {
                    $signatureUrl = "https://www.reape.rs/signature/signature.php?steamid={$steamId}";
                    echo "<td class='pass'><strong>{$steamId}</strong></td>";
                    echo "<td><small><a href='{$signatureUrl}' target='_blank'>{$signatureUrl}</a></small></td>";
                    echo "<td class='pass'>✓ WILL DISPLAY</td>";
                } else {
                    echo "<td class='fail'>Not extracted</td>";
                    echo "<td>-</td>";
                    echo "<td class='fail'>✗ WON'T DISPLAY</td>";
                }
                
                echo "</tr>";
            }
        }
        
        echo "</tbody></table>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<h2>Test Steam ID Extraction</h2>";
echo "<p>Enter a Steam URL to test extraction:</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_url'])) {
    $testUrl = $_POST['test_url'];
    
    $manager = StatusDisplayManager::i();
    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('extractSteamId');
    $method->setAccessible(true);
    
    $extractedId = $method->invoke($manager, $testUrl);
    
    echo "<div class='card mb-3'>";
    echo "<div class='card-body'>";
    echo "<h5>Test Results:</h5>";
    echo "<dl class='row'>";
    echo "<dt class='col-sm-3'>Input URL:</dt>";
    echo "<dd class='col-sm-9'><code>" . htmlspecialchars($testUrl) . "</code></dd>";
    echo "<dt class='col-sm-3'>Extracted ID:</dt>";
    
    if ($extractedId) {
        echo "<dd class='col-sm-9 pass'><strong>{$extractedId}</strong> ✓</dd>";
        
        $signatureUrl = "https://www.reape.rs/signature/signature.php?steamid={$extractedId}";
        echo "<dt class='col-sm-3'>Signature URL:</dt>";
        echo "<dd class='col-sm-9'><a href='{$signatureUrl}' target='_blank'>{$signatureUrl}</a></dd>";
        
        echo "<dt class='col-sm-3'>BBCode:</dt>";
        echo "<dd class='col-sm-9'><pre>[url={$testUrl}][img]{$signatureUrl}[/img][/url]</pre></dd>";
    } else {
        echo "<dd class='col-sm-9 fail'><strong>Failed to extract</strong> ✗</dd>";
        echo "<dt class='col-sm-3'>Reason:</dt>";
        echo "<dd class='col-sm-9'>URL format not recognized. Use format like: https://steamcommunity.com/profiles/76561198399534638</dd>";
    }
    
    echo "</dl>";
    echo "</div></div>";
}

?>

<form method="POST" class="mb-4">
    <div class="input-group">
        <input type="text" name="test_url" class="form-control" placeholder="https://steamcommunity.com/profiles/76561198399534638" value="<?= isset($_POST['test_url']) ? htmlspecialchars($_POST['test_url']) : '' ?>">
        <div class="input-group-append">
            <button type="submit" class="btn btn-primary">Test Extract</button>
        </div>
    </div>
    <small class="form-text text-muted">Example: https://steamcommunity.com/profiles/76561198399534638</small>
</form>

<h2>Supported URL Formats</h2>
<table class="table table-sm">
    <thead>
        <tr><th>Format</th><th>Example</th><th>Status</th></tr>
    </thead>
    <tbody>
        <tr>
            <td>Direct Profile</td>
            <td><code>https://steamcommunity.com/profiles/76561198399534638</code></td>
            <td class="pass">✓ Supported</td>
        </tr>
        <tr>
            <td>Without HTTPS</td>
            <td><code>steamcommunity.com/profiles/76561198399534638</code></td>
            <td class="pass">✓ Supported</td>
        </tr>
        <tr>
            <td>With Parameter</td>
            <td><code>https://example.com/steam?steamid=76561198399534638</code></td>
            <td class="pass">✓ Supported</td>
        </tr>
        <tr>
            <td>Custom URL</td>
            <td><code>https://steamcommunity.com/id/username</code></td>
            <td class="fail">✗ Not Supported (Requires API)</td>
        </tr>
    </tbody>
</table>

<h2>Debugging Steps</h2>
<ol>
    <li><strong>Check if user has Steam profile</strong> - View above table</li>
    <li><strong>Verify Steam ID extraction</strong> - Use test form above</li>
    <li><strong>Test signature URL</strong> - Click signature URL link to verify it works</li>
    <li><strong>Run bot</strong> - <code>php src/private/php/status-display-bot.php --once</code></li>
    <li><strong>Check bot output</strong> - Look for any errors</li>
    <li><strong>View channel description source</strong> - Check if BBCode is present</li>
</ol>

<hr>
<a href="status-display.php" class="btn btn-secondary">Back to Status Display Admin</a>

</div></body></html>
<?php
