<?php

use Wruczek\TSWebsite\CacheManager;
use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;
use Wruczek\PhpFileCache\PhpFileCache;

require_once __DIR__ . "/private/php/load.php";

$db = DatabaseUtils::i()->getDb();

// Define staff groups: 532 (highest rank), 556 (admins)
$staffGroups = [532, 556];

// Define VIP groups for different games
$vipGroups = [
    'cs2' => Config::get('vip_cs2_groups', [557, 558]), // Example VIP group IDs for CS2
    'minecraft' => Config::get('vip_mc_groups', [559, 560]), // Example VIP group IDs for Minecraft
    'teamspeak' => Config::get('vip_ts3_groups', [561, 562]) // Example VIP group IDs for TeamSpeak
];

$staff = [];
$vipMembers = ['cs2' => [], 'minecraft' => [], 'teamspeak' => []];
$tsConnected = false;

try {
    $tsConnected = TeamSpeakUtils::i()->checkTSConnection();
} catch (\Exception $e) {
    $tsConnected = false;
}

// Function to fetch members by group IDs
function fetchMembersByGroups($groupIds, $db, $node = null) {
    $members = [];
    $clientGroupMap = [];
    
    if ($node) {
        // Fetch from TeamSpeak
        foreach ($groupIds as $groupId) {
            try {
                $clients = $node->serverGroupClientList((int)$groupId);
                if ($clients) {
                    foreach ($clients as $c) {
                        $dbid = isset($c['cldbid']) ? (int) $c['cldbid'] : (isset($c['client_database_id']) ? (int) $c['client_database_id'] : null);
                        if (!$dbid) continue;
                        
                        if (!isset($clientGroupMap[$dbid])) {
                            $clientGroupMap[$dbid] = [];
                        }
                        $clientGroupMap[$dbid][] = (int)$groupId;
                    }
                }
            } catch (\Exception $e) {
                // Continue with other groups
            }
        }
    }
    
    // Get online client list
    $onlineClients = [];
    try {
        $clientListData = CacheManager::i()->getClientList();
        if (is_array($clientListData)) {
            foreach ($clientListData as $client) {
                if (isset($client['client_database_id'])) {
                    $onlineClients[(int)$client['client_database_id']] = $client;
                }
            }
        }
    } catch (\Exception $e) {
        // Continue without online status
    }
    
    // Fetch profile data from database
    $profilesById = [];
    if (!empty($clientGroupMap)) {
        try {
            $ids = array_keys($clientGroupMap);
            $rows = $db->select('profiles', '*', ['cldbid' => $ids]);
            foreach ($rows as $r) {
                $profilesById[(int)$r['cldbid']] = $r;
            }
        } catch (\Exception $e) {
            // Continue without profile data
        }
    }
    
    // Build member list
    foreach ($clientGroupMap as $dbid => $memberGroupIds) {
        $nick = null;
        $country = null;
        $isOnline = false;
        $avatarUrl = null;
        $socialLinks = [];
        
        // Check if user is online
        if (isset($onlineClients[$dbid])) {
            $isOnline = true;
            $online = $onlineClients[$dbid];
            
            if (isset($online['client_nickname']) && !empty($online['client_nickname'])) {
                $nick = (string) $online['client_nickname'];
            }
            
            if (isset($online['client_country']) && !empty($online['client_country'])) {
                $country = (string) $online['client_country'];
            }
        }
        
        // Get data from profile database
        if (isset($profilesById[$dbid])) {
            $profile = $profilesById[$dbid];
            
            if (empty($nick) && !empty($profile['nickname'])) {
                $nick = (string) $profile['nickname'];
            }
            if (empty($country) && !empty($profile['country'])) {
                $country = (string) $profile['country'];
            }
            
            // Get avatar URL
            if (!empty($profile['avatar_url'])) {
                $avatarUrl = (string) $profile['avatar_url'];
            }
            
            // Get social links
            if (!empty($profile['discord'])) {
                $socialLinks['discord'] = (string) $profile['discord'];
            }
            if (!empty($profile['steam'])) {
                $socialLinks['steam'] = (string) $profile['steam'];
            }
            if (!empty($profile['twitter'])) {
                $socialLinks['twitter'] = (string) $profile['twitter'];
            }
            if (!empty($profile['youtube'])) {
                $socialLinks['youtube'] = (string) $profile['youtube'];
            }
            if (!empty($profile['github'])) {
                $socialLinks['github'] = (string) $profile['github'];
            }
        }
        
        // Last fallback: use cached "last seen" data
        if (empty($nick) || empty($country)) {
            try {
                $lastSeenCache = new PhpFileCache(__CACHE_DIR, "profile_last_seen");
                $cached = $lastSeenCache->retrieve("u_" . $dbid);
                if (is_array($cached)) {
                    if (empty($nick) && !empty($cached['nickname'])) {
                        $nick = (string) $cached['nickname'];
                    }
                    if (empty($country) && !empty($cached['country'])) {
                        $country = (string) $cached['country'];
                    }
                }
            } catch (\Exception $e) {
                // Continue without cached data
            }
        }
        
        $members[] = [
            'cldbid' => $dbid,
            'nickname' => $nick ?: ('User #' . $dbid),
            'country' => $country ?: null,
            'isOnline' => $isOnline,
            'groups' => $memberGroupIds,
            'avatarUrl' => $avatarUrl,
            'socialLinks' => $socialLinks
        ];
    }
    
    return $members;
}

