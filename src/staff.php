<?php

use Wruczek\TSWebsite\CacheManager;
use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;
use Wruczek\PhpFileCache\PhpFileCache;

require_once __DIR__ . "/private/php/load.php";

$db = DatabaseUtils::i()->getDb();

// Define staff groups with their names
$staffGroups = [
    532 => 'CM',      // Community Manager
    556 => 'Admin'    // Administrator
];

// Define VIP group
$vipGroup = 542; // VIP group ID

$staff = [];
$vipMembers = [];
$tsConnected = false;

try {
    $tsConnected = TeamSpeakUtils::i()->checkTSConnection();
} catch (\Exception $e) {
    $tsConnected = false;
}

// Function to fetch members by group IDs
function fetchMembersByGroups($groupIds, $db, $node = null, $serverGroups = null) {
    $members = [];
    $clientGroupMap = [];
    
    // Normalize groupIds to array
    if (!is_array($groupIds)) {
        $groupIds = [$groupIds];
    }
    
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
            $rows = $db->select('profiles', ['cldbid', 'nickname', 'country', 'avatar_url', 'socials_json', 'discord', 'steam', 'twitter', 'youtube', 'github'], ['cldbid' => $ids]);
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
        $groupIcon = null;
        $groupName = null;
        
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
            
            // Get social links - parse JSON from socials_json column
            $socialData = null;
            if (!empty($profile['socials_json'])) {
                $socialData = json_decode($profile['socials_json'], true);
            }
            
            if (is_array($socialData)) {
                $socialLinks = $socialData;
            } else {
                // Fallback to individual columns
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
        }
        
        // Get group icon and name from the highest priority group
        if (!empty($memberGroupIds) && $serverGroups) {
            $primaryGroup = $memberGroupIds[0]; // First group in their list
            if (isset($serverGroups[$primaryGroup])) {
                if (!empty($serverGroups[$primaryGroup]['iconid'])) {
                    $groupIcon = (int) $serverGroups[$primaryGroup]['iconid'];
                }
                if (!empty($serverGroups[$primaryGroup]['name'])) {
                    $groupName = (string) $serverGroups[$primaryGroup]['name'];
                }
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
            'socialLinks' => $socialLinks,
            'groupIcon' => $groupIcon,
            'groupName' => $groupName
        ];
    }
    
    return $members;
}

// Get server groups list first
$serverGroups = null;
try {
    $serverGroups = CacheManager::i()->getServerGroupList();
} catch (\Exception $e) {
    // Continue without server groups
}

if ($tsConnected) {
    try {
        $node = TeamSpeakUtils::i()->getTSNodeServer();
        
        // Fetch staff members (groups 532 and 556)
        $staff = fetchMembersByGroups(array_keys($staffGroups), $db, $node, $serverGroups);
        
        // Fetch VIP members (group 542)
        $vipMembers = fetchMembersByGroups($vipGroup, $db, $node, $serverGroups);
        
    } catch (\Exception $e) {
        // Fall through to database fallback
    }
}

// Fallback to database if TeamSpeak is not available
if (empty($staff) && empty($vipMembers)) {
    try {
        $rows = $db->select("profiles", "*", ["ORDER" => ["cldbid" => "ASC"]]);
        foreach ($rows as $r) {
            $sg = isset($r["servergroups"]) ? (string) $r["servergroups"] : "";
            $sgids = array_values(array_filter(
                array_map(function ($x) { return (int) trim($x); }, explode(",", $sg)),
                function ($v) { return $v > 0; }
            ));
            
            if (empty($sgids)) continue;
            
            // Get social links from socials_json column
            $socialLinks = [];
            $socialData = null;
            if (!empty($r['socials_json'])) {
                $socialData = json_decode($r['socials_json'], true);
            }
            
            if (is_array($socialData)) {
                $socialLinks = $socialData;
            } else {
                // Fallback to individual columns
                if (!empty($r['discord'])) $socialLinks['discord'] = (string) $r['discord'];
                if (!empty($r['steam'])) $socialLinks['steam'] = (string) $r['steam'];
                if (!empty($r['twitter'])) $socialLinks['twitter'] = (string) $r['twitter'];
                if (!empty($r['youtube'])) $socialLinks['youtube'] = (string) $r['youtube'];
                if (!empty($r['github'])) $socialLinks['github'] = (string) $r['github'];
            }
            
            // Get group icon and name
            $groupIcon = null;
            $groupName = null;
            if (!empty($sgids) && $serverGroups) {
                // Find the first matching staff or VIP group
                foreach ($sgids as $gid) {
                    if (isset($staffGroups[$gid]) || $gid == $vipGroup) {
                        if (isset($serverGroups[$gid])) {
                            if (!empty($serverGroups[$gid]['iconid'])) {
                                $groupIcon = (int) $serverGroups[$gid]['iconid'];
                            }
                            if (!empty($serverGroups[$gid]['name'])) {
                                $groupName = (string) $serverGroups[$gid]['name'];
                            }
                        }
                        break;
                    }
                }
            }
            
            $memberData = [
                'cldbid' => (int) $r['cldbid'],
                'nickname' => isset($r['nickname']) ? (string) $r['nickname'] : ('User #' . $r['cldbid']),
                'country' => isset($r['country']) ? (string) $r['country'] : null,
                'isOnline' => false,
                'groups' => $sgids,
                'avatarUrl' => isset($r['avatar_url']) ? (string) $r['avatar_url'] : null,
                'socialLinks' => $socialLinks,
                'groupIcon' => $groupIcon,
                'groupName' => $groupName
            ];
            
            // Check if user has any staff groups
            $matchingStaffGroups = array_intersect($sgids, array_keys($staffGroups));
            if (!empty($matchingStaffGroups)) {
                $staff[] = $memberData;
            }
            
            // Check if user has VIP group
            if (in_array($vipGroup, $sgids)) {
                $vipMembers[] = $memberData;
            }
        }
    } catch (\Exception $e) {
        // Continue with empty lists
    }
}

// Sort staff: group 532 (CM) first, then 556 (Admin)
if (!empty($staff)) {
    usort($staff, function($a, $b) {
        $aHas532 = in_array(532, $a['groups']);
        $bHas532 = in_array(532, $b['groups']);
        
        if ($aHas532 && !$bHas532) return -1;
        if (!$aHas532 && $bHas532) return 1;
        
        return $a['cldbid'] <=> $b['cldbid'];
    });
}

// Sort VIP members by cldbid
if (!empty($vipMembers)) {
    usort($vipMembers, function($a, $b) {
        return $a['cldbid'] <=> $b['cldbid'];
    });
}

TemplateUtils::i()->renderTemplate("staff", [
    "staff" => $staff,
    "vipMembers" => $vipMembers,
    "serverGroups" => $serverGroups,
    "staffGroups" => $staffGroups,
    "vipGroup" => $vipGroup,
    "navActiveIndex" => 8
]);
