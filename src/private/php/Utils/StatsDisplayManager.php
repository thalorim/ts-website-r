<?php

namespace Wruczek\TSWebsite\Utils;

use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\CacheManager;

/**
 * Class StatsDisplayManager
 * Manages TeamSpeak server statistics, leaderboards and tracking
 * @package Wruczek\TSWebsite\Utils
 */
class StatsDisplayManager {

    use SingletonTait;

    /**
     * Get all stats display configurations
     * @return array
     */
    public function getConfigurations(): array {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            return $db->select("channel_stats_display", "*", ["enabled" => 1]);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Add a new stats display configuration
     * @param int $channelId
     * @param string $displayType Type: 'connections', 'online_time', 'combined', 'server_stats'
     * @param int $topCount How many users to show in leaderboard
     * @return bool
     */
    public function addConfiguration(int $channelId, string $displayType = 'combined', int $topCount = 10): bool {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $db->insert("channel_stats_display", [
                "channel_id" => $channelId,
                "display_type" => $displayType,
                "top_count" => $topCount,
                "enabled" => 1
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update a stats display configuration
     * @param int $id
     * @param string $displayType
     * @param int $topCount
     * @return bool
     */
    public function updateConfiguration(int $id, string $displayType, int $topCount): bool {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $db->update("channel_stats_display", [
                "display_type" => $displayType,
                "top_count" => $topCount
            ], ["id" => $id]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove a stats display configuration
     * @param int $id
     * @return bool
     */
    public function removeConfiguration(int $id): bool {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $db->delete("channel_stats_display", ["id" => $id]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Record a user connection
     * @param int $cldbid
     * @param string $nickname
     * @param string $cluid
     * @return bool
     */
    public function recordConnection(int $cldbid, string $nickname, string $cluid): bool {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $existing = $db->get("user_statistics", "*", ["cldbid" => $cldbid]);
            $today = date('Y-m-d');
            $dayOfWeek = date('w'); // 0 (Sunday) to 6 (Saturday)
            
            if ($existing) {
                // Parse connection dates
                $connectionDates = !empty($existing['connection_dates']) 
                    ? json_decode($existing['connection_dates'], true) 
                    : [];
                
                // Parse day statistics
                $dayStats = !empty($existing['day_statistics']) 
                    ? json_decode($existing['day_statistics'], true) 
                    : array_fill(0, 7, 0);
                
                // Check if this is first connection today
                if (!in_array($today, $connectionDates)) {
                    $connectionDates[] = $today;
                    $connectedDays = count($connectionDates);
                    
                    // Keep only last 400 days to prevent huge JSON
                    if (count($connectionDates) > 400) {
                        $connectionDates = array_slice($connectionDates, -400);
                    }
                    
                    // Update day of week statistics
                    $dayStats[$dayOfWeek] = ($dayStats[$dayOfWeek] ?? 0) + 1;
                    
                    // Calculate streaks
                    $streakData = $this->calculateStreaks($connectionDates);
                    
                    $updateData = [
                        "total_connections" => $existing['total_connections'] + 1,
                        "last_seen" => date('Y-m-d H:i:s'),
                        "last_nickname" => $nickname,
                        "cluid" => $cluid,
                        "connected_days" => $connectedDays,
                        "connection_dates" => json_encode($connectionDates),
                        "current_streak" => $streakData['current'],
                        "longest_streak" => $streakData['longest'],
                        "longest_streak_start" => $streakData['longest_start'],
                        "longest_streak_end" => $streakData['longest_end'],
                        "day_statistics" => json_encode($dayStats)
                    ];
                } else {
                    // Same day connection
                    $updateData = [
                        "total_connections" => $existing['total_connections'] + 1,
                        "last_seen" => date('Y-m-d H:i:s'),
                        "last_nickname" => $nickname,
                        "cluid" => $cluid
                    ];
                }
                
                $db->update("user_statistics", $updateData, ["cldbid" => $cldbid]);
            } else {
                // Insert new - check if user exists in profiles table
                $existingConnections = 0;
                try {
                    $profile = $db->get("profiles", "connections", ["cldbid" => $cldbid]);
                    if ($profile && isset($profile['connections'])) {
                        $existingConnections = (int)$profile['connections'];
                    }
                } catch (\Exception $e) {
                    // Table might not exist
                }
                
                // Initialize day statistics
                $dayStats = array_fill(0, 7, 0);
                $dayStats[$dayOfWeek] = 1;
                
                $db->insert("user_statistics", [
                    "cldbid" => $cldbid,
                    "cluid" => $cluid,
                    "last_nickname" => $nickname,
                    "total_connections" => $existingConnections + 1,
                    "total_online_time" => 0,
                    "first_seen" => date('Y-m-d H:i:s'),
                    "last_seen" => date('Y-m-d H:i:s'),
                    "connected_days" => 1,
                    "connection_dates" => json_encode([$today]),
                    "current_streak" => 1,
                    "longest_streak" => 1,
                    "longest_streak_start" => $today,
                    "longest_streak_end" => $today,
                    "day_statistics" => json_encode($dayStats)
                ]);
            }
            return true;
        } catch (\Exception $e) {
            error_log("Failed to record connection: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calculate streak statistics from connection dates
     * @param array $dates Array of Y-m-d date strings
     * @return array
     */
    private function calculateStreaks(array $dates): array {
        if (empty($dates)) {
            return [
                'current' => 0,
                'longest' => 0,
                'longest_start' => null,
                'longest_end' => null
            ];
        }
        
        sort($dates);
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $currentStreak = 0;
        $longestStreak = 0;
        $longestStart = null;
        $longestEnd = null;
        
        $tempStreak = 1;
        $tempStart = $dates[0];
        
        for ($i = 1; $i < count($dates); $i++) {
            $prevDate = new \DateTime($dates[$i - 1]);
            $currDate = new \DateTime($dates[$i]);
            $diff = $prevDate->diff($currDate)->days;
            
            if ($diff === 1) {
                // Consecutive day
                $tempStreak++;
            } else {
                // Streak broken, check if it was the longest
                if ($tempStreak > $longestStreak) {
                    $longestStreak = $tempStreak;
                    $longestStart = $tempStart;
                    $longestEnd = $dates[$i - 1];
                }
                $tempStreak = 1;
                $tempStart = $dates[$i];
            }
        }
        
        // Check final streak
        if ($tempStreak > $longestStreak) {
            $longestStreak = $tempStreak;
            $longestStart = $tempStart;
            $longestEnd = $dates[count($dates) - 1];
        }
        
        // Calculate current streak
        $lastDate = $dates[count($dates) - 1];
        if ($lastDate === $today || $lastDate === $yesterday) {
            $currentStreak = 1;
            for ($i = count($dates) - 2; $i >= 0; $i--) {
                $prevDate = new \DateTime($dates[$i]);
                $currDate = new \DateTime($dates[$i + 1]);
                if ($prevDate->diff($currDate)->days === 1) {
                    $currentStreak++;
                } else {
                    break;
                }
            }
        }
        
        return [
            'current' => $currentStreak,
            'longest' => $longestStreak,
            'longest_start' => $longestStart,
            'longest_end' => $longestEnd
        ];
    }

    /**
     * Update online time for user
     * @param int $cldbid
     * @param int $seconds
     * @param array $additionalData Optional additional data to update
     * @return bool
     */
    public function addOnlineTime(int $cldbid, int $seconds, array $additionalData = []): bool {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $existing = $db->get("user_statistics", "*", ["cldbid" => $cldbid]);
            
            if ($existing) {
                $newTime = $existing['total_online_time'] + $seconds;
                $newHours = round($newTime / 3600, 2);
                
                // Calculate average session time
                $totalConnections = (int)$existing['total_connections'];
                $avgSessionTime = $totalConnections > 0 ? floor($newTime / $totalConnections) : 0;
                
                $updateData = [
                    "total_online_time" => $newTime,
                    "total_online_hours" => $newHours,
                    "average_session_time" => $avgSessionTime,
                    "last_seen" => date('Y-m-d H:i:s')
                ];
                
                // Add any additional tracking data
                foreach ($additionalData as $key => $value) {
                    $updateData[$key] = $value;
                }
                
                $db->update("user_statistics", $updateData, ["cldbid" => $cldbid]);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            error_log("Failed to add online time: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Start user session (track session start time)
     * @param int $cldbid
     * @return bool
     */
    public function startSession(int $cldbid): bool {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $db->update("user_statistics", [
                "current_session_start" => date('Y-m-d H:i:s')
            ], ["cldbid" => $cldbid]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * End user session and update longest session if needed
     * @param int $cldbid
     * @return bool
     */
    public function endSession(int $cldbid): bool {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $user = $db->get("user_statistics", ["current_session_start", "longest_session"], ["cldbid" => $cldbid]);
            
            if ($user && $user['current_session_start']) {
                $sessionStart = strtotime($user['current_session_start']);
                $sessionLength = time() - $sessionStart;
                
                $updateData = ["current_session_start" => null];
                
                // Update longest session if this one is longer
                if ($sessionLength > (int)$user['longest_session']) {
                    $updateData['longest_session'] = $sessionLength;
                }
                
                $db->update("user_statistics", $updateData, ["cldbid" => $cldbid]);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get top users by connections
     * @param int $limit
     * @return array
     */
    public function getTopByConnections(int $limit = 10): array {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            return $db->select("user_statistics", "*", [
                "ORDER" => ["total_connections" => "DESC"],
                "LIMIT" => $limit
            ]);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get top users by online time
     * @param int $limit
     * @return array
     */
    public function getTopByOnlineTime(int $limit = 10): array {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            return $db->select("user_statistics", "*", [
                "ORDER" => ["total_online_time" => "DESC"],
                "LIMIT" => $limit
            ]);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get server statistics
     * @return array
     */
    public function getServerStats(): array {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $totalUsers = $db->count("user_statistics");
            $totalConnections = $db->sum("user_statistics", "total_connections");
            $totalOnlineTime = $db->sum("user_statistics", "total_online_time");
            
            // Get current online users
            $onlineClients = CacheManager::i()->getClientList();
            $currentOnline = count($onlineClients);
            
            return [
                'total_users' => $totalUsers ?? 0,
                'total_connections' => $totalConnections ?? 0,
                'total_online_time' => $totalOnlineTime ?? 0,
                'current_online' => $currentOnline,
                'avg_connections' => $totalUsers > 0 ? round($totalConnections / $totalUsers, 2) : 0,
                'avg_online_time' => $totalUsers > 0 ? round($totalOnlineTime / $totalUsers, 2) : 0
            ];
        } catch (\Exception $e) {
            return [
                'total_users' => 0,
                'total_connections' => 0,
                'total_online_time' => 0,
                'current_online' => 0,
                'avg_connections' => 0,
                'avg_online_time' => 0
            ];
        }
    }

    /**
     * Update channel description with stats/leaderboard
     * @param int $channelId
     * @param string $displayType
     * @param int $topCount
     * @return bool
     */
    public function updateChannelDescription(int $channelId, string $displayType = 'combined', int $topCount = 10): bool {
        try {
            if (!TeamSpeakUtils::i()->checkTSConnection()) {
                return false;
            }

            $tsServer = TeamSpeakUtils::i()->getTSNodeServer();
            
            // Build description based on display type
            $description = $this->buildStatsDescription($displayType, $topCount);

            // Update channel description
            try {
                $channel = $tsServer->channelGetById($channelId);
                $channel->modify(['channel_description' => $description]);
            } catch (\Exception $e1) {
                try {
                    $tsServer->execute("channeledit", [
                        "cid" => $channelId,
                        "channel_description" => $description
                    ]);
                } catch (\Exception $e2) {
                    $tsServer->request("channeledit cid=" . (int)$channelId . 
                        " channel_description=" . $tsServer->escape($description));
                }
            }

            // Update last updated timestamp
            try {
                $db = DatabaseUtils::i()->getDb();
                $db->update("channel_stats_display", [
                    "last_updated" => date('Y-m-d H:i:s')
                ], ["channel_id" => $channelId]);
            } catch (\Exception $e) {
                // Ignore timestamp errors
            }

            return true;
        } catch (\Exception $e) {
            error_log("Failed to update stats channel description: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build BBCode formatted stats description
     * @param string $displayType
     * @param int $topCount
     * @return string
     */
    private function buildStatsDescription(string $displayType, int $topCount): string {
        $baseUrl = $this->getBaseUrl();
        $description = "";
        
        switch ($displayType) {
            case 'connections':
                $description = $this->buildConnectionsLeaderboard($topCount, $baseUrl);
                break;
            case 'online_time':
                $description = $this->buildOnlineTimeLeaderboard($topCount, $baseUrl);
                break;
            case 'server_stats':
                $description = $this->buildServerStats($baseUrl);
                break;
            case 'detailed_stats':
                $description = $this->buildDetailedUserStats($topCount, $baseUrl);
                break;
            case 'combined':
            default:
                $description = $this->buildCombinedStats($topCount, $baseUrl);
                break;
        }
        
        // Add footer
        $timestamp = date('Y-m-d H:i:s');
        $description .= "\n[hr]\n";
        $description .= "[right][size=8]Last updated: {$timestamp}[/size][/right]";
        
        return $description;
    }
    
    /**
     * Build detailed user statistics display
     * @param int $topCount
     * @param string $baseUrl
     * @return string
     */
    private function buildDetailedUserStats(int $topCount, string $baseUrl): string {
        $topUsers = $this->getTopByOnlineTime($topCount);
        
        $description = "[center][size=16][b]📊 Detailed User Statistics[/b][/size][/center]\n\n";
        
        if (empty($topUsers)) {
            $description .= "[center][i]No statistics available yet.[/i][/center]\n";
            return $description;
        }
        
        $rank = 1;
        foreach ($topUsers as $user) {
            $cldbid = (int)$user['cldbid'];
            $nickname = $this->escapeBBCode($user['last_nickname'] ?? 'Unknown');
            
            // Get medal
            $medal = $this->getRankMedal($rank);
            
            // Profile link
            $profileUrl = "{$baseUrl}/profile.php?cldbid={$cldbid}";
            $userLink = "[url={$profileUrl}]{$nickname}[/url]";
            
            // Format dates
            $firstConnected = isset($user['first_seen']) && $user['first_seen'] 
                ? date('jS F, Y', strtotime($user['first_seen']))
                : 'Unknown';
                
            $lastOnline = isset($user['last_seen']) && $user['last_seen']
                ? date('jS F, Y, g:ia', strtotime($user['last_seen']))
                : 'Unknown';
            
            // Total time in hours
            $totalHours = isset($user['total_online_hours']) 
                ? number_format($user['total_online_hours'], 0)
                : number_format((int)$user['total_online_time'] / 3600, 0);
            
            // Connected days
            $connectedDays = (int)($user['connected_days'] ?? 0);
            
            // Calculate percentage
            $percentageDays = 0;
            $totalDays = 0;
            if (isset($user['first_seen']) && $user['first_seen']) {
                $firstDate = new \DateTime($user['first_seen']);
                $now = new \DateTime();
                $totalDays = $firstDate->diff($now)->days + 1;
                $percentageDays = $connectedDays > 0 && $totalDays > 0 
                    ? round(($connectedDays / $totalDays) * 100, 2) 
                    : 0;
            }
            
            // Most consecutive days
            $longestStreak = (int)($user['longest_streak'] ?? 0);
            $streakStart = isset($user['longest_streak_start']) && $user['longest_streak_start']
                ? date('jS F, Y', strtotime($user['longest_streak_start']))
                : '';
            $streakEnd = isset($user['longest_streak_end']) && $user['longest_streak_end']
                ? date('jS F, Y', strtotime($user['longest_streak_end']))
                : '';
            
            // Most popular day
            $popularDay = 'Unknown';
            $popularCount = 0;
            if (!empty($user['day_statistics'])) {
                $dayStats = json_decode($user['day_statistics'], true);
                if (is_array($dayStats)) {
                    $maxDay = array_search(max($dayStats), $dayStats);
                    $popularCount = max($dayStats);
                    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    $popularDay = $days[$maxDay] ?? 'Unknown';
                }
            }
            
            // Build entry
            $description .= "[size=12]{$medal} [b]#{$rank} {$userLink}[/b][/size]\n\n";
            $description .= "[size=10][b]first connected:[/b] {$firstConnected}[/size]\n";
            $description .= "[size=10][b]last online:[/b] {$lastOnline}[/size]\n";
            $description .= "[size=10][b]total time:[/b] {$totalHours} hrs[/size]\n";
            $description .= "[size=10][b]connected days:[/b] {$connectedDays} days[/size]\n";
            
            if ($totalDays > 0) {
                $description .= "[size=10][b]percentage days:[/b] {$percentageDays}% ({$connectedDays}/{$totalDays})[/size]\n";
            }
            
            if ($longestStreak > 0 && $streakStart && $streakEnd) {
                $description .= "[size=10][b]most consecutive days:[/b] {$longestStreak} ({$streakStart} to {$streakEnd})[/size]\n";
            }
            
            if ($popularCount > 0) {
                $description .= "[size=10][b]most popular day:[/b] {$popularDay} ({$popularCount})[/size]\n";
            }
            
            $description .= "\n[hr]\n\n";
            
            $rank++;
        }
        
        return $description;
    }

    /**
     * Build connections leaderboard
     * @param int $topCount
     * @param string $baseUrl
     * @return string
     */
    private function buildConnectionsLeaderboard(int $topCount, string $baseUrl): string {
        $topUsers = $this->getTopByConnections($topCount);
        
        $description = "[center][size=16][b]🏆 Top Connections Leaderboard[/b][/size][/center]\n\n";
        
        if (empty($topUsers)) {
            $description .= "[center][i]No statistics available yet.[/i][/center]\n";
            return $description;
        }
        
        $rank = 1;
        foreach ($topUsers as $user) {
            $cldbid = (int)$user['cldbid'];
            $nickname = $this->escapeBBCode($user['last_nickname'] ?? 'Unknown');
            $connections = number_format((int)$user['total_connections']);
            $onlineTime = $this->formatTime((int)$user['total_online_time']);
            $lastSeen = $this->formatDate($user['last_seen']);
            
            // Get medal emoji
            $medal = $this->getRankMedal($rank);
            
            // Profile link
            $profileUrl = "{$baseUrl}/profile.php?cldbid={$cldbid}";
            $userLink = "[url={$profileUrl}]{$nickname}[/url]";
            
            // Build entry
            $description .= "[size=11]{$medal} [b]#{$rank}[/b] {$userLink}[/size]\n";
            $description .= "[size=10]   🔄 [b]{$connections}[/b] connections | ⏱️ {$onlineTime} | 👁️ {$lastSeen}[/size]\n\n";
            
            $rank++;
        }
        
        return $description;
    }

    /**
     * Build online time leaderboard
     * @param int $topCount
     * @param string $baseUrl
     * @return string
     */
    private function buildOnlineTimeLeaderboard(int $topCount, string $baseUrl): string {
        $topUsers = $this->getTopByOnlineTime($topCount);
        
        $description = "[center][size=16][b]⏱️ Top Online Time Leaderboard[/b][/size][/center]\n\n";
        
        if (empty($topUsers)) {
            $description .= "[center][i]No statistics available yet.[/i][/center]\n";
            return $description;
        }
        
        $rank = 1;
        foreach ($topUsers as $user) {
            $cldbid = (int)$user['cldbid'];
            $nickname = $this->escapeBBCode($user['last_nickname'] ?? 'Unknown');
            $onlineTime = $this->formatTime((int)$user['total_online_time']);
            $connections = number_format((int)$user['total_connections']);
            $lastSeen = $this->formatDate($user['last_seen']);
            
            // Get medal emoji
            $medal = $this->getRankMedal($rank);
            
            // Profile link
            $profileUrl = "{$baseUrl}/profile.php?cldbid={$cldbid}";
            $userLink = "[url={$profileUrl}]{$nickname}[/url]";
            
            // Build entry
            $description .= "[size=11]{$medal} [b]#{$rank}[/b] {$userLink}[/size]\n";
            $description .= "[size=10]   ⏱️ [b]{$onlineTime}[/b] online | 🔄 {$connections} connections | 👁️ {$lastSeen}[/size]\n\n";
            
            $rank++;
        }
        
        return $description;
    }

    /**
     * Build server statistics display
     * @param string $baseUrl
     * @return string
     */
    private function buildServerStats(string $baseUrl): string {
        $stats = $this->getServerStats();
        
        $description = "[center][size=16][b]📊 Server Statistics[/b][/size][/center]\n\n";
        
        $totalTime = $this->formatTime((int)$stats['total_online_time']);
        $avgTime = $this->formatTime((int)$stats['avg_online_time']);
        
        $description .= "[size=12]👥 [b]Total Unique Users:[/b] " . number_format($stats['total_users']) . "[/size]\n";
        $description .= "[size=12]🟢 [b]Currently Online:[/b] " . number_format($stats['current_online']) . "[/size]\n";
        $description .= "[size=12]🔄 [b]Total Connections:[/b] " . number_format($stats['total_connections']) . "[/size]\n";
        $description .= "[size=12]⏱️ [b]Total Online Time:[/b] {$totalTime}[/size]\n";
        $description .= "[size=12]📈 [b]Avg Connections/User:[/b] " . number_format($stats['avg_connections'], 1) . "[/size]\n";
        $description .= "[size=12]⏰ [b]Avg Time/User:[/b] {$avgTime}[/size]\n\n";
        
        return $description;
    }

    /**
     * Build combined stats (server stats + leaderboards)
     * @param int $topCount
     * @param string $baseUrl
     * @return string
     */
    private function buildCombinedStats(int $topCount, string $baseUrl): string {
        $description = "[center][size=18][b]📊 Server Statistics & Leaderboards[/b][/size][/center]\n\n";
        
        // Server stats
        $stats = $this->getServerStats();
        $totalTime = $this->formatTime((int)$stats['total_online_time']);
        
        $description .= "[center][size=13][b]━━━━━━━━━ Server Overview ━━━━━━━━━[/b][/size][/center]\n";
        $description .= "[center]👥 [b]" . number_format($stats['total_users']) . "[/b] users | ";
        $description .= "🟢 [b]" . number_format($stats['current_online']) . "[/b] online | ";
        $description .= "🔄 [b]" . number_format($stats['total_connections']) . "[/b] connections[/center]\n";
        $description .= "[center]⏱️ [b]{$totalTime}[/b] total time tracked[/center]\n\n";
        
        $description .= "[hr]\n\n";
        
        // Top Connections
        $topConnections = $this->getTopByConnections($topCount);
        $description .= "[center][size=14][b]🏆 Top by Connections[/b][/size][/center]\n\n";
        
        if (!empty($topConnections)) {
            $rank = 1;
            foreach (array_slice($topConnections, 0, 5) as $user) {
                $cldbid = (int)$user['cldbid'];
                $nickname = $this->escapeBBCode($user['last_nickname'] ?? 'Unknown');
                $connections = number_format((int)$user['total_connections']);
                $onlineTime = $this->formatTime((int)$user['total_online_time']);
                $medal = $this->getRankMedal($rank);
                
                $profileUrl = "{$baseUrl}/profile.php?cldbid={$cldbid}";
                $userLink = "[url={$profileUrl}]{$nickname}[/url]";
                
                $description .= "[size=11]{$medal} [b]#{$rank}[/b] {$userLink} • [b]{$connections}[/b] connections • {$onlineTime}[/size]\n";
                $rank++;
            }
            $description .= "\n";
        }
        
        $description .= "[hr]\n\n";
        
        // Top Online Time
        $topTime = $this->getTopByOnlineTime($topCount);
        $description .= "[center][size=14][b]⏱️ Top by Online Time[/b][/size][/center]\n\n";
        
        if (!empty($topTime)) {
            $rank = 1;
            foreach (array_slice($topTime, 0, 5) as $user) {
                $cldbid = (int)$user['cldbid'];
                $nickname = $this->escapeBBCode($user['last_nickname'] ?? 'Unknown');
                $onlineTime = $this->formatTime((int)$user['total_online_time']);
                $connections = number_format((int)$user['total_connections']);
                $medal = $this->getRankMedal($rank);
                
                $profileUrl = "{$baseUrl}/profile.php?cldbid={$cldbid}";
                $userLink = "[url={$profileUrl}]{$nickname}[/url]";
                
                $description .= "[size=11]{$medal} [b]#{$rank}[/b] {$userLink} • [b]{$onlineTime}[/b] • {$connections} connections[/size]\n";
                $rank++;
            }
            $description .= "\n";
        }
        
        return $description;
    }

    /**
     * Get rank medal emoji
     * @param int $rank
     * @return string
     */
    private function getRankMedal(int $rank): string {
        switch ($rank) {
            case 1: return "🥇";
            case 2: return "🥈";
            case 3: return "🥉";
            default: return "  ";
        }
    }

    /**
     * Format seconds to human readable time
     * @param int $seconds
     * @return string
     */
    private function formatTime(int $seconds): string {
        if ($seconds < 60) {
            return $seconds . "s";
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . "m";
        } elseif ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . "h " . $minutes . "m";
        } else {
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            return $days . "d " . $hours . "h";
        }
    }

    /**
     * Format date
     * @param string $date
     * @return string
     */
    private function formatDate($date): string {
        if (empty($date)) return 'Never';
        
        try {
            $timestamp = strtotime($date);
            if ($timestamp === false) return 'Unknown';
            
            $diff = time() - $timestamp;
            
            if ($diff < 60) return 'Just now';
            if ($diff < 3600) return floor($diff / 60) . 'm ago';
            if ($diff < 86400) return floor($diff / 3600) . 'h ago';
            if ($diff < 604800) return floor($diff / 86400) . 'd ago';
            
            return date('M j', $timestamp);
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Escape text for BBCode
     * @param string $text
     * @return string
     */
    private function escapeBBCode(string $text): string {
        return strip_tags(trim($text));
    }

    /**
     * Get base URL
     * @return string
     */
    private function getBaseUrl(): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
        
        $basePath = '';
        if (!empty($documentRoot) && !empty($scriptFilename)) {
            $relativePath = str_replace($documentRoot, '', dirname($scriptFilename));
            
            if (strpos($relativePath, '/src') !== false) {
                $parts = explode('/src', $relativePath);
                $basePath = $parts[0];
            }
        }
        
        $baseUrl = $protocol . '://' . $host . $basePath;
        return rtrim($baseUrl, '/');
    }

    /**
     * Process all stats channel updates
     * @return array
     */
    public function processAllStatsUpdates(): array {
        $stats = [
            'total' => 0,
            'updated' => 0,
            'failed' => 0
        ];

        try {
            $configs = $this->getConfigurations();
            $stats['total'] = count($configs);

            foreach ($configs as $config) {
                $channelId = (int) $config['channel_id'];
                $displayType = $config['display_type'] ?? 'combined';
                $topCount = (int) ($config['top_count'] ?? 10);
                
                $result = $this->updateChannelDescription($channelId, $displayType, $topCount);
                
                if ($result) {
                    $stats['updated']++;
                } else {
                    $stats['failed']++;
                }
            }
        } catch (\Exception $e) {
            error_log("Error processing stats updates: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Add missing columns to existing table
     * @param object $db
     * @param string $tableName
     */
    private function addMissingColumns($db, string $tableName): void {
        try {
            $columns = $db->query("SHOW COLUMNS FROM `{$tableName}`");
            $existingColumns = [];
            while ($col = $columns->fetch(\PDO::FETCH_ASSOC)) {
                $existingColumns[] = $col['Field'];
            }
            
            // Add missing columns
            $columnsToAdd = [
                'total_online_hours' => "ADD COLUMN `total_online_hours` DECIMAL(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total online time in hours'",
                'average_session_time' => "ADD COLUMN `average_session_time` INT(11) NOT NULL DEFAULT '0' COMMENT 'Average session length in seconds'",
                'longest_session' => "ADD COLUMN `longest_session` INT(11) NOT NULL DEFAULT '0' COMMENT 'Longest session in seconds'",
                'current_session_start' => "ADD COLUMN `current_session_start` TIMESTAMP NULL DEFAULT NULL COMMENT 'When current session started'",
                'last_ip' => "ADD COLUMN `last_ip` VARCHAR(45) DEFAULT NULL COMMENT 'Last known IP address'",
                'country_code' => "ADD COLUMN `country_code` VARCHAR(2) DEFAULT NULL COMMENT 'Country code'",
                'peak_clients_seen' => "ADD COLUMN `peak_clients_seen` INT(11) NOT NULL DEFAULT '0' COMMENT 'Max concurrent clients when user was online'",
                'messages_sent' => "ADD COLUMN `messages_sent` INT(11) NOT NULL DEFAULT '0' COMMENT 'Total messages sent (future feature)'",
                'channels_visited' => "ADD COLUMN `channels_visited` TEXT DEFAULT NULL COMMENT 'JSON array of visited channel IDs'",
                'connected_days' => "ADD COLUMN `connected_days` INT(11) NOT NULL DEFAULT '0' COMMENT 'Total unique days user connected'",
                'connection_dates' => "ADD COLUMN `connection_dates` TEXT DEFAULT NULL COMMENT 'JSON array of connection dates (Y-m-d) for tracking'",
                'current_streak' => "ADD COLUMN `current_streak` INT(11) NOT NULL DEFAULT '0' COMMENT 'Current consecutive days streak'",
                'longest_streak' => "ADD COLUMN `longest_streak` INT(11) NOT NULL DEFAULT '0' COMMENT 'Longest consecutive days streak'",
                'longest_streak_start' => "ADD COLUMN `longest_streak_start` DATE DEFAULT NULL COMMENT 'Start date of longest streak'",
                'longest_streak_end' => "ADD COLUMN `longest_streak_end` DATE DEFAULT NULL COMMENT 'End date of longest streak'",
                'day_statistics' => "ADD COLUMN `day_statistics` TEXT DEFAULT NULL COMMENT 'JSON: connections per day of week'"
            ];
            
            foreach ($columnsToAdd as $columnName => $sql) {
                if (!in_array($columnName, $existingColumns)) {
                    $db->query("ALTER TABLE `{$tableName}` {$sql}");
                }
            }
            
            // Update total_online_hours from total_online_time for existing records
            $db->query("UPDATE `{$tableName}` SET `total_online_hours` = ROUND(`total_online_time` / 3600, 2) WHERE `total_online_hours` = 0 AND `total_online_time` > 0");
        } catch (\Exception $e) {
            error_log("Error adding missing columns: " . $e->getMessage());
        }
    }
    
    /**
     * Ensure tables exist
     * @return bool
     */
    public function ensureTablesExist(): bool {
        $db = DatabaseUtils::i()->getDb();
        $dbConfig = Config::i()->getDatabaseConfig();
        $prefix = isset($dbConfig["prefix"]) ? $dbConfig["prefix"] : "";
        
        try {
            // Create user_statistics table
            $statsTable = $prefix . "user_statistics";
            $stmt = $db->query("SHOW TABLES LIKE '" . addslashes($statsTable) . "'");
            $tableExists = $stmt && $stmt->fetchColumn();
            
            if (!$tableExists) {
                $sql = "CREATE TABLE IF NOT EXISTS `{$statsTable}` (
                    `cldbid` INT(11) NOT NULL,
                    `cluid` VARCHAR(64) DEFAULT NULL,
                    `last_nickname` VARCHAR(128) DEFAULT NULL,
                    `total_connections` INT(11) NOT NULL DEFAULT '0' COMMENT 'Total number of connections',
                    `total_online_time` BIGINT(20) NOT NULL DEFAULT '0' COMMENT 'Total online time in seconds',
                    `total_online_hours` DECIMAL(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total online time in hours',
                    `average_session_time` INT(11) NOT NULL DEFAULT '0' COMMENT 'Average session length in seconds',
                    `longest_session` INT(11) NOT NULL DEFAULT '0' COMMENT 'Longest session in seconds',
                    `current_session_start` TIMESTAMP NULL DEFAULT NULL COMMENT 'When current session started',
                    `last_ip` VARCHAR(45) DEFAULT NULL COMMENT 'Last known IP address',
                    `country_code` VARCHAR(2) DEFAULT NULL COMMENT 'Country code',
                    `first_seen` TIMESTAMP NULL DEFAULT NULL COMMENT 'First time user connected',
                    `last_seen` TIMESTAMP NULL DEFAULT NULL COMMENT 'Last time user was seen',
                    `peak_clients_seen` INT(11) NOT NULL DEFAULT '0' COMMENT 'Max concurrent clients when user was online',
                    `messages_sent` INT(11) NOT NULL DEFAULT '0' COMMENT 'Total messages sent (future feature)',
                    `channels_visited` TEXT DEFAULT NULL COMMENT 'JSON array of visited channel IDs',
                    `connected_days` INT(11) NOT NULL DEFAULT '0' COMMENT 'Total unique days user connected',
                    `connection_dates` TEXT DEFAULT NULL COMMENT 'JSON array of connection dates for tracking',
                    `current_streak` INT(11) NOT NULL DEFAULT '0' COMMENT 'Current consecutive days streak',
                    `longest_streak` INT(11) NOT NULL DEFAULT '0' COMMENT 'Longest consecutive days streak',
                    `longest_streak_start` DATE DEFAULT NULL COMMENT 'Start date of longest streak',
                    `longest_streak_end` DATE DEFAULT NULL COMMENT 'End date of longest streak',
                    `day_statistics` TEXT DEFAULT NULL COMMENT 'JSON: connections per day of week',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`cldbid`),
                    KEY `idx_connections` (`total_connections`),
                    KEY `idx_online_time` (`total_online_time`),
                    KEY `idx_online_hours` (`total_online_hours`),
                    KEY `idx_last_seen` (`last_seen`),
                    KEY `idx_country` (`country_code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comprehensive user statistics and activity tracking'";
                $db->query($sql);
            } else {
                // Add new columns if they don't exist (for existing installations)
                $this->addMissingColumns($db, $statsTable);
            }
            
            // Create channel_stats_display table
            $displayTable = $prefix . "channel_stats_display";
            $stmt = $db->query("SHOW TABLES LIKE '" . addslashes($displayTable) . "'");
            if (!$stmt || !$stmt->fetchColumn()) {
                $sql = "CREATE TABLE IF NOT EXISTS `{$displayTable}` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `channel_id` INT(11) NOT NULL,
                    `display_type` VARCHAR(50) NOT NULL DEFAULT 'combined' COMMENT 'Type: connections, online_time, combined, server_stats',
                    `top_count` INT(11) NOT NULL DEFAULT '10' COMMENT 'How many users to show in leaderboard',
                    `enabled` TINYINT(1) NOT NULL DEFAULT '1',
                    `last_updated` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_channel` (`channel_id`),
                    KEY `idx_enabled` (`enabled`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $db->query($sql);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Failed to create stats tables: " . $e->getMessage());
            return false;
        }
    }
}
