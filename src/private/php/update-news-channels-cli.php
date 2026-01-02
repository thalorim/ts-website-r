#!/usr/bin/env php
<?php
/**
 * CLI Script to Update News Channels
 * 
 * Usage:
 *   php update-news-channels-cli.php
 * 
 * This script updates all configured news channels with the latest news.
 * It can be run manually or via cron job.
 */

// Change to the script directory to ensure correct path resolution
chdir(__DIR__);

// Load the application
require_once __DIR__ . '/load.php';

use Wruczek\TSWebsite\Utils\NewsChannelDisplayManager;

try {
    echo "=================================================\n";
    echo "News Channel Display Updater\n";
    echo "=================================================\n\n";
    
    echo "Initializing manager...\n";
    $manager = NewsChannelDisplayManager::i();
    
    echo "Fetching configurations...\n";
    $configurations = $manager->getConfigurations();
    echo "Found " . count($configurations) . " configuration(s)\n\n";
    
    if (empty($configurations)) {
        echo "No configurations found. Please add configurations via the admin panel.\n";
        echo "Admin panel: https://your-site.com/admin/news-channel-display.php\n";
        exit(0);
    }
    
    echo "Updating all channels...\n";
    $result = $manager->updateAllChannels();
    
    echo "\n=================================================\n";
    echo "Update Results:\n";
    echo "=================================================\n";
    echo "Total configurations: " . $result['total'] . "\n";
    echo "Successfully updated:  " . $result['updated'] . "\n";
    echo "Failed:               " . $result['failed'] . "\n";
    echo "Skipped:              " . $result['skipped'] . "\n";
    echo "\n";
    
    if (!empty($result['details'])) {
        echo "Details:\n";
        echo "-------------------------------------------------\n";
        foreach ($result['details'] as $detail) {
            $configId = $detail['config_id'] ?? 'N/A';
            $channelId = $detail['channel_id'] ?? 'N/A';
            $status = $detail['status'] ?? 'unknown';
            
            echo "Config #{$configId} (Channel #{$channelId}): ";
            
            if ($status === 'success') {
                echo "✓ SUCCESS\n";
            } elseif ($status === 'skipped') {
                $reason = $detail['reason'] ?? 'Unknown reason';
                echo "○ SKIPPED ({$reason})\n";
            } else {
                $error = $detail['error'] ?? 'Unknown error';
                echo "✗ FAILED ({$error})\n";
            }
        }
    }
    
    echo "\n=================================================\n";
    
    if ($result['failed'] > 0) {
        echo "Some updates failed. Check the details above.\n";
        exit(1);
    } else {
        echo "All updates completed successfully!\n";
        exit(0);
    }
    
} catch (\Exception $e) {
    echo "\n=================================================\n";
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "=================================================\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
