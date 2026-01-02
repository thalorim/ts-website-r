<?php

/**
 * Sync Profiles from TeamSpeak Server
 * 
 * This script fetches user data from TeamSpeak server and populates/updates
 * the profiles table with created_ts and lastconnected_ts timestamps.
 * 
 * Usage:
 *   php sync-profiles-from-ts.php               Sync all users in user_statistics
 *   php sync-profiles-from-ts.php --cldbid=3    Sync specific user
 *   php sync-profiles-from-ts.php --all         Sync all online users
 */

use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;
use Wruczek\TSWebsite\CacheManager;

require_once __DIR__ . "/load.php";

// Parse arguments
$options = getopt("", ["cldbid::", "all"]);
$specificCldbid = isset($options["cldbid"]) ? (int)$options["cldbid"] : null;
$syncAll = isset($options["all"]);

echo "=================================================\n";
echo "Sync Profiles from TeamSpeak Server\n";
echo "=================================================\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "=================================================\n\n";

// Check TS connection
if (!TeamSpeakUtils::i()->checkTSConnection()) {
    echo "ERROR: Cannot connect to TeamSpeak server\n";
    echo "Please check your server query credentials in config.php\n";
    exit(1);
}

$db = DatabaseUtils::i()->getDb();
$tsServer = TeamSpeakUtils::i()->getTSNodeServer();
$synced = 0;
$failed = 0;
$skipped = 0;

// Determine which users to sync
$usersToSync = [];

if ($specificCldbid) {
    // Sync specific user
    echo "Mode: Sync specific user (cldbid: {$specificCldbid})\n\n";
    $usersToSync[] = ['cldbid' => $specificCldbid];
    
} else if ($syncAll) {
    // Sync all online users
    echo "Mode: Sync all currently online users\n\n";
    try {
        $clients = CacheManager::i()->getClientList();
        foreach ($clients as $client) {
            if (isset($client['client_type']) && $client['client_type'] == 1) {
                continue; // Skip query clients
            }
            $usersToSync[] = [
                'cldbid' => (int)$client['client_database_id'],
                'nickname' => (string)$client['client_nickname']
            ];
        }
        echo "Found " . count($usersToSync) . " online users\n\n";
    } catch (\Exception $e) {
        echo "ERROR: Failed to get online clients: " . $e->getMessage() . "\n";
        exit(1);
    }
    
} else {
    // Sync all users in user_statistics
    echo "Mode: Sync all users from user_statistics table\n\n";
    try {
        $stats = $db->select("user_statistics", ["cldbid", "last_nickname"]);
        foreach ($stats as $stat) {
            $usersToSync[] = [
                'cldbid' => (int)$stat['cldbid'],
                'nickname' => $stat['last_nickname']
            ];
        }
        echo "Found " . count($usersToSync) . " users in statistics\n\n";
    } catch (\Exception $e) {
        echo "ERROR: Failed to query user_statistics: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Process each user
foreach ($usersToSync as $user) {
    $cldbid = $user['cldbid'];
    $nickname = isset($user['nickname']) ? $user['nickname'] : "User #{$cldbid}";
    
    echo "[{$cldbid}] Processing {$nickname}... ";
    
    try {
        // Query TeamSpeak server for this user
        $tsInfo = $tsServer->clientDbInfo($cldbid);
        
        $createdTs = isset($tsInfo["client_created"]) ? (int)$tsInfo["client_created"] : null;
        $lastconnectedTs = isset($tsInfo["client_lastconnected"]) ? (int)$tsInfo["client_lastconnected"] : null;
        $totalConnections = isset($tsInfo["client_totalconnections"]) ? (int)$tsInfo["client_totalconnections"] : null;
        $cluid = isset($tsInfo["client_unique_identifier"]) ? (string)$tsInfo["client_unique_identifier"] : null;
        $tsNickname = isset($tsInfo["client_nickname"]) ? (string)$tsInfo["client_nickname"] : $nickname;
        
        if (!$createdTs && !$lastconnectedTs) {
            echo "SKIPPED (no TS data)\n";
            $skipped++;
            continue;
        }
        
        // Prepare data
        $profileData = [
            'nickname' => $tsNickname
        ];
        
        if ($createdTs) {
            $profileData['created_ts'] = $createdTs;
        }
        if ($lastconnectedTs) {
            $profileData['lastconnected_ts'] = $lastconnectedTs;
        }
        if ($totalConnections) {
            $profileData['totalconnections'] = $totalConnections;
        }
        if ($cluid) {
            $profileData['cluid'] = $cluid;
        }
        
        // Insert or update
        if ($db->has("profiles", ["cldbid" => $cldbid])) {
            $db->update("profiles", $profileData, ["cldbid" => $cldbid]);
            echo "UPDATED";
        } else {
            $profileData['cldbid'] = $cldbid;
            $db->insert("profiles", $profileData);
            echo "CREATED";
        }
        
        // Show dates
        if ($createdTs) {
            echo " (first: " . date('Y-m-d', $createdTs) . ")";
        }
        if ($lastconnectedTs) {
            echo " (last: " . date('Y-m-d H:i', $lastconnectedTs) . ")";
        }
        echo "\n";
        
        $synced++;
        
    } catch (\Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n=================================================\n";
echo "Sync Complete\n";
echo "=================================================\n";
echo "Synced:  {$synced}\n";
echo "Skipped: {$skipped}\n";
echo "Failed:  {$failed}\n";
echo "Total:   " . count($usersToSync) . "\n";
echo "=================================================\n";

if ($synced > 0) {
    echo "\nSuccess! Profile data has been populated.\n";
    echo "You can now update your stats channels.\n\n";
    echo "Next step:\n";
    echo "  Go to Admin Panel → Stats & Leaderboards → Click 'Update Now'\n";
}

exit(0);
