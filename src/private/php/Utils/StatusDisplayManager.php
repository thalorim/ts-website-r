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
            
            $nickname = "User";
            $clid = null;
            $uid = null;
            
            // Try to get live data if user is online
            if ($isOnline) {
                try {
                    $onlineClients = CacheManager::i()->getClientList();
                    foreach ($onlineClients as $client) {
                        if (isset($client['client_database_id']) && (int)$client['client_database_id'] === $cldbid) {
                            $nickname = (string)$client['client_nickname'];
                            $clid = (int)$client['clid'];
                            $uid = (string)$client['client_unique_identifier'];
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    // Fallback to profile/db data
                }
            }
            
            // Fallback to profile data
            if (!$profile) {
                // Try to fetch from TeamSpeak
                try {
                    $tsInfo = $tsServer->clientDbInfo($cldbid);
                    $nickname = isset($tsInfo["client_nickname"]) ? (string) $tsInfo["client_nickname"] : $nickname;
                    if (!$uid && isset($tsInfo["client_unique_identifier"])) {
                        $uid = (string) $tsInfo["client_unique_identifier"];
                    }
                } catch (\Exception $e) {
                    // Use default
                }
            } else {
                if (empty($nickname) || $nickname === "User") {
                    $nickname = $profile["nickname"] ?? "User";
                }
                if (!$uid && !empty($profile["cluid"])) {
                    $uid = (string) $profile["cluid"];
                }
            }

            // Build BBCode description
            $description = $this->buildChannelDescription($cldbid, $nickname, $isOnline, $profile, $serverGroupId, $clid, $uid);

            // Update channel description
            // Method 1: Try getting channel object and modifying it
            try {
                $channel = $tsServer->channelGetById($channelId);
                $channel->modify(['channel_description' => $description]);
            } catch (\Exception $e1) {
                // Method 2: Try using execute method (for older versions)
                try {
                    $tsServer->execute("channeledit", [
                        "cid" => $channelId,
                        "channel_description" => $description
                    ]);
                } catch (\Exception $e2) {
                    // Method 3: Use raw request as last resort
                    $tsServer->request("channeledit cid=" . (int)$channelId . 
                        " channel_description=" . $tsServer->escape($description));
                }
            }

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
     * @param int|null $clid Client ID (if online)
     * @param string|null $uid Client Unique Identifier
     * @return string
     */
    private function buildChannelDescription(int $cldbid, string $nickname, bool $isOnline, ?array $profile, ?int $serverGroupId, ?int $clid = null, ?string $uid = null): string {
        $baseUrl = $this->getBaseUrl();
        $profileUrl = "{$baseUrl}/profile.php?cldbid={$cldbid}";
        
        // Get avatar URL
        $avatarUrl = null;
        if ($profile && !empty($profile["avatar_url"])) {
            $avatarUrl = $profile["avatar_url"];
            // Make absolute URL if relative
            if (!preg_match('/^https?:\/\//', $avatarUrl)) {
                $avatarUrl = $baseUrl . '/' . ltrim($avatarUrl, '/');
            }
        } else {
            // Default avatar
            $avatarUrl = $baseUrl . '/img/icons/defaulticon-128.png';
        }
        
        // Status indicator
        $statusColor = $isOnline ? "#00ff00" : "#ff0000";
        $statusText = $isOnline ? "ONLINE" : "OFFLINE";
        $statusIcon = $isOnline ? "✓" : "✗";
        
        $description = "[center]";
        
        // Add avatar image (resized to 250x250)
        $description .= "[img=250x250]" . htmlspecialchars($avatarUrl) . "[/img]\n\n";
        
        // Add username - make it clickable if online and we have the client data
        if ($isOnline && $clid !== null && $uid !== null) {
            // TeamSpeak clickable client link format: [URL=client://CLID/UID~NICKNAME]NICKNAME[/URL]
            // Note: UID should NOT be URL encoded - TeamSpeak expects it as-is
            // Only encode the nickname part for special characters
            $encodedNickname = str_replace(['[', ']', ' '], ['%5B', '%5D', '%20'], $nickname);
            $clientUrl = "client://{$clid}/{$uid}~{$encodedNickname}";
            $description .= "[size=14][b][url=" . htmlspecialchars($clientUrl) . "]" . htmlspecialchars($nickname) . "[/url][/b][/size]\n\n";
        } else {
            // Not online or no client data - just show name
            $description .= "[size=14][b]" . htmlspecialchars($nickname) . "[/b][/size]\n\n";
        }
        
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
        
        // Social media links with image icons
        $steamId = null;
        $steamUrl = null;
        
        if ($profile && !empty($profile["socials_json"])) {
            $socials = json_decode($profile["socials_json"], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($socials) && !empty($socials)) {
                $description .= "[size=10][b]Social Media:[/b][/size]\n";
                
                // Map platform to image filename
                $socialIcons = [
                    'instagram' => 'ts3-instagram.png',
                    'facebook' => 'ts3-facebook.png',
                    'youtube' => 'ts3-youtube.png',
                    'twitter' => 'ts3-twitter.png',
                    'steam' => 'ts3-steam.png',
                    'soundcloud' => 'ts3-soundcloud.png',
                    'github' => 'ts3-github.png',
                    'telegram' => 'ts3-telegram.png',
                    'twitch' => 'ts3-twitch.png',
                    'discord' => 'ts3-discord.png',
                ];
                
                foreach ($socials as $platform => $url) {
                    if (!empty($url) && isset($socialIcons[$platform])) {
                        $iconFilename = $socialIcons[$platform];
                        $iconUrl = $baseUrl . '/img/icons/' . $iconFilename;
                        
                        // Make image clickable by wrapping in URL tag
                        $description .= "[url={$url}][img]{$iconUrl}[/img][/url] ";
                        
                        // Extract Steam ID if this is a Steam profile
                        if ($platform === 'steam' && $steamId === null) {
                            $steamId = $this->extractSteamId($url);
                            $steamUrl = $url;
                        }
                    }
                }
                $description .= "\n\n";
                
                // Add Steam signature if Steam ID was found
                if ($steamId !== null && $steamUrl !== null) {
                    $signatureUrl = "https://www.reape.rs/signature/signature.php?steamid={$steamId}";
                    $description .= "[url={$steamUrl}][img]{$signatureUrl}[/img][/url]\n\n";
                    
                    // Debug log
                    error_log("Steam Signature Added: steamid={$steamId}, url={$signatureUrl}");
                }
            }
        }
        
        // Additional profile info
        if ($profile) {
            if (!empty($profile["description"])) {
                $description .= "[hr]\n";
                $description .= "[size=10]" . htmlspecialchars(substr($profile["description"], 0, 200)) . "[/size]\n\n";
            }
        }
        
        $description .= "[/center]";
        
        // Add footer with timestamp
        $timestamp = date('Y-m-d H:i:s');
        $description .= "[hr]\n";
        $description .= "[right][size=8]Last updated: {$timestamp}[/size][/right]";
        
        return $description;
    }

    /**
     * Extract Steam ID from Steam profile URL
     * @param string $steamUrl
     * @return string|null Steam ID (64-bit format) or null if not found
     */
    private function extractSteamId(string $steamUrl): ?string {
        // Steam URL formats:
        // https://steamcommunity.com/profiles/76561198399534638
        // https://steamcommunity.com/id/username
        // steamcommunity.com/profiles/76561198399534638
        
        // Try to extract 64-bit Steam ID (17 digits starting with 7656)
        if (preg_match('/\/profiles\/(\d{17})/', $steamUrl, $matches)) {
            return $matches[1];
        }
        
        // Try to extract from direct ID in URL
        if (preg_match('/steamid=(\d{17})/', $steamUrl, $matches)) {
            return $matches[1];
        }
        
        // If it's a custom URL (/id/username), we can't directly get the Steam64 ID
        // without making an API call, so we'll try to extract it if it's in the URL
        if (preg_match('/\/id\/([a-zA-Z0-9_-]+)/', $steamUrl, $matches)) {
            // Could implement Steam API lookup here if needed
            // For now, return null as we need the 64-bit ID
            return null;
        }
        
        // Try one more pattern - just the ID at the end
        if (preg_match('/(\d{17})(?:\/|$)/', $steamUrl, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get base URL for profile links
     * @return string
     */
    private function getBaseUrl(): string {
        // Try to get from config or construct from server variables
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Get the document root and current script path
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
        
        // Calculate base path
        $basePath = '';
        if (!empty($documentRoot) && !empty($scriptFilename)) {
            // Get the relative path from document root
            $relativePath = str_replace($documentRoot, '', dirname($scriptFilename));
            
            // Find the 'src' directory and go one level up
            if (strpos($relativePath, '/src') !== false) {
                $parts = explode('/src', $relativePath);
                $basePath = $parts[0];
            } elseif (strpos($relativePath, '/private/php') !== false) {
                $parts = explode('/private/php', $relativePath);
                $basePath = $parts[0];
            } elseif (strpos($relativePath, '/admin') !== false) {
                $parts = explode('/admin', $relativePath);
                $basePath = $parts[0];
            }
        }
        
        // Construct full URL
        $baseUrl = $protocol . '://' . $host . $basePath;
        
        return rtrim($baseUrl, '/');
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
