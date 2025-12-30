<?php

/**
 * Status Display Update API Endpoint
 * 
 * This endpoint can be called to trigger status display updates manually
 * or via cron job if you don't want to run the bot as a daemon.
 * 
 * Usage:
 *   GET /api/status-display-update.php
 *   GET /api/status-display-update.php?cldbid=123
 */

use Wruczek\TSWebsite\Utils\StatusDisplayManager;
use Wruczek\TSWebsite\CacheManager;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

try {
    $manager = StatusDisplayManager::i();
    
    // Ensure table exists
    if (!$manager->ensureTableExists()) {
        throw new \Exception("Failed to ensure database table exists");
    }
    
    // Check if specific cldbid is requested
    $cldbid = isset($_GET['cldbid']) ? (int) $_GET['cldbid'] : null;
    
    if ($cldbid !== null) {
        // Update specific user
        $configs = $manager->getConfigurationByCldbid($cldbid);
        
        if (!$configs) {
            echo json_encode([
                'success' => false,
                'error' => 'No configuration found for this client database ID'
            ]);
            exit;
        }
        
        // Check if user is online
        $onlineClients = CacheManager::i()->getClientList();
        $isOnline = false;
        
        foreach ($onlineClients as $client) {
            if (isset($client['client_database_id']) && (int) $client['client_database_id'] === $cldbid) {
                $isOnline = true;
                break;
            }
        }
        
        $updated = 0;
        $failed = 0;
        
        foreach ($configs as $config) {
            $channelId = (int) $config['channel_id'];
            $serverGroupId = isset($config['server_group_id']) ? (int) $config['server_group_id'] : null;
            
            $result = $manager->updateChannelDescription($cldbid, $channelId, $isOnline, $serverGroupId);
            
            if ($result) {
                $updated++;
            } else {
                $failed++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'cldbid' => $cldbid,
            'is_online' => $isOnline,
            'updated' => $updated,
            'failed' => $failed
        ]);
    } else {
        // Update all configurations
        $stats = $manager->processAllStatusUpdates();
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
