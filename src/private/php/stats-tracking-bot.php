<?php

/**
 * Stats Tracking Bot
 * 
 * This bot monitors TeamSpeak server and tracks:
 * - User connections
 * - Online time
 * - User activity
 * 
 * Usage:
 *   php stats-tracking-bot.php --once             Run once and exit
 *   php stats-tracking-bot.php --daemon           Run continuously
 *   php stats-tracking-bot.php --daemon --interval=60  Run with custom interval
 */

use Wruczek\TSWebsite\Utils\StatsDisplayManager;
use Wruczek\TSWebsite\CacheManager;

require_once __DIR__ . "/load.php";

// Parse command line arguments
$options = getopt("", ["once", "daemon", "interval::"]);

$runOnce = isset($options["once"]);
$runDaemon = isset($options["daemon"]);
$interval = isset($options["interval"]) ? (int) $options["interval"] : 60; // Default 60 seconds

if (!$runOnce && !$runDaemon) {
    echo "Stats Tracking Bot - Track user connections and online time\n";
    echo "\n";
    echo "Usage:\n";
    echo "  php stats-tracking-bot.php --once             Run once and exit\n";
    echo "  php stats-tracking-bot.php --daemon           Run continuously (60s interval)\n";
    echo "  php stats-tracking-bot.php --daemon --interval=30  Run with custom interval\n";
    echo "\n";
    exit(1);
}

// Validate interval
if ($interval < 10) {
    echo "Error: Interval must be at least 10 seconds\n";
    exit(1);
}

echo "=================================================\n";
echo "Stats Tracking Bot\n";
echo "=================================================\n";
echo "Mode: " . ($runOnce ? "Single run" : "Daemon mode") . "\n";
if ($runDaemon) {
    echo "Interval: {$interval} seconds\n";
}
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "=================================================\n\n";

$manager = StatsDisplayManager::i();
$manager->ensureTablesExist();

// Track online users (cldbid => array with start time and nickname)
$onlineUsers = [];
$lastCheck = time();

// Import existing connections on first run
echo "[" . date('Y-m-d H:i:s') . "] Importing existing connections from database...\n";
try {
    $db = \Wruczek\TSWebsite\Utils\DatabaseUtils::i()->getDb();
    $imported = 0;
    
    // Check if profiles table exists and has connection data
    try {
        $profiles = $db->select("profiles", ["cldbid", "totalconnections", "nickname"], ["totalconnections[>]" => 0]);
        foreach ($profiles as $profile) {
            $cldbid = (int)$profile['cldbid'];
            
            // Check if user already in stats
            $existing = $db->get("user_statistics", "cldbid", ["cldbid" => $cldbid]);
            
            if (!$existing && isset($profile['totalconnections']) && $profile['totalconnections'] > 0) {
                // User not in stats yet, but has connections in profiles
                $db->insert("user_statistics", [
                    "cldbid" => $cldbid,
                    "last_nickname" => $profile['nickname'] ?? 'Unknown',
                    "total_connections" => (int)$profile['totalconnections'],
                    "total_online_time" => 0,
                    "first_seen" => date('Y-m-d H:i:s'),
                    "last_seen" => date('Y-m-d H:i:s')
                ]);
                $imported++;
            }
        }
    } catch (\Exception $e) {
        // Profiles table might not exist
    }
    
    if ($imported > 0) {
        echo "  Imported {$imported} users with existing connection data\n";
    } else {
        echo "  No existing connections to import\n";
    }
} catch (\Exception $e) {
    echo "  Error importing: " . $e->getMessage() . "\n";
}
echo "\n";

/**
 * Process user tracking
 */
