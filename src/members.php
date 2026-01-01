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

// Cache member list to avoid flooding ServerQuery (cache for 30 seconds)
$membersCache = new PhpFileCache(__CACHE_DIR, "members_list");
$cacheKey = "members_" . md5(json_encode($memberGroups));
$members = [];

try {
    $members = $membersCache->retrieve($cacheKey);
} catch (\Exception $e) {
    $members = null;
}

// If cache miss or invalid, fetch from server or database
if (!is_array($members)) {
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
            // Dynamically fetch all configured member groups
            $groupClients = [];
            foreach ($memberGroups as $groupId) {
                $groupClients[$groupId] = $node->serverGroupClientList((int)$groupId) ?: [];
            }

            // Map each client to their groups
            $map = [];
            foreach ($groupClients as $groupId => $clients) {
                foreach ($clients as $c) {
                    $dbid = isset($c['cldbid']) ? (int) $c['cldbid'] : (isset($c['client_database_id']) ? (int) $c['client_database_id'] : null);
                    if (!$dbid) continue;
                    if (!isset($map[$dbid])) {
                        $map[$dbid] = ['groups' => []];
                    }
                    $map[$dbid]['groups'][] = (int)$groupId;
                }
            }

            if (!empty($map)) {
                // Prefetch profile country/nickname where available
                $profilesById = [];
                try {
                    $ids = array_keys($map);
                    if (!empty($ids)) {
                        $rows = $db->select('profiles', ['cldbid', 'nickname', 'country'], ['cldbid' => $ids]);
                        foreach ($rows as $r) {
                            $profilesById[(int)$r['cldbid']] = [
                                'nickname' => (string) $r['nickname'],
                                'country' => (string) $r['country'],
                            ];
                        }
                    }
                } catch (\Exception $e) { /* ignore */ }

                foreach ($map as $dbid => $data) {
                    $groups = $data['groups'];
                    if (empty($groups)) continue;
                    // Category: best priority from user's groups (for sorting)
                    $cat = $getBestPriority($groups);
                    $nick = null;
                    $country = null;
                    $isOnline = false;
                    
                    // Try to get data from online client (highest priority)
                    $online = CacheManager::i()->getClient($dbid);
                    if ($online) {
                        $isOnline = true;
                        if (isset($online['client_nickname'])) { $nick = (string) $online['client_nickname']; }
                        if (!empty($online['client_country'])) { $country = (string) $online['client_country']; }
                        
                        // Cache online data for offline use (similar to viewer/profile behavior)
                        if ($nick || $country) {
                            try {
                                $lastSeenCache = new PhpFileCache(__CACHE_DIR, "profile_last_seen");
                                $cacheData = $lastSeenCache->retrieve("u_" . (int) $dbid);
                                if (!is_array($cacheData)) {
                                    $cacheData = [];
                                }
                                if ($nick) { $cacheData['nickname'] = $nick; }
                                if ($country) { $cacheData['country'] = $country; }
                                $cacheData['ts'] = time();
                                $lastSeenCache->store("u_" . (int) $dbid, $cacheData, 31536000); // 365 days
                            } catch (\Exception $e) { /* ignore cache errors */ }
                        }
                    }
                    
                    // Fallback to profile DB data if still missing
                    if (!$nick || !$country) {
                        if (isset($profilesById[$dbid])) {
                            if (!$nick && !empty($profilesById[$dbid]['nickname'])) { $nick = $profilesById[$dbid]['nickname']; }
                            if (!$country && !empty($profilesById[$dbid]['country'])) { $country = $profilesById[$dbid]['country']; }
                        }
                    }
                    
                    // Final fallback: use cached "last seen" data from when user was online
                    if (!$nick || !$country) {
                        try {
                            $lastSeenCache = new PhpFileCache(__CACHE_DIR, "profile_last_seen");
                            $cached = $lastSeenCache->retrieve("u_" . (int) $dbid);
                            if (is_array($cached)) {
                                if (!$nick && !empty($cached['nickname'])) { $nick = (string) $cached['nickname']; }
                                if (!$country && !empty($cached['country'])) { $country = (string) $cached['country']; }
                            }
                        } catch (\Exception $e) { /* ignore */ }
                    }
                    
                    $members[] = [
                        'cldbid' => (int) $dbid,
                        'nickname' => $nick ?: ('User #' . $dbid),
                        'country' => $country ?: null,
                        'cat' => $cat,
                        'isOnline' => $isOnline,
                    ];
                }
            }
        } catch (\Exception $e) { /* ignore */ }
    }

    // Fallback to profiles table if TS server not reachable
    if (empty($members)) {
        try {
            $rows = $db->select("profiles", ["cldbid", "nickname", "country", "servergroups"], ["ORDER" => ["cldbid" => "ASC"]]);
            foreach ($rows as $r) {
                $sg = isset($r["servergroups"]) ? (string) $r["servergroups"] : "";
                $ids = array_values(array_filter(array_map(function ($x) { return (int) trim($x); }, explode(",", $sg)), function ($v) { return $v > 0; }));
                if (empty($ids)) continue;
                // Check if user has any of the configured member groups
                $matchingGroups = array_intersect($ids, $memberGroups);
                if (empty($matchingGroups)) continue;
                $cat = $getBestPriority(array_values($matchingGroups));
                $dbid = (int) $r["cldbid"];
                $nick = isset($r["nickname"]) && $r["nickname"] !== '' ? (string) $r["nickname"] : null;
                $country = isset($r['country']) && $r['country'] !== '' ? (string) $r['country'] : null;
                
                // Try to use cached "last seen" data as fallback (from when user was online)
                if (!$nick || !$country) {
                    try {
                        $lastSeenCache = new \Wruczek\PhpFileCache\PhpFileCache(__CACHE_DIR, "profile_last_seen");
                        $cached = $lastSeenCache->retrieve("u_" . $dbid);
                        if (is_array($cached)) {
                            if (!$nick && !empty($cached['nickname'])) { $nick = (string) $cached['nickname']; }
                            if (!$country && !empty($cached['country'])) { $country = (string) $cached['country']; }
                        }
                    } catch (\Exception $e) { /* ignore */ }
                }
                
                $members[] = [
                    "cldbid" => $dbid,
                    "nickname" => $nick ?: ("User #" . $dbid),
                    "country" => $country ?: null,
                    "cat" => $cat,
                    "isOnline" => false,
                ];
            }
        } catch (\Exception $e) { /* ignore */ }
    }
    
    // Store in cache for 30 seconds to keep data fresh
    try {
        $membersCache->store($cacheKey, $members, 30);
    } catch (\Exception $e) { /* ignore cache errors */ }
}

