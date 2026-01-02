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

// Track online users (cldbid => timestamp when seen)
$onlineUsers = [];
$lastCheck = time();

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
        
        foreach ($clients as $client) {
            // Skip query clients
            if (isset($client['client_type']) && $client['client_type'] == 1) {
                continue;
            }
            
            $cldbid = (int) $client['client_database_id'];
            $nickname = (string) $client['client_nickname'];
            $cluid = isset($client['client_unique_identifier']) ? (string) $client['client_unique_identifier'] : '';
            
            $currentOnline[$cldbid] = true;
            
            // Check if this is a new connection
            if (!isset($onlineUsers[$cldbid])) {
                // New user connected
                $manager->recordConnection($cldbid, $nickname, $cluid);
                $onlineUsers[$cldbid] = $currentTime;
                $newConnections++;
                echo "  [NEW] {$nickname} (cldbid: {$cldbid}) connected\n";
            } else {
                // User was already online, add time
                $manager->addOnlineTime($cldbid, $timeDiff);
                $onlineUsers[$cldbid] = $currentTime;
                $timeUpdates++;
            }
        }
        
        // Remove disconnected users
        $disconnected = 0;
        foreach ($onlineUsers as $cldbid => $timestamp) {
            if (!isset($currentOnline[$cldbid])) {
                unset($onlineUsers[$cldbid]);
                $disconnected++;
                echo "  [LEFT] User {$cldbid} disconnected\n";
            }
        }
        
        echo "  Summary: " . count($onlineUsers) . " online, ";
        echo "{$newConnections} new connections, {$disconnected} left\n";
        echo "  Tracking data updated for {$timeUpdates} users\n";
        
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