function processUserTracking() {
    global $manager, $onlineUsers, $lastCheck;
    
    $currentTime = time();
    $timeDiff = $currentTime - $lastCheck;
    
    echo "[" . date('Y-m-d H:i:s') . "] Processing user tracking...\n";
    
    try {
        // Get currently online clients
        $clients = CacheManager::i()->getClientList();
        $currentOnline = [];
        $newConnections = 0;
        $timeUpdates = 0;
        $totalOnline = count($clients);
        
        foreach ($clients as $client) {
            // Skip query clients
            if (isset($client['client_type']) && $client['client_type'] == 1) {
                $totalOnline--;
                continue;
            }
            
            $cldbid = (int) $client['client_database_id'];
            $nickname = (string) $client['client_nickname'];
            $cluid = isset($client['client_unique_identifier']) ? (string) $client['client_unique_identifier'] : '';
            
            $currentOnline[$cldbid] = true;
            
            // Collect additional data
            $additionalData = [];
            
            // Get IP address if available
            if (isset($client['connection_client_ip'])) {
                $additionalData['last_ip'] = (string) $client['connection_client_ip'];
            }
            
            // Get country if available
            if (isset($client['client_country'])) {
                $additionalData['country_code'] = (string) $client['client_country'];
            }
            
            // Track peak online count
            $additionalData['peak_clients_seen'] = $totalOnline;
            
            // Check if this is a new connection
            if (!isset($onlineUsers[$cldbid])) {
                // New user connected
                $manager->recordConnection($cldbid, $nickname, $cluid);
                $manager->startSession($cldbid);
                $onlineUsers[$cldbid] = [
                    'start' => $currentTime,
                    'nickname' => $nickname
                ];
                $newConnections++;
                echo "  [NEW] {$nickname} (cldbid: {$cldbid}) connected\n";
            } else {
                // User was already online, add time
                $manager->addOnlineTime($cldbid, $timeDiff, $additionalData);
                $onlineUsers[$cldbid]['nickname'] = $nickname;
                $timeUpdates++;
            }
        }
        
        // Remove disconnected users
        $disconnected = 0;
        foreach ($onlineUsers as $cldbid => $userData) {
            if (!isset($currentOnline[$cldbid])) {
                // Calculate session length
                $sessionLength = $currentTime - $userData['start'];
                $manager->endSession($cldbid);
                
                unset($onlineUsers[$cldbid]);
                $disconnected++;
                $sessionTime = floor($sessionLength / 60);
                echo "  [LEFT] {$userData['nickname']} (cldbid: {$cldbid}) disconnected after {$sessionTime}m\n";
            }
        }
        
        echo "  Summary: " . count($onlineUsers) . " online ({$totalOnline} total clients), ";
        echo "{$newConnections} new, {$disconnected} left, {$timeUpdates} updated\n";
        
        $lastCheck = $currentTime;
        
    } catch (\Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
        error_log("Stats tracking bot error: " . $e->getMessage());
    }
    
    echo "\n";
}

/**
 * Update stats display channels
 */
function updateStatsChannels() {
    global $manager;
    
    echo "[" . date('Y-m-d H:i:s') . "] Updating stats display channels...\n";
    
    try {
        $stats = $manager->processAllStatsUpdates();
        
        echo "  Channels updated: {$stats['updated']}\n";
        echo "  Failed: {$stats['failed']}\n";
        echo "  Total: {$stats['total']}\n";
        
        if ($stats['failed'] > 0) {
            echo "  WARNING: Some updates failed\n";
        }
        
    } catch (\Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
        error_log("Stats channel update error: " . $e->getMessage());
    }
    
    echo "\n";
}

// Signal handlers for graceful shutdown
if (function_exists('pcntl_signal')) {
    declare(ticks = 1);
    
    $running = true;
    
    pcntl_signal(SIGTERM, function() use (&$running) {
        global $running;
        echo "\n[" . date('Y-m-d H:i:s') . "] Received SIGTERM, shutting down...\n";
        $running = false;
    });
    
    pcntl_signal(SIGINT, function() use (&$running) {
        global $running;
        echo "\n[" . date('Y-m-d H:i:s') . "] Received SIGINT (Ctrl+C), shutting down...\n";
        $running = false;
    });
} else {
    $running = true;
}

// Main loop
if ($runOnce) {
    // Run once and exit
    processUserTracking();
    updateStatsChannels();
    echo "[" . date('Y-m-d H:i:s') . "] Single run completed. Exiting.\n";
    exit(0);
}

// Daemon mode
echo "[" . date('Y-m-d H:i:s') . "] Bot is running. Press Ctrl+C to stop.\n\n";

$iterationCount = 0;
$channelUpdateInterval = 300; // Update channels every 5 minutes
$lastChannelUpdate = 0;

while ($running) {
    $iterationCount++;
    
    // Always track users
    processUserTracking();
    
    // Update channels periodically (every 5 minutes)
    if (time() - $lastChannelUpdate >= $channelUpdateInterval) {
        updateStatsChannels();
        $lastChannelUpdate = time();
    }
    
    // Show status every 10 iterations
    if ($iterationCount % 10 === 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Bot running (iteration #{$iterationCount})\n";
        echo "  Currently tracking: " . count($onlineUsers) . " users\n\n";
    }
    
    // Sleep for the specified interval
    sleep($interval);
}

echo "[" . date('Y-m-d H:i:s') . "] Bot stopped.\n";
exit(0);
