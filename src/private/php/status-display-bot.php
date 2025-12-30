#!/usr/bin/env php
<?php

/**
 * TeamSpeak Status Display Bot
 * 
 * This script monitors TeamSpeak server for client connect/disconnect events
 * and updates channel descriptions based on configurations in the database.
 * 
 * Usage:
 *   php status-display-bot.php [--daemon] [--interval=SECONDS]
 * 
 * Options:
 *   --daemon          Run continuously as a daemon
 *   --interval=N      Update interval in seconds (default: 30)
 *   --once            Run once and exit (default if --daemon not specified)
 * 
 * Examples:
 *   php status-display-bot.php --once
 *   php status-display-bot.php --daemon --interval=15
 *   
 * To run as a background service:
 *   nohup php status-display-bot.php --daemon > /dev/null 2>&1 &
 */

// Load the website framework
require_once __DIR__ . '/load.php';

use Wruczek\TSWebsite\Utils\StatusDisplayManager;
use Wruczek\TSWebsite\CacheManager;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;

// Parse command line arguments
$options = getopt('', ['daemon', 'once', 'interval::', 'help']);

if (isset($options['help'])) {
    echo "TeamSpeak Status Display Bot\n";
    echo "Usage: php status-display-bot.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --daemon           Run continuously as a daemon\n";
    echo "  --once             Run once and exit (default)\n";
    echo "  --interval=N       Update interval in seconds (default: 30)\n";
    echo "  --help             Show this help message\n\n";
    echo "Examples:\n";
    echo "  php status-display-bot.php --once\n";
    echo "  php status-display-bot.php --daemon --interval=15\n";
    exit(0);
}

$isDaemon = isset($options['daemon']);
$interval = isset($options['interval']) ? (int) $options['interval'] : 30;

if ($interval < 5) {
    echo "Warning: Interval less than 5 seconds may cause excessive server load. Setting to 5 seconds.\n";
    $interval = 5;
}

// Ensure the status display table exists
$manager = StatusDisplayManager::i();
if (!$manager->ensureTableExists()) {
    echo "Error: Failed to ensure database table exists.\n";
    exit(1);
}

echo "TeamSpeak Status Display Bot started\n";
echo "Mode: " . ($isDaemon ? "Daemon (continuous)" : "Once") . "\n";
if ($isDaemon) {
    echo "Update interval: {$interval} seconds\n";
}
echo "---\n\n";

// Track previous states to detect changes
$previousStates = [];

/**
 * Main update function
 */
function updateStatusDisplays(): array {
    global $previousStates;
    
    $manager = StatusDisplayManager::i();
    
    // Get all configurations
    $configs = $manager->getConfigurations();
    
    if (empty($configs)) {
        return [
            'total' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'message' => 'No configurations found'
        ];
    }
    
    $stats = [
        'total' => count($configs),
        'updated' => 0,
        'failed' => 0,
        'skipped' => 0
    ];
    
    try {
        // Check TeamSpeak connection
        if (!TeamSpeakUtils::i()->checkTSConnection()) {
            return [
                'total' => count($configs),
                'updated' => 0,
                'failed' => count($configs),
                'skipped' => 0,
                'message' => 'Cannot connect to TeamSpeak server'
            ];
        }
        
        // Get currently online clients (cache will auto-refresh based on configured interval)
        $onlineClients = CacheManager::i()->getClientList();
        $onlineCldbids = [];
        
        foreach ($onlineClients as $client) {
            if (isset($client['client_database_id'])) {
                $onlineCldbids[] = (int) $client['client_database_id'];
            }
        }
        
        // Process each configuration
        foreach ($configs as $config) {
            $cldbid = (int) $config['cldbid'];
            $channelId = (int) $config['channel_id'];
            $serverGroupId = isset($config['server_group_id']) ? (int) $config['server_group_id'] : null;
            
            $isOnline = in_array($cldbid, $onlineCldbids, true);
            
            // Check if state changed
            $stateKey = "{$cldbid}_{$channelId}";
            $stateChanged = !isset($previousStates[$stateKey]) || $previousStates[$stateKey] !== $isOnline;
            
            // Update if state changed (connect/disconnect event)
            if ($stateChanged) {
                echo date('[Y-m-d H:i:s]') . " Client {$cldbid} state changed to " . ($isOnline ? "ONLINE" : "OFFLINE") . " - updating channel {$channelId}\n";
                
                $result = $manager->updateChannelDescription($cldbid, $channelId, $isOnline, $serverGroupId);
                
                if ($result) {
                    $stats['updated']++;
                    $previousStates[$stateKey] = $isOnline;
                } else {
                    $stats['failed']++;
                    echo date('[Y-m-d H:i:s]') . " Failed to update channel {$channelId} for client {$cldbid}\n";
                }
            } else {
                $stats['skipped']++;
            }
        }
        
    } catch (\Exception $e) {
        echo date('[Y-m-d H:i:s]') . " Error: " . $e->getMessage() . "\n";
        $stats['failed'] = $stats['total'];
        $stats['updated'] = 0;
    }
    
    return $stats;
}

// Main execution loop
$iteration = 0;

do {
    $iteration++;
    
    if ($isDaemon && $iteration > 1) {
        echo date('[Y-m-d H:i:s]') . " Checking for status updates (iteration #{$iteration})...\n";
    } else if (!$isDaemon) {
        echo date('[Y-m-d H:i:s]') . " Running status update...\n";
    }
    
    $stats = updateStatusDisplays();
    
    echo date('[Y-m-d H:i:s]') . " Update complete: ";
    echo "Total: {$stats['total']}, ";
    echo "Updated: {$stats['updated']}, ";
    echo "Skipped: {$stats['skipped']}, ";
    echo "Failed: {$stats['failed']}";
    
    if (isset($stats['message'])) {
        echo " ({$stats['message']})";
    }
    
    echo "\n";
    
    if ($isDaemon) {
        if ($stats['updated'] > 0) {
            echo date('[Y-m-d H:i:s]') . " Next check in {$interval} seconds...\n\n";
        }
        sleep($interval);
    }
    
} while ($isDaemon);

echo "\nStatus Display Bot finished\n";
exit(0);
