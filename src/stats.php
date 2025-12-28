<?php

use Wruczek\TSWebsite\CacheManager;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;

require_once __DIR__ . "/private/php/load.php";

$db = DatabaseUtils::i()->getDb();

// Get server groups for icon display
$serverGroupsData = [];
try {
    $serverGroups = CacheManager::i()->getServerGroupList();
    if ($serverGroups) {
        $serverGroupsData = $serverGroups;
    }
} catch (\Exception $e) { /* ignore */ }

// Get all members from profiles
$allMembers = [];
try {
    $rows = $db->select("profiles", ["cldbid", "nickname", "country", "servergroups"], ["ORDER" => ["cldbid" => "ASC"]]);
    foreach ($rows as $r) {
        $allMembers[] = [
            'cldbid' => (int) $r['cldbid'],
            'nickname' => (string) ($r['nickname'] ?? ''),
            'country' => (string) ($r['country'] ?? ''),
            'servergroups' => (string) ($r['servergroups'] ?? '')
        ];
    }
} catch (\Exception $e) { /* ignore */ }

// Get online users from TeamSpeak
$onlineUsers = [];
$onlineCount = 0;
$offlineCount = count($allMembers);
try {
    if (TeamSpeakUtils::i()->checkTSConnection()) {
        $clients = CacheManager::i()->getClientList();
        if ($clients) {
            foreach ($clients as $client) {
                if (!empty($client['client_database_id'])) {
                    $onlineUsers[] = (int) $client['client_database_id'];
                }
            }
            $onlineCount = count($onlineUsers);
            $offlineCount = max(0, count($allMembers) - $onlineCount);
        }
    }
} catch (\Exception $e) { /* ignore */ }

// Country Statistics - Top 10 countries
$countryStats = [];
foreach ($allMembers as $m) {
    if (!empty($m['country'])) {
        $country = strtoupper((string) $m['country']);
        if (!isset($countryStats[$country])) {
            $countryStats[$country] = 0;
        }
        $countryStats[$country]++;
    }
}
arsort($countryStats);
$topCountries = array_slice($countryStats, 0, 10, true);

// Server Group Distribution (top 10 most common groups)
$groupStats = [];
foreach ($allMembers as $m) {
    if (!empty($m['servergroups'])) {
        $groups = array_filter(array_map('trim', explode(',', $m['servergroups'])));
        foreach ($groups as $gid) {
            if (is_numeric($gid)) {
                $gid = (int) $gid;
                if (!isset($groupStats[$gid])) {
                    $groupStats[$gid] = 0;
                }
                $groupStats[$gid]++;
            }
        }
    }
}
arsort($groupStats);
$topGroups = array_slice($groupStats, 0, 10, true);

// Online vs Offline stats
$onlineOfflineData = [
    'Online' => $onlineCount,
    'Offline' => $offlineCount
];

// Total stats
$totalStats = [
    'totalUsers' => count($allMembers),
    'onlineUsers' => $onlineCount,
    'offlineUsers' => $offlineCount,
    'countriesCount' => count($countryStats),
    'groupsCount' => count($groupStats)
];

// Prepare chart data as JSON
$countryLabels = json_encode(array_keys($topCountries));
$countryValues = json_encode(array_values($topCountries));

// Prepare group data with icon information
$topGroupsDetailed = [];
foreach (array_keys($topGroups) as $gid) {
    $groupInfo = [
        'id' => $gid,
        'name' => "Group " . $gid,
        'iconid' => null
    ];
    if (isset($serverGroupsData[$gid])) {
        $groupInfo['name'] = $serverGroupsData[$gid]['name'];
        $groupInfo['iconid'] = isset($serverGroupsData[$gid]['iconid']) ? (int) $serverGroupsData[$gid]['iconid'] : null;
    }
    $topGroupsDetailed[] = $groupInfo;
}

$groupLabels = json_encode(array_map(function($g) { return $g['name']; }, $topGroupsDetailed));
$groupValues = json_encode(array_values($topGroups));
$groupData = json_encode($topGroupsDetailed);

$onlineOfflineLabels = json_encode(array_keys($onlineOfflineData));
$onlineOfflineValues = json_encode(array_values($onlineOfflineData));

// User growth simulation (last 12 months)
// Note: This is simulated data - you would need to track actual registration dates in your database
$months = [];
$userCounts = [];
$currentMonth = date('n');
$currentYear = date('Y');
for ($i = 11; $i >= 0; $i--) {
    $m = $currentMonth - $i;
    $y = $currentYear;
    if ($m <= 0) {
        $m += 12;
        $y--;
    }
    $months[] = date('M Y', mktime(0, 0, 0, $m, 1, $y));
    // Simulate growth - in real implementation, query database for actual data
    $userCounts[] = max(0, count($allMembers) - ($i * 5));
}
$growthLabels = json_encode($months);
$growthValues = json_encode($userCounts);

TemplateUtils::i()->renderTemplate("stats", [
    "title" => "Statistics",
    "navActiveIndex" => 7,
    "totalStats" => $totalStats,
    "countryLabels" => $countryLabels,
    "countryValues" => $countryValues,
    "groupLabels" => $groupLabels,
    "groupValues" => $groupValues,
    "groupData" => $groupData,
    "onlineOfflineLabels" => $onlineOfflineLabels,
    "onlineOfflineValues" => $onlineOfflineValues,
    "growthLabels" => $growthLabels,
    "growthValues" => $growthValues,
]);
