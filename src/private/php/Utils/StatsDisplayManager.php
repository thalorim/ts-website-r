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
            
            if ($existing) {
                // Update existing
                $db->update("user_statistics", [
                    "total_connections" => $existing['total_connections'] + 1,
                    "last_seen" => date('Y-m-d H:i:s'),
                    "last_nickname" => $nickname,
                    "cluid" => $cluid
                ], ["cldbid" => $cldbid]);
            } else {
                // Insert new
                $db->insert("user_statistics", [
                    "cldbid" => $cldbid,
                    "cluid" => $cluid,
                    "last_nickname" => $nickname,
                    "total_connections" => 1,
                    "total_online_time" => 0,
                    "first_seen" => date('Y-m-d H:i:s'),
                    "last_seen" => date('Y-m-d H:i:s')
                ]);
            }
            return true;
        } catch (\Exception $e) {
            error_log("Failed to record connection: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update online time for user
     * @param int $cldbid
     * @param int $seconds
     * @return bool
     */
    public function addOnlineTime(int $cldbid, int $seconds): bool {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $existing = $db->get("user_statistics", ["total_online_time"], ["cldbid" => $cldbid]);
            
            if ($existing) {
                $newTime = $existing['total_online_time'] + $seconds;
                $db->update("user_statistics", [
                    "total_online_time" => $newTime,
                    "last_seen" => date('Y-m-d H:i:s')
                ], ["cldbid" => $cldbid]);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            error_log("Failed to add online time: " . $e->getMessage());
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
        
        // Build table
        $description .= "[table]\n";
        $description .= "[tr][th]Rank[/th][th]User[/th][th]Connections[/th][th]Last Seen[/th][/tr]\n";
        
        $rank = 1;
        foreach ($topUsers as $user) {
            $cldbid = (int)$user['cldbid'];
            $nickname = $this->escapeBBCode($user['last_nickname'] ?? 'Unknown');
            $connections = number_format((int)$user['total_connections']);
            $lastSeen = $this->formatDate($user['last_seen']);
            
            // Get medal emoji
            $medal = $this->getRankMedal($rank);
            
            // Profile link
            $profileUrl = "{$baseUrl}/profile.php?cldbid={$cldbid}";
            $userLink = "[url={$profileUrl}]{$nickname}[/url]";
            
            $description .= "[tr][td]{$medal} #{$rank}[/td][td]{$userLink}[/td][td]{$connections}[/td][td]{$lastSeen}[/td][/tr]\n";
            $rank++;
        }
        
        $description .= "[/table]\n\n";
        
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
        
        // Build table
        $description .= "[table]\n";
        $description .= "[tr][th]Rank[/th][th]User[/th][th]Online Time[/th][th]Last Seen[/th][/tr]\n";
        
        $rank = 1;
        foreach ($topUsers as $user) {
            $cldbid = (int)$user['cldbid'];
            $nickname = $this->escapeBBCode($user['last_nickname'] ?? 'Unknown');
            $onlineTime = $this->formatTime((int)$user['total_online_time']);
            $lastSeen = $this->formatDate($user['last_seen']);
            
            // Get medal emoji
            $medal = $this->getRankMedal($rank);
            
            // Profile link
            $profileUrl = "{$baseUrl}/profile.php?cldbid={$cldbid}";
            $userLink = "[url={$profileUrl}]{$nickname}[/url]";
            
            $description .= "[tr][td]{$medal} #{$rank}[/td][td]{$userLink}[/td][td]{$onlineTime}[/td][td]{$lastSeen}[/td][/tr]\n";
            $rank++;
        }
        
        $description .= "[/table]\n\n";
        
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
        
        $description .= "[table]\n";
        $description .= "[tr][th]Statistic[/th][th]Value[/th][/tr]\n";
        $description .= "[tr][td]👥 Total Unique Users[/td][td][b]" . number_format($stats['total_users']) . "[/b][/td][/tr]\n";
        $description .= "[tr][td]🟢 Currently Online[/td][td][b]" . number_format($stats['current_online']) . "[/b][/td][/tr]\n";
        $description .= "[tr][td]🔄 Total Connections[/td][td][b]" . number_format($stats['total_connections']) . "[/b][/td][/tr]\n";
        $description .= "[tr][td]⏱️ Total Online Time[/td][td][b]{$totalTime}[/b][/td][/tr]\n";
        $description .= "[tr][td]📈 Avg Connections/User[/td][td][b]" . number_format($stats['avg_connections'], 1) . "[/b][/td][/tr]\n";
        $description .= "[tr][td]⏰ Avg Time/User[/td][td][b]{$avgTime}[/b][/td][/tr]\n";
        $description .= "[/table]\n\n";
        
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
        $description .= "[center][size=14][b]Server Overview[/b][/size][/center]\n";
        $description .= "[center]";
        $description .= "👥 Users: [b]" . number_format($stats['total_users']) . "[/b] | ";
        $description .= "🟢 Online: [b]" . number_format($stats['current_online']) . "[/b] | ";
        $description .= "🔄 Connections: [b]" . number_format($stats['total_connections']) . "[/b]";
        $description .= "[/center]\n\n";
        
        $description .= "[hr]\n\n";
        
        // Top Connections
        $topConnections = $this->getTopByConnections($topCount);
        $description .= "[center][size=14][b]🏆 Top by Connections[/b][/size][/center]\n";
        
        if (!empty($topConnections)) {
            $description .= "[table]\n";
            $description .= "[tr][th]Rank[/th][th]User[/th][th]Connections[/th][/tr]\n";
            
            $rank = 1;
            foreach (array_slice($topConnections, 0, 5) as $user) {
                $cldbid = (int)$user['cldbid'];
                $nickname = $this->escapeBBCode($user['last_nickname'] ?? 'Unknown');
                $connections = number_format((int)$user['total_connections']);
                $medal = $this->getRankMedal($rank);
                
                $profileUrl = "{$baseUrl}/profile.php?cldbid={$cldbid}";
                $userLink = "[url={$profileUrl}]{$nickname}[/url]";
                
                $description .= "[tr][td]{$medal} #{$rank}[/td][td]{$userLink}[/td][td]{$connections}[/td][/tr]\n";
                $rank++;
            }
            
            $description .= "[/table]\n\n";
        }
        
        $description .= "[hr]\n\n";
        
        // Top Online Time
        $topTime = $this->getTopByOnlineTime($topCount);
        $description .= "[center][size=14][b]⏱️ Top by Online Time[/b][/size][/center]\n";
        
        if (!empty($topTime)) {
            $description .= "[table]\n";
            $description .= "[tr][th]Rank[/th][th]User[/th][th]Time[/th][/tr]\n";
            
            $rank = 1;
            foreach (array_slice($topTime, 0, 5) as $user) {
                $cldbid = (int)$user['cldbid'];
                $nickname = $this->escapeBBCode($user['last_nickname'] ?? 'Unknown');
                $onlineTime = $this->formatTime((int)$user['total_online_time']);
                $medal = $this->getRankMedal($rank);
                
                $profileUrl = "{$baseUrl}/profile.php?cldbid={$cldbid}";
                $userLink = "[url={$profileUrl}]{$nickname}[/url]";
                
                $description .= "[tr][td]{$medal} #{$rank}[/td][td]{$userLink}[/td][td]{$onlineTime}[/td][/tr]\n";
                $rank++;
            }
            
            $description .= "[/table]\n\n";
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
            if (!$stmt || !$stmt->fetchColumn()) {
                $sql = "CREATE TABLE IF NOT EXISTS `{$statsTable}` (
                    `cldbid` INT(11) NOT NULL,
                    `cluid` VARCHAR(64) DEFAULT NULL,
                    `last_nickname` VARCHAR(128) DEFAULT NULL,
                    `total_connections` INT(11) NOT NULL DEFAULT '0',
                    `total_online_time` BIGINT(20) NOT NULL DEFAULT '0' COMMENT 'Total online time in seconds',
                    `first_seen` TIMESTAMP NULL DEFAULT NULL,
                    `last_seen` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`cldbid`),
                    KEY `idx_connections` (`total_connections`),
                    KEY `idx_online_time` (`total_online_time`),
                    KEY `idx_last_seen` (`last_seen`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $db->query($sql);
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
