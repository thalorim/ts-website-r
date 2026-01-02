<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\StatsDisplayManager;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

// Check if user is logged in and is admin (CLDBID 3)
if (!Auth::isLoggedIn() || Auth::getCldbid() !== 3) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "forbidden"]);
    exit;
}

try {
    $manager = StatsDisplayManager::i();
    $manager->ensureTablesExist();
    
    // Get optional parameters
    $channelId = isset($_POST["channel_id"]) ? (int) $_POST["channel_id"] : null;
    
    if ($channelId !== null) {
        // Update specific channel
        $configs = $manager->getConfigurations();
        $config = null;
        
        foreach ($configs as $c) {
            if ((int)$c['channel_id'] === $channelId) {
                $config = $c;
                break;
            }
        }
        
        if (!$config) {
            http_response_code(404);
            echo json_encode(["ok" => false, "error" => "Channel not configured for stats display"]);
            exit;
        }
        
        $displayType = $config['display_type'] ?? 'combined';
        $topCount = (int) ($config['top_count'] ?? 10);
        
        $result = $manager->updateChannelDescription($channelId, $displayType, $topCount);
        
        if ($result) {
            echo json_encode([
                "ok" => true,
                "message" => "Channel updated successfully",
                "channel_id" => $channelId
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["ok" => false, "error" => "Failed to update channel"]);
        }
    } else {
        // Update all configured channels
        $stats = $manager->processAllStatsUpdates();
        echo json_encode([
            "ok" => true,
            "message" => "All channels updated",
            "stats" => $stats
        ]);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}
