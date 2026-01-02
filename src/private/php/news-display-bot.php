<?php

/**
 * News Display Bot
 * 
 * This bot monitors for news changes and automatically updates
 * configured TeamSpeak channels with the latest news.
 * 
 * Usage:
 *   php news-display-bot.php --once             Run once and exit
 *   php news-display-bot.php --daemon           Run continuously
 *   php news-display-bot.php --daemon --interval=300  Run with 5 minute interval
 */

use Wruczek\TSWebsite\Utils\NewsDisplayManager;
use Wruczek\TSWebsite\Utils\DatabaseUtils;

require_once __DIR__ . "/load.php";

// Parse command line arguments
$options = getopt("", ["once", "daemon", "interval::"]);

$runOnce = isset($options["once"]);
$runDaemon = isset($options["daemon"]);
$interval = isset($options["interval"]) ? (int) $options["interval"] : 300; // Default 5 minutes

if (!$runOnce && !$runDaemon) {
    echo "News Display Bot - Updates TeamSpeak channels with latest news\n";
    echo "\n";
    echo "Usage:\n";
    echo "  php news-display-bot.php --once             Run once and exit\n";
    echo "  php news-display-bot.php --daemon           Run continuously (default 300s interval)\n";
    echo "  php news-display-bot.php --daemon --interval=300  Run with custom interval (seconds)\n";
    echo "\n";
    exit(1);
}

// Validate interval
if ($interval < 30) {
    echo "Error: Interval must be at least 30 seconds\n";
    exit(1);
}

echo "=================================================\n";
echo "News Display Bot\n";
echo "=================================================\n";
echo "Mode: " . ($runOnce ? "Single run" : "Daemon mode") . "\n";
if ($runDaemon) {
    echo "Interval: {$interval} seconds\n";
}
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "=================================================\n\n";

$manager = NewsDisplayManager::i();
$manager->ensureTableExists();

$lastNewsHash = null;

/**
 * Check if news has changed since last check
 * @return bool True if news has changed
 */
function hasNewsChanged() {
    global $lastNewsHash;
    
    try {
        $db = DatabaseUtils::i()->getDb();
        
        // Get latest news items with their edit timestamps
        $newsList = $db->select("news", ["newsid", "added", "edited"], [
            "ORDER" => ["added" => "DESC"],
            "LIMIT" => 10 // Check last 10 news items
        ]);
        
        if (empty($newsList)) {
            return false;
        }
        
        // Create hash of news data
        $currentHash = md5(json_encode($newsList));
        
        if ($lastNewsHash === null) {
            // First run, store hash
            $lastNewsHash = $currentHash;
            return true; // Trigger initial update
        }
        
        if ($currentHash !== $lastNewsHash) {
            $lastNewsHash = $currentHash;
            return true;
        }
        
        return false;
    } catch (\Exception $e) {
        error_log("Error checking news changes: " . $e->getMessage());
        return false;
    }
}

/**
 * Process news updates
 */
function processNewsUpdates() {
    global $manager;
    
    echo "[" . date('Y-m-d H:i:s') . "] Processing news updates...\n";
    
    try {
        $stats = $manager->processAllNewsUpdates();
        
        echo "[" . date('Y-m-d H:i:s') . "] Update complete:\n";
        echo "  - Total configurations: {$stats['total']}\n";
        echo "  - Successfully updated: {$stats['updated']}\n";
        echo "  - Failed: {$stats['failed']}\n";
        
        if ($stats['failed'] > 0) {
            echo "  WARNING: Some updates failed. Check error logs for details.\n";
        }
        
        echo "\n";
        
    } catch (\Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n\n";
        error_log("News display bot error: " . $e->getMessage());
    }
}

// Signal handlers for graceful shutdown (if available)
if (function_exists('pcntl_signal')) {
    declare(ticks = 1);
    
    $running = true;
    
    pcntl_signal(SIGTERM, function() use (&$running) {
        global $running;
        echo "\n[" . date('Y-m-d H:i:s') . "] Received SIGTERM, shutting down gracefully...\n";
        $running = false;
    });
    
    pcntl_signal(SIGINT, function() use (&$running) {
        global $running;
        echo "\n[" . date('Y-m-d H:i:s') . "] Received SIGINT (Ctrl+C), shutting down gracefully...\n";
        $running = false;
    });
} else {
    $running = true;
}

// Main loop
if ($runOnce) {
    // Run once and exit
    processNewsUpdates();
    echo "[" . date('Y-m-d H:i:s') . "] Single run completed. Exiting.\n";
    exit(0);
}

// Daemon mode
echo "[" . date('Y-m-d H:i:s') . "] Bot is running. Press Ctrl+C to stop.\n\n";

$iterationCount = 0;

while ($running) {
    $iterationCount++;
    
    // Check if news has changed
    if (hasNewsChanged()) {
        echo "[" . date('Y-m-d H:i:s') . "] News changes detected (iteration #{$iterationCount})\n";
        processNewsUpdates();
    } else {
        // Only log every 10th iteration to reduce log spam
        if ($iterationCount % 10 === 0) {
            echo "[" . date('Y-m-d H:i:s') . "] No news changes detected (iteration #{$iterationCount})\n";
        }
    }
    
    // Sleep for the specified interval
    sleep($interval);
}

echo "[" . date('Y-m-d H:i:s') . "] Bot stopped.\n";
exit(0);
