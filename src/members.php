<?php

use Wruczek\TSWebsite\CacheManager;
use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;
use Wruczek\PhpFileCache\PhpFileCache;

require_once __DIR__ . "/private/php/load.php";

$db = DatabaseUtils::i()->getDb();

// Get configurable member groups from database
// Display order: 532, 556, 543, 533, then 1005 down to 996
$memberGroups = Config::get("members_groups", [532, 556, 543, 533, 1005, 1004, 1003, 1002, 1001, 1000, 999, 998, 997, 996]);
if (!is_array($memberGroups) || empty($memberGroups)) {
    $memberGroups = [532, 556, 543, 533, 1005, 1004, 1003, 1002, 1001, 1000, 999, 998, 997, 996];
}

// Define priority order for member groups (lower priority value = displayed first)
$groupPriority = array_flip(array_values($memberGroups));

// Helper function to get the best priority for a user based on their groups
$getBestPriority = function($groups) use ($groupPriority) {
    $priorities = [];
    foreach ($groups as $gid) {
        if (isset($groupPriority[$gid])) {
            $priorities[] = $groupPriority[$gid];
        }
    }
    return !empty($priorities) ? min($priorities) : PHP_INT_MAX;
};

// ALWAYS fetch fresh data from TeamSpeak - NO CACHING for member list
$members = [];
$tsConnected = false;

try {
    $tsConnected = TeamSpeakUtils::i()->checkTSConnection();
} catch (\Exception $e) {
    $tsConnected = false;
}