// Sort: by category (priority order: 532, 556, 543, 533, 1005-996), then by cldbid ascending
$members && usort($members, function ($a, $b) {
    if ($a["cat"] === $b["cat"]) {
        return $a["cldbid"] <=> $b["cldbid"];
    }
    return $a["cat"] <=> $b["cat"];
});

// Get configurable rank badge range from database, default to 996-1005
$rankBadgeRange = Config::get("rank_badge_range", ["min" => 996, "max" => 1005]);
$rankMin = isset($rankBadgeRange['min']) ? (int)$rankBadgeRange['min'] : 996;
$rankMax = isset($rankBadgeRange['max']) ? (int)$rankBadgeRange['max'] : 1005;

// Enrich all members with rank icon and server groups (configurable group id range)
try {
    $serverGroups = CacheManager::i()->getServerGroupList();
} catch (\Exception $e) { $serverGroups = null; }

if (!empty($members) && $serverGroups) {
    $allIds = array_map(function ($m) { return (int) $m['cldbid']; }, $members);
    
    // Get server groups from database profiles (already contains the data)
    $profileSgById = [];
    try {
        $rows = $db->select('profiles', ['cldbid', 'servergroups'], ['cldbid' => $allIds]);
        foreach ($rows as $r) { $profileSgById[(int)$r['cldbid']] = (string) $r['servergroups']; }
    } catch (\Exception $e) { /* ignore */ }

    // Cache for individual server group lookups (cache for 30 seconds)
    $sgCache = new PhpFileCache(__CACHE_DIR, "member_servergroups");

    foreach ($members as &$m) {
        $dbid = (int) $m['cldbid'];
        $sgids = [];
        
        // Priority 1: Get live server groups from online client
        if (!empty($m['isOnline'])) {
            try {
                $online = CacheManager::i()->getClient($dbid);
                if ($online && isset($online['client_servergroups'])) {
                    $sgStr = (string) $online['client_servergroups'];
                    $sgids = array_values(array_filter(array_map(function ($x) { return (int) trim($x); }, explode(',', $sgStr)), function ($v) { return $v > 0; }));
                    
                    // Store fresh online data in cache for 30 seconds
                    if (!empty($sgids)) {
                        try {
                            $sgCache->store("sg_" . $dbid, $sgids, 30);
                        } catch (\Exception $e) { /* ignore */ }
                    }
                }
            } catch (\Exception $e) { /* ignore */ }
        }
        
        // Priority 2: Try to get from cache (if user is offline or online fetch failed)
        if (empty($sgids)) {
            try {
                $cached = $sgCache->retrieve("sg_" . $dbid);
                if (is_array($cached)) {
                    $sgids = $cached;
                }
            } catch (\Exception $e) { /* cache miss, will fetch below */ }
        }
        
        // Priority 3: Fallback to database profile
        if (empty($sgids) && isset($profileSgById[$dbid]) && $profileSgById[$dbid] !== '') {
            $sgids = array_values(array_filter(array_map(function ($x) { return (int) trim($x); }, explode(',', $profileSgById[$dbid])), function ($v) { return $v > 0; }));
            
            // Store in cache for 30 seconds
            try {
                $sgCache->store("sg_" . $dbid, $sgids, 30);
            } catch (\Exception $e) { /* ignore */ }
        }
        
        // Store all server groups for display
        $m['servergroups'] = $sgids;
        
        // Find rank icon from groups in range 996-1005
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