if ($tsConnected) {
    try {
        $node = TeamSpeakUtils::i()->getTSNodeServer();
        
        // Fetch staff members
        $staff = fetchMembersByGroups($staffGroups, $db, $node);
        
        // Fetch VIP members for each game
        foreach ($vipGroups as $game => $groupIds) {
            $vipMembers[$game] = fetchMembersByGroups($groupIds, $db, $node);
        }
        
    } catch (\Exception $e) {
        // Fall through to database fallback
    }
}

// Fallback to database if TeamSpeak is not available
if (empty($staff)) {
    try {
        $rows = $db->select("profiles", "*", ["ORDER" => ["cldbid" => "ASC"]]);
        foreach ($rows as $r) {
            $sg = isset($r["servergroups"]) ? (string) $r["servergroups"] : "";
            $sgids = array_values(array_filter(
                array_map(function ($x) { return (int) trim($x); }, explode(",", $sg)),
                function ($v) { return $v > 0; }
            ));
            
            if (empty($sgids)) continue;
            
            // Check if user has any staff groups
            $matchingStaffGroups = array_intersect($sgids, $staffGroups);
            if (!empty($matchingStaffGroups)) {
                $staff[] = [
                    'cldbid' => (int) $r['cldbid'],
                    'nickname' => isset($r['nickname']) ? (string) $r['nickname'] : ('User #' . $r['cldbid']),
                    'country' => isset($r['country']) ? (string) $r['country'] : null,
                    'isOnline' => false,
                    'groups' => $sgids,
                    'avatarUrl' => isset($r['avatar_url']) ? (string) $r['avatar_url'] : null,
                    'socialLinks' => [
                        'discord' => isset($r['discord']) ? (string) $r['discord'] : null,
                        'steam' => isset($r['steam']) ? (string) $r['steam'] : null,
                        'twitter' => isset($r['twitter']) ? (string) $r['twitter'] : null,
                        'youtube' => isset($r['youtube']) ? (string) $r['youtube'] : null,
                        'github' => isset($r['github']) ? (string) $r['github'] : null,
                    ]
                ];
            }
            
            // Check VIP groups
            foreach ($vipGroups as $game => $groupIds) {
                $matchingVipGroups = array_intersect($sgids, $groupIds);
                if (!empty($matchingVipGroups)) {
                    $vipMembers[$game][] = [
                        'cldbid' => (int) $r['cldbid'],
                        'nickname' => isset($r['nickname']) ? (string) $r['nickname'] : ('User #' . $r['cldbid']),
                        'country' => isset($r['country']) ? (string) $r['country'] : null,
                        'isOnline' => false,
                        'groups' => $sgids,
                        'avatarUrl' => isset($r['avatar_url']) ? (string) $r['avatar_url'] : null,
                        'socialLinks' => [
                            'discord' => isset($r['discord']) ? (string) $r['discord'] : null,
                            'steam' => isset($r['steam']) ? (string) $r['steam'] : null,
                            'twitter' => isset($r['twitter']) ? (string) $r['twitter'] : null,
                            'youtube' => isset($r['youtube']) ? (string) $r['youtube'] : null,
                            'github' => isset($r['github']) ? (string) $r['github'] : null,
                        ]
                    ];
                }
            }
        }
    } catch (\Exception $e) {
        // Continue with empty lists
    }
}

// Sort staff: group 532 first, then 556
usort($staff, function($a, $b) {
    $aHas532 = in_array(532, $a['groups']);
    $bHas532 = in_array(532, $b['groups']);
    
    if ($aHas532 && !$bHas532) return -1;
    if (!$aHas532 && $bHas532) return 1;
    
    return $a['cldbid'] <=> $b['cldbid'];
});

// Get server groups list for display
$serverGroups = null;
try {
    $serverGroups = CacheManager::i()->getServerGroupList();
} catch (\Exception $e) {
    // Continue without server groups
}

TemplateUtils::i()->renderTemplate("staff", [
    "staff" => $staff,
    "vipMembers" => $vipMembers,
    "serverGroups" => $serverGroups,
    "navActiveIndex" => 8
]);