if ($tsConnected) {
    try {
        $node = TeamSpeakUtils::i()->getTSNodeServer();
        
        // Fetch all clients from configured member groups directly from TeamSpeak
        $groupClients = [];
        foreach ($memberGroups as $groupId) {
            try {
                $clients = $node->serverGroupClientList((int)$groupId);
                $groupClients[$groupId] = $clients ?: [];
            } catch (\Exception $e) {
                $groupClients[$groupId] = [];
            }
        }

        // Build a map of client database IDs to their member groups
        $clientGroupMap = [];
        foreach ($groupClients as $groupId => $clients) {
            foreach ($clients as $c) {
                $dbid = isset($c['cldbid']) ? (int) $c['cldbid'] : (isset($c['client_database_id']) ? (int) $c['client_database_id'] : null);
                if (!$dbid) continue;
                
                if (!isset($clientGroupMap[$dbid])) {
                    $clientGroupMap[$dbid] = [];
                }
                $clientGroupMap[$dbid][] = (int)$groupId;
            }
        }

        // Get online client list to check who's online
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

        // Fetch ALL server groups directly from TeamSpeak for ALL members
        $allServerGroupsByClient = [];
        if (!empty($clientGroupMap)) {
            foreach (array_keys($clientGroupMap) as $dbid) {
                try {
                    // Query TeamSpeak directly for this client's server groups
                    $clientInfo = $node->clientDBInfo($dbid);
                    if ($clientInfo && isset($clientInfo['client_servergroups'])) {
                        $sgStr = (string) $clientInfo['client_servergroups'];
                        $allServerGroupsByClient[$dbid] = array_values(array_filter(
                            array_map(function ($x) { return (int) trim($x); }, explode(',', $sgStr)),
                            function ($v) { return $v > 0; }
                        ));
                    }
                } catch (\Exception $e) {
                    // If direct query fails, fallback will be used
                }
            }
        }
        
        // Fetch profile data from database (including servergroups as fallback)
        $profilesById = [];
        if (!empty($clientGroupMap)) {
            try {
                $ids = array_keys($clientGroupMap);
                $rows = $db->select('profiles', ['cldbid', 'nickname', 'country', 'servergroups'], ['cldbid' => $ids]);
                foreach ($rows as $r) {
                    $profilesById[(int)$r['cldbid']] = [
                        'nickname' => (string) $r['nickname'],
                        'country' => (string) $r['country'],
                        'servergroups' => (string) $r['servergroups'],
                    ];
                }
            } catch (\Exception $e) {
                // Continue without profile data
            }
        }

        // Build member list with fresh data
        foreach ($clientGroupMap as $dbid => $memberGroupIds) {
            $cat = $getBestPriority($memberGroupIds);
            $nick = null;
            $country = null;
            $isOnline = false;
            $servergroups = [];
            
            // Check if user is online and get live data
            if (isset($onlineClients[$dbid])) {
                $isOnline = true;
                $online = $onlineClients[$dbid];
                
                // Get nickname from online client
                if (isset($online['client_nickname']) && !empty($online['client_nickname'])) {
                    $nick = (string) $online['client_nickname'];
                }
                
                // Get country from online client
                if (isset($online['client_country']) && !empty($online['client_country'])) {
                    $country = (string) $online['client_country'];
                }
                
                // Priority 1: Get server groups from online client (MOST CURRENT)
                if (isset($online['client_servergroups']) && !empty($online['client_servergroups'])) {
                    $sgStr = (string) $online['client_servergroups'];
                    $servergroups = array_values(array_filter(
                        array_map(function ($x) { return (int) trim($x); }, explode(',', $sgStr)),
                        function ($v) { return $v > 0; }
                    ));
                }
            }
            
            // Priority 2: Get server groups from TeamSpeak clientDBInfo (if online didn't provide)
            if (empty($servergroups) && isset($allServerGroupsByClient[$dbid])) {
                $servergroups = $allServerGroupsByClient[$dbid];
            }
            
            // Fallback to profile database for nickname/country and server groups
            if (isset($profilesById[$dbid])) {
                if (empty($nick) && !empty($profilesById[$dbid]['nickname'])) {
                    $nick = $profilesById[$dbid]['nickname'];
                }
                if (empty($country) && !empty($profilesById[$dbid]['country'])) {
                    $country = $profilesById[$dbid]['country'];
                }
                // Priority 3: Fallback to database server groups if TeamSpeak queries failed
                if (empty($servergroups) && !empty($profilesById[$dbid]['servergroups'])) {
                    $sgStr = $profilesById[$dbid]['servergroups'];
                    $servergroups = array_values(array_filter(
                        array_map(function ($x) { return (int) trim($x); }, explode(',', $sgStr)),
                        function ($v) { return $v > 0; }
                    ));
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
            
            // Store online data for future offline fallback
            if ($isOnline && ($nick || $country)) {
                try {
                    $lastSeenCache = new PhpFileCache(__CACHE_DIR, "profile_last_seen");
                    $cacheData = [
                        'nickname' => $nick,
                        'country' => $country,
                        'ts' => time(),
                    ];
                    $lastSeenCache->store("u_" . $dbid, $cacheData, 31536000); // 365 days
                } catch (\Exception $e) {
                    // Ignore cache errors
                }
            }
            
            $members[] = [
                'cldbid' => $dbid,
                'nickname' => $nick ?: ('User #' . $dbid),
                'country' => $country ?: null,
                'cat' => $cat,
                'isOnline' => $isOnline,
                'servergroups' => $servergroups,
            ];
        }
    } catch (\Exception $e) {
        // If TeamSpeak query fails, fall through to database fallback
    }
}

// Fallback to profiles table if TS server not reachable or no data
if (empty($members)) {
    try {
        $rows = $db->select("profiles", ["cldbid", "nickname", "country", "servergroups"], ["ORDER" => ["cldbid" => "ASC"]]);
        foreach ($rows as $r) {
            $sg = isset($r["servergroups"]) ? (string) $r["servergroups"] : "";
            $sgids = array_values(array_filter(
                array_map(function ($x) { return (int) trim($x); }, explode(",", $sg)),
                function ($v) { return $v > 0; }
            ));
            
            if (empty($sgids)) continue;
            
            // Check if user has any of the configured member groups
            $matchingGroups = array_intersect($sgids, $memberGroups);
            if (empty($matchingGroups)) continue;
            
            $cat = $getBestPriority(array_values($matchingGroups));
            $dbid = (int) $r["cldbid"];
            $nick = isset($r["nickname"]) && $r["nickname"] !== '' ? (string) $r["nickname"] : null;
            $country = isset($r['country']) && $r['country'] !== '' ? (string) $r['country'] : null;
            
            // Try to use cached "last seen" data as fallback
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
                "cldbid" => $dbid,
                "nickname" => $nick ?: ("User #" . $dbid),
                "country" => $country ?: null,
                "cat" => $cat,
                "isOnline" => false,
                "servergroups" => $sgids,
            ];
        }
    } catch (\Exception $e) {
        // Continue with empty members list
    }
}

// Sort: by category (priority order), then by cldbid ascending
if (!empty($members)) {
    usort($members, function ($a, $b) {
        if ($a["cat"] === $b["cat"]) {
            return $a["cldbid"] <=> $b["cldbid"];
        }
        return $a["cat"] <=> $b["cat"];
    });
}

// Get configurable rank badge range from database, default to 996-1005
$rankBadgeRange = Config::get("rank_badge_range", ["min" => 996, "max" => 1005]);
$rankMin = isset($rankBadgeRange['min']) ? (int)$rankBadgeRange['min'] : 996;
$rankMax = isset($rankBadgeRange['max']) ? (int)$rankBadgeRange['max'] : 1005;

// Get server groups list for display names and icons
$serverGroups = null;
try {
    $serverGroups = CacheManager::i()->getServerGroupList();
} catch (\Exception $e) {
    // Continue without server groups metadata
}

// Enrich members with rank icons based on their server groups
if (!empty($members) && $serverGroups) {
    foreach ($members as &$m) {
        $sgids = $m['servergroups'];
        
        // Find rank icon from groups in configured rank range
        if (!empty($sgids)) {
            $rankGroups = array_values(array_filter($sgids, function ($g) use ($rankMin, $rankMax) { 
                return $g >= $rankMin && $g <= $rankMax; 
            }));
            
            if (!empty($rankGroups)) {
                $chosen = max($rankGroups);
                if (isset($serverGroups[$chosen]) && !empty($serverGroups[$chosen]['iconid'])) {
                    $m['rank_iconid'] = (int) $serverGroups[$chosen]['iconid'];
                    $m['rank_group_name'] = isset($serverGroups[$chosen]['name']) ? (string) $serverGroups[$chosen]['name'] : null;
                }
            }
        }
    }
    unset($m);
}

TemplateUtils::i()->renderTemplate("members", [
    "title" => "Members list",
    "navActiveIndex" => 6,
    "members" => $members,
    "serverGroups" => $serverGroups
]);
