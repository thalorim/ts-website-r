#!/usr/bin/env php
<?php
/**
 * Test Script for News Channel Display Setup
 * 
 * This script checks if everything is properly configured for the
 * news channel display system.
 * 
 * Usage:
 *   php test-news-channel-setup.php
 */

// Change to the script directory to ensure correct path resolution
chdir(__DIR__);

// Load the application
require_once __DIR__ . '/load.php';

use Wruczek\TSWebsite\Utils\NewsChannelDisplayManager;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;
use Wruczek\TSWebsite\News\DefaultNewsStore;

echo "=================================================\n";
echo "News Channel Display - Setup Test\n";
echo "=================================================\n\n";

$allGood = true;

// Test 1: Database Connection
echo "[1/6] Testing database connection...\n";
try {
    $db = DatabaseUtils::i()->getDb();
    echo "      ✓ Database connection successful\n";
} catch (\Exception $e) {
    echo "      ✗ Database connection failed: " . $e->getMessage() . "\n";
    $allGood = false;
}
echo "\n";

// Test 2: TeamSpeak Connection
echo "[2/6] Testing TeamSpeak connection...\n";
try {
    if (TeamSpeakUtils::i()->checkTSConnection()) {
        echo "      ✓ TeamSpeak connection successful\n";
        $server = TeamSpeakUtils::i()->getTSNodeServer();
        $info = $server->getInfo();
        echo "      Server: " . $info['virtualserver_name'] . "\n";
    } else {
        echo "      ✗ TeamSpeak connection failed\n";
        $exceptions = TeamSpeakUtils::i()->getExceptionsList();
        foreach ($exceptions as $ex) {
            echo "      Error: " . $ex->getMessage() . "\n";
        }
        $allGood = false;
    }
} catch (\Exception $e) {
    echo "      ✗ TeamSpeak connection failed: " . $e->getMessage() . "\n";
    $allGood = false;
}
echo "\n";

// Test 3: News Channel Display Manager
echo "[3/6] Testing NewsChannelDisplayManager...\n";
try {
    $manager = NewsChannelDisplayManager::i();
    echo "      ✓ Manager initialized\n";
    
    $manager->ensureTableExists();
    echo "      ✓ Database table created/verified\n";
    
    $configs = $manager->getConfigurations();
    echo "      ✓ Found " . count($configs) . " configuration(s)\n";
    
    if (count($configs) === 0) {
        echo "      ⚠ No configurations found. Add one via admin panel.\n";
    }
} catch (\Exception $e) {
    echo "      ✗ Manager error: " . $e->getMessage() . "\n";
    $allGood = false;
}
echo "\n";

// Test 4: News Store
echo "[4/6] Testing news data...\n";
try {
    $newsStore = new DefaultNewsStore();
    $newsCount = $newsStore->getNewsCount();
    echo "      ✓ News store initialized\n";
    echo "      Found {$newsCount} news item(s)\n";
    
    if ($newsCount === 0) {
        echo "      ⚠ No news found. Add news via admin panel to test.\n";
    } else {
        $newsList = $newsStore->getNewsList(3);
        echo "      Latest news:\n";
        foreach ($newsList as $news) {
            $title = substr($news['title'], 0, 50);
            if (strlen($news['title']) > 50) $title .= '...';
            echo "        - " . $title . "\n";
        }
    }
} catch (\Exception $e) {
    echo "      ✗ News store error: " . $e->getMessage() . "\n";
    $allGood = false;
}
echo "\n";

// Test 5: Channel List
echo "[5/6] Testing channel access...\n";
try {
    if (TeamSpeakUtils::i()->checkTSConnection()) {
        $server = TeamSpeakUtils::i()->getTSNodeServer();
        $channels = $server->channelList();
        echo "      ✓ Can access channel list\n";
        echo "      Found " . count($channels) . " channel(s)\n";
    } else {
        echo "      ✗ Cannot access channels (TeamSpeak not connected)\n";
        $allGood = false;
    }
} catch (\Exception $e) {
    echo "      ✗ Channel access error: " . $e->getMessage() . "\n";
    $allGood = false;
}
echo "\n";

// Test 6: Configuration Details
echo "[6/6] Configuration details...\n";
try {
    $configs = $manager->getConfigurations();
    
    if (empty($configs)) {
        echo "      ⚠ No configurations to test\n";
    } else {
        foreach ($configs as $config) {
            $id = $config['id'];
            $channelId = $config['channel_id'];
            $enabled = $config['enabled'] ? 'Yes' : 'No';
            $newsLimit = $config['news_limit'];
            $showContent = $config['show_content'] ? 'Yes' : 'No';
            
            echo "      Config #{$id}:\n";
            echo "        Channel ID: {$channelId}\n";
            echo "        Enabled: {$enabled}\n";
            echo "        News Limit: {$newsLimit}\n";
            echo "        Show Content: {$showContent}\n";
            
            // Try to get channel name
            try {
                if (TeamSpeakUtils::i()->checkTSConnection()) {
                    $server = TeamSpeakUtils::i()->getTSNodeServer();
                    $channel = $server->channelGetById($channelId);
                    $info = $channel->getInfo();
                    $channelName = (string) $info['channel_name'];
                    echo "        Channel Name: {$channelName}\n";
                }
            } catch (\Exception $e) {
                echo "        Channel Name: [Error: " . $e->getMessage() . "]\n";
            }
            
            echo "\n";
        }
    }
} catch (\Exception $e) {
    echo "      ✗ Configuration error: " . $e->getMessage() . "\n";
    $allGood = false;
}

echo "=================================================\n";
if ($allGood) {
    echo "✓ ALL TESTS PASSED\n";
    echo "\nYou can now:\n";
    echo "1. Add configurations via admin panel\n";
    echo "2. Update channels manually or via cron\n";
    echo "3. Set up automated updates\n";
} else {
    echo "✗ SOME TESTS FAILED\n";
    echo "\nPlease fix the errors above before using the system.\n";
}
echo "=================================================\n";

exit($allGood ? 0 : 1);
