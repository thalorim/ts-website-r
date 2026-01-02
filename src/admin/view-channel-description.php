<?php
/**
 * View Channel Description
 * Shows the actual BBCode that will be sent to TeamSpeak channel
 */

require_once __DIR__ . "/../private/php/load.php";
require_once __DIR__ . "/../private/php/Utils/StatusDisplayManager.php";

use Wruczek\TSWebsite\Utils\StatusDisplayManager;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\CacheManager;

echo "<!DOCTYPE html><html><head><title>View Channel Description</title>";
echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css'>";
echo "<style>body{padding:20px;} pre{background:#f5f5f5;padding:15px;border:1px solid #ddd;white-space:pre-wrap;word-wrap:break-word;}</style>";
echo "</head><body><div class='container'>";

echo "<h1>View Channel Description Preview</h1>";
echo "<p class='text-muted'>Preview what will be displayed in TeamSpeak channel</p>";

// Get configurations
$manager = StatusDisplayManager::i();
$configs = $manager->getConfigurations();

if (empty($configs)) {
    echo "<div class='alert alert-warning'>No status display configurations found.</div>";
    echo "<p><a href='status-display.php'>Add a configuration</a></p>";
} else {
    foreach ($configs as $config) {
        $cldbid = (int) $config['cldbid'];
        $channelId = (int) $config['channel_id'];
        $serverGroupId = isset($config['server_group_id']) ? (int) $config['server_group_id'] : null;
        
        echo "<div class='card mb-4'>";
        echo "<div class='card-header'>";
        echo "<h4>CLDBID: {$cldbid} → Channel: {$channelId}</h4>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        // Get profile
        $db = DatabaseUtils::i()->getDb();
        $profile = $db->get("profiles", "*", ["cldbid" => $cldbid]);
        
        $nickname = "User";
        $isOnline = false;
        $clid = null;
        $uid = null;
        
        // Check if online
        try {
            $onlineClients = CacheManager::i()->getClientList();
            foreach ($onlineClients as $client) {
                if (isset($client['client_database_id']) && (int)$client['client_database_id'] === $cldbid) {
                    $nickname = (string)$client['client_nickname'];
                    $clid = (int)$client['clid'];
                    $uid = (string)$client['client_unique_identifier'];
                    $isOnline = true;
                    break;
                }
            }
        } catch (Exception $e) {
            // Ignore
        }
        
        if ($profile) {
            if (!$isOnline) {
                $nickname = $profile['nickname'] ?? 'User';
                if (!$uid && !empty($profile["cluid"])) {
                    $uid = (string) $profile["cluid"];
                }
            }
        }
        
        echo "<dl class='row'>";
        echo "<dt class='col-sm-3'>Nickname:</dt><dd class='col-sm-9'>" . htmlspecialchars($nickname) . "</dd>";
        echo "<dt class='col-sm-3'>Status:</dt><dd class='col-sm-9'>" . ($isOnline ? "<span class='badge badge-success'>ONLINE</span>" : "<span class='badge badge-danger'>OFFLINE</span>") . "</dd>";
        
        // Check for Steam
        $hasSteam = false;
        $steamUrl = null;
        if ($profile && !empty($profile['socials_json'])) {
            $socials = json_decode($profile['socials_json'], true);
            if (isset($socials['steam']) && !empty($socials['steam'])) {
                $hasSteam = true;
                $steamUrl = $socials['steam'];
                
                // Try to extract Steam ID
                $reflection = new ReflectionClass($manager);
                $method = $reflection->getMethod('extractSteamId');
                $method->setAccessible(true);
                $steamId = $method->invoke($manager, $steamUrl);
                
                echo "<dt class='col-sm-3'>Steam URL:</dt><dd class='col-sm-9'><small>" . htmlspecialchars($steamUrl) . "</small></dd>";
                echo "<dt class='col-sm-3'>Steam ID:</dt><dd class='col-sm-9'>" . ($steamId ? "<strong>{$steamId}</strong>" : "<span class='text-danger'>Not extracted</span>") . "</dd>";
            }
        }
        
        echo "</dl>";
        
        // Generate description
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('buildChannelDescription');
        $method->setAccessible(true);
        
        $description = $method->invoke($manager, $cldbid, $nickname, $isOnline, $profile, $serverGroupId, $clid, $uid);
        
        echo "<h5>Generated BBCode:</h5>";
        echo "<pre>" . htmlspecialchars($description) . "</pre>";
        
        // Check for signature in output
        if (strpos($description, 'signature.php') !== false) {
            echo "<div class='alert alert-success'>✓ Steam signature IS included in description</div>";
        } else {
            if ($hasSteam) {
                echo "<div class='alert alert-warning'>⚠ Steam URL found but signature NOT included - possible extraction issue</div>";
            } else {
                echo "<div class='alert alert-info'>ℹ No Steam profile linked for this user</div>";
            }
        }
        
        echo "</div></div>";
    }
}

echo "<hr>";
echo "<div class='btn-group'>";
echo "<a href='status-display.php' class='btn btn-secondary'>Back to Admin</a> ";
echo "<a href='test-steam-signature.php' class='btn btn-primary'>Test Steam Extraction</a>";
echo "</div>";

echo "</div></body></html>";
?>
