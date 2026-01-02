<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\NewsDisplayManager;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

// Check if user is logged in and is admin (CLDBID 3)
if (!Auth::isLoggedIn() || Auth::getCldbid() !== 3) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "forbidden"]);
    exit;
}

try {
    $manager = NewsDisplayManager::i();
    $manager->ensureTableExists();
    
    // Get optional parameters
    $channelId = isset($_POST["channel_id"]) ? (int) $_POST["channel_id"] : null;
    
    if ($channelId !== null) {
        // Update specific channel
        $config = $manager->getConfigurationByChannelId($channelId);
        if (!$config) {
            http_response_code(404);
            echo json_encode(["ok" => false, "error" => "Channel not configured for news display"]);
            exit;
        }
        
        $newsLimit = (int) ($config['news_limit'] ?? 5);
        $result = $manager->updateChannelDescription($channelId, $newsLimit);
        
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
        $stats = $manager->processAllNewsUpdates();
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
