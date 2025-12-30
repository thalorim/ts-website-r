<?php

namespace Wruczek\TSWebsite\Utils;

use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\CacheManager;

/**
 * Class StatusDisplayManager
 * Manages TeamSpeak channel status display configurations and updates
 * @package Wruczek\TSWebsite\Utils
 */
class StatusDisplayManager {

    use SingletonTait;

    /**
     * Get all status display configurations
     * @return array
     */
    public function getConfigurations(): array {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            return $db->select("channel_status_display", "*", ["enabled" => 1]);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get status display configuration for a specific cldbid
     * @param int $cldbid
     * @return array|null
     */
    public function getConfigurationByCldbid(int $cldbid): ?array {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $configs = $db->select("channel_status_display", "*", [
                "cldbid" => $cldbid,
                "enabled" => 1
            ]);
            
            return !empty($configs) ? $configs : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Add a new status display configuration
     * @param int $cldbid
     * @param int $channelId
     * @param int|null $serverGroupId
     * @return bool
     */
    public function addConfiguration(int $cldbid, int $channelId, ?int $serverGroupId = null): bool {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $db->insert("channel_status_display", [
                "cldbid" => $cldbid,
                "channel_id" => $channelId,
                "server_group_id" => $serverGroupId,
                "enabled" => 1
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove a status display configuration
     * @param int $id
     * @return bool
     */
    public function removeConfiguration(int $id): bool {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $db->delete("channel_status_display", ["id" => $id]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update channel description with user status
     * @param int $cldbid
     * @param int $channelId
     * @param bool $isOnline
     * @param int|null $serverGroupId
     * @return bool
     */
    public function updateChannelDescription(int $cldbid, int $channelId, bool $isOnline, ?int $serverGroupId = null): bool {
        try {
            if (!TeamSpeakUtils::i()->checkTSConnection()) {
                return false;
            }

            $tsServer = TeamSpeakUtils::i()->getTSNodeServer();
            
            // Get user profile data
            $db = DatabaseUtils::i()->getDb();
            $profile = $db->get("profiles", "*", ["cldbid" => $cldbid]);
            
            if (!$profile) {
                // Try to fetch from TeamSpeak
                try {
                    $tsInfo = $tsServer->clientDbInfo($cldbid);
                    $nickname = isset($tsInfo["client_nickname"]) ? (string) $tsInfo["client_nickname"] : "User";
                } catch (\Exception $e) {
                    $nickname = "User";
                }
            } else {
                $nickname = $profile["nickname"] ?? "User";
            }

            // Build BBCode description
            $description = $this->buildChannelDescription($cldbid, $nickname, $isOnline, $profile, $serverGroupId);

            // Update channel description
            $tsServer->channelEdit($channelId, [
                "channel_description" => $description
            ]);

            return true;
        } catch (\Exception $e) {
            error_log("Failed to update channel description: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build BBCode formatted channel description
     * @param int $cldbid
     * @param string $nickname
     * @param bool $isOnline
     * @param array|null $profile
     * @param int|null $serverGroupId
     * @return string
     */
    private function buildChannelDescription(int $cldbid, string $nickname, bool $isOnline, ?array $profile, ?int $serverGroupId): string {
        $baseUrl = $this->getBaseUrl();
        $profileUrl = "{$baseUrl}/profile.php?cldbid={$cldbid}";
        
        // Status indicator
        $statusColor = $isOnline ? "#00ff00" : "#ff0000";
        $statusText = $isOnline ? "ONLINE" : "OFFLINE";
        $statusIcon = $isOnline ? "✓" : "✗";
        
        $description = "[center]";
        $description .= "[size=14][b]" . htmlspecialchars($nickname) . "[/b][/size]\n\n";
        
        // Status with color
        $description .= "[color={$statusColor}][size=12][b]{$statusIcon} {$statusText}[/b][/size][/color]\n\n";
        
        // Profile link
        $description .= "[url={$profileUrl}]View Profile[/url]\n\n";
        
        // Server group icon (if available)
        if ($serverGroupId !== null) {
            try {
                $serverGroups = CacheManager::i()->getServerGroupList();
                if (isset($serverGroups[$serverGroupId])) {
                    $groupInfo = $serverGroups[$serverGroupId];
                    $groupName = isset($groupInfo["name"]) ? (string) $groupInfo["name"] : "Group";
                    
                    // Add server group icon reference
                    if (isset($groupInfo["iconid"]) && $groupInfo["iconid"] > 0) {
                        $iconId = (int) $groupInfo["iconid"];
                        // TeamSpeak icon display (using icon ID)
                        $description .= "[img]icon_" . abs($iconId) . "[/img] {$groupName}\n\n";
                    }
                }
            } catch (\Exception $e) {
                // Ignore icon errors
            }
        }
        
        // Social media links
        if ($profile && !empty($profile["socials_json"])) {
            $socials = json_decode($profile["socials_json"], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($socials) && !empty($socials)) {
                $description .= "[size=10][b]Social Media:[/b][/size]\n";
                
                $socialIcons = [
                    'instagram' => '📷',
                    'facebook' => '👤',
                    'youtube' => '▶️',
                    'twitter' => '🐦',
                    'steam' => '🎮',
                    'soundcloud' => '🎵',
                    'github' => '💻',
                    'telegram' => '✈️',
                    'twitch' => '📺',
                    'discord' => '💬',
                ];
                
                foreach ($socials as $platform => $url) {
                    if (!empty($url)) {
                        $icon = $socialIcons[$platform] ?? '🔗';
                        $platformName = ucfirst($platform);
                        $description .= "[url={$url}]{$icon} {$platformName}[/url] ";
                    }
                }
                $description .= "\n\n";
            }
        }
        
        // Additional profile info
        if ($profile) {
            if (!empty($profile["description"])) {
                $description .= "[hr]\n";
                $description .= "[size=10]" . htmlspecialchars(substr($profile["description"], 0, 200)) . "[/size]\n";
            }
        }
        
        $description .= "[/center]";
        
        return $description;
    }

    /**
     * Get base URL for profile links
     * @return string
     */
    private function getBaseUrl(): string {
        // Try to get from config or construct from server variables
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Remove trailing slash
        $baseUrl = rtrim($protocol . '://' . $host, '/');
        
        // Check if we're in a subdirectory
        if (defined('__BASE_DIR')) {
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $basePath = str_replace('/index.php', '', $scriptName);
            $basePath = str_replace('/private/php/load.php', '', $basePath);
            if (!empty($basePath) && $basePath !== '/') {
                $baseUrl .= $basePath;
            }
        }
        
        return $baseUrl;
    }

    /**
     * Process status update for all configured users
     * This should be called by the monitoring bot
     * @return array Statistics about updates
     */
    public function processAllStatusUpdates(): array {
        $stats = [
            'total' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0
        ];

        try {
            $configs = $this->getConfigurations();
            $stats['total'] = count($configs);

            if (empty($configs)) {
                return $stats;
            }

            // Get list of currently online clients
            $onlineClients = CacheManager::i()->getClientList();
            $onlineCldbids = [];
            
            foreach ($onlineClients as $client) {
                if (isset($client['client_database_id'])) {
                    $onlineCldbids[] = (int) $client['client_database_id'];
                }
            }

            foreach ($configs as $config) {
                $cldbid = (int) $config['cldbid'];
                $channelId = (int) $config['channel_id'];
                $serverGroupId = isset($config['server_group_id']) ? (int) $config['server_group_id'] : null;
                
                $isOnline = in_array($cldbid, $onlineCldbids, true);
                
                $result = $this->updateChannelDescription($cldbid, $channelId, $isOnline, $serverGroupId);
                
                if ($result) {
                    $stats['updated']++;
                } else {
                    $stats['failed']++;
                }
            }
        } catch (\Exception $e) {
            error_log("Error processing status updates: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Ensure the status display table exists
     * @return bool
     */
    public function ensureTableExists(): bool {
        $db = DatabaseUtils::i()->getDb();
        $dbConfig = Config::i()->getDatabaseConfig();
        $prefix = isset($dbConfig["prefix"]) ? $dbConfig["prefix"] : "";
        $tableName = $prefix . "channel_status_display";

        try {
            // Check if table exists
            $stmt = $db->query("SHOW TABLES LIKE '" . addslashes($tableName) . "'");
            $exists = $stmt && $stmt->fetchColumn();

            if (!$exists) {
                // Create the table
                $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `cldbid` INT(11) NOT NULL COMMENT 'Client database ID to monitor',
                    `channel_id` INT(11) NOT NULL COMMENT 'Channel ID to update description',
                    `server_group_id` INT(11) DEFAULT NULL COMMENT 'Server group ID to display icon for',
                    `enabled` TINYINT(1) NOT NULL DEFAULT '1' COMMENT 'Whether this configuration is active',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_cldbid_channel` (`cldbid`, `channel_id`),
                    KEY `idx_cldbid` (`cldbid`),
                    KEY `idx_enabled` (`enabled`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

                $db->query($sql);
                return true;
            }

            return true;
        } catch (\Exception $e) {
            error_log("Failed to ensure table exists: " . $e->getMessage());
            return false;
        }
    }
}
