<?php

namespace Wruczek\TSWebsite\Utils;

use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\CacheManager;

/**
 * Class NewsDisplayManager
 * Manages TeamSpeak channel news display configurations and updates
 * @package Wruczek\TSWebsite\Utils
 */
class NewsDisplayManager {

    use SingletonTait;

    /**
     * Get all news display configurations
     * @return array
     */
    public function getConfigurations(): array {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            return $db->select("channel_news_display", "*", ["enabled" => 1]);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get news display configuration for a specific channel
     * @param int $channelId
     * @return array|null
     */
    public function getConfigurationByChannelId(int $channelId): ?array {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            return $db->get("channel_news_display", "*", [
                "channel_id" => $channelId,
                "enabled" => 1
            ]);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Add a new news display configuration
     * @param int $channelId
     * @param int $newsLimit How many news items to display
     * @return bool
     */
    public function addConfiguration(int $channelId, int $newsLimit = 5): bool {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $db->insert("channel_news_display", [
                "channel_id" => $channelId,
                "news_limit" => $newsLimit,
                "enabled" => 1
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update a news display configuration
     * @param int $id
     * @param int $newsLimit
     * @return bool
     */
    public function updateConfiguration(int $id, int $newsLimit): bool {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $db->update("channel_news_display", [
                "news_limit" => $newsLimit
            ], ["id" => $id]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove a news display configuration
     * @param int $id
     * @return bool
     */
    public function removeConfiguration(int $id): bool {
        $db = DatabaseUtils::i()->getDb();
        
        try {
            $db->delete("channel_news_display", ["id" => $id]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update channel description with news data
     * @param int $channelId
     * @param int $newsLimit
     * @return bool
     */
    public function updateChannelDescription(int $channelId, int $newsLimit = 5): bool {
        try {
            if (!TeamSpeakUtils::i()->checkTSConnection()) {
                return false;
            }

            $tsServer = TeamSpeakUtils::i()->getTSNodeServer();
            
            // Get news from database
            $db = DatabaseUtils::i()->getDb();
            $newsList = $db->select("news", "*", [
                "ORDER" => ["added" => "DESC"],
                "LIMIT" => $newsLimit
            ]);
            
            if (empty($newsList)) {
                $newsList = [];
            }

            // Build BBCode description
            $description = $this->buildChannelDescription($newsList);

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

            // Update last updated timestamp in database
            try {
                $db->update("channel_news_display", [
                    "last_updated" => date('Y-m-d H:i:s')
                ], ["channel_id" => $channelId]);
            } catch (\Exception $e) {
                // Ignore timestamp update errors
            }

            return true;
        } catch (\Exception $e) {
            error_log("Failed to update news channel description: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build BBCode formatted channel description with news
     * @param array $newsList
     * @return string
     */
    private function buildChannelDescription(array $newsList): string {
        $baseUrl = $this->getBaseUrl();
        
        $description = "[center]";
        $description .= "[size=16][b]📰 Latest News[/b][/size]\n";
        $description .= "[/center]\n\n";
        
        if (empty($newsList)) {
            $description .= "[center][i]No news available at this time.[/i][/center]\n\n";
        } else {
            foreach ($newsList as $news) {
                $title = isset($news["title"]) ? (string) $news["title"] : "Untitled";
                $content = isset($news["content"]) ? (string) $news["content"] : "";
                $added = isset($news["added"]) ? (int) $news["added"] : time();
                $edited = isset($news["edited"]) ? (int) $news["edited"] : null;
                
                // Format date
                $dateStr = date('F j, Y', $added);
                if ($edited && $edited > $added) {
                    $dateStr .= " (edited: " . date('M j', $edited) . ")";
                }
                
                // Truncate content if too long (keep first 300 characters)
                $contentPreview = $content;
                if (strlen($content) > 300) {
                    $contentPreview = substr($content, 0, 297) . "...";
                }
                
                // Build news item
                $description .= "[hr]\n";
                $description .= "[size=14][b]" . htmlspecialchars($title) . "[/b][/size]\n";
                $description .= "[size=9][color=#888888]{$dateStr}[/color][/size]\n\n";
                $description .= "[size=10]" . htmlspecialchars($contentPreview) . "[/size]\n\n";
            }
            
            $description .= "[hr]\n";
        }
        
        // Add link to full news page
        $newsUrl = "{$baseUrl}/index.php";
        $description .= "[center][url={$newsUrl}]» View all news on website «[/url][/center]\n\n";
        
        // Add footer with timestamp
        $timestamp = date('Y-m-d H:i:s');
        $description .= "[right][size=8]Last updated: {$timestamp}[/size][/right]";
        
        return $description;
    }
    
    /**
     * Get base URL for links
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
     * Process news update for all configured channels
     * This should be called when news is added/edited or manually triggered
     * @return array Statistics about updates
     */
    public function processAllNewsUpdates(): array {
        $stats = [
            'total' => 0,
            'updated' => 0,
            'failed' => 0,
        ];

        try {
            $configs = $this->getConfigurations();
            $stats['total'] = count($configs);

            if (empty($configs)) {
                return $stats;
            }

            foreach ($configs as $config) {
                $channelId = (int) $config['channel_id'];
                $newsLimit = isset($config['news_limit']) ? (int) $config['news_limit'] : 5;
                
                $result = $this->updateChannelDescription($channelId, $newsLimit);
                
                if ($result) {
                    $stats['updated']++;
                } else {
                    $stats['failed']++;
                }
            }
        } catch (\Exception $e) {
            error_log("Error processing news updates: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Ensure the news display table exists
     * @return bool
     */
    public function ensureTableExists(): bool {
        $db = DatabaseUtils::i()->getDb();
        $dbConfig = Config::i()->getDatabaseConfig();
        $prefix = isset($dbConfig["prefix"]) ? $dbConfig["prefix"] : "";
        $tableName = $prefix . "channel_news_display";

        try {
            // Check if table exists
            $stmt = $db->query("SHOW TABLES LIKE '" . addslashes($tableName) . "'");
            $exists = $stmt && $stmt->fetchColumn();

            if (!$exists) {
                // Create the table
                $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `channel_id` INT(11) NOT NULL COMMENT 'Channel ID to update description',
                    `news_limit` INT(11) NOT NULL DEFAULT '5' COMMENT 'Number of news items to display',
                    `enabled` TINYINT(1) NOT NULL DEFAULT '1' COMMENT 'Whether this configuration is active',
                    `last_updated` TIMESTAMP NULL DEFAULT NULL COMMENT 'Last time the channel was updated',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_channel` (`channel_id`),
                    KEY `idx_enabled` (`enabled`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

                $db->query($sql);
                return true;
            }

            return true;
        } catch (\Exception $e) {
            error_log("Failed to ensure news display table exists: " . $e->getMessage());
            return false;
        }
    }
}
