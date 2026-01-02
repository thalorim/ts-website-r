<?php

namespace Wruczek\TSWebsite\Utils;

use Wruczek\TSWebsite\News\DefaultNewsStore;
use TeamSpeak3_Helper_String;

/**
 * NewsChannelDisplayManager
 * Manages configurations for displaying news in TeamSpeak channel descriptions
 */
class NewsChannelDisplayManager {

    use SingletonTait;

    private $db;
    private $tableName = "news_channel_display";

    private function __construct() {
        $this->db = DatabaseUtils::i()->getDb();
    }

    /**
     * Ensures the configuration table exists
     */
    public function ensureTableExists(): void {
        $type = $this->db->type();
        
        if ($type === "mysql") {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `channel_id` INT NOT NULL,
                    `news_limit` INT DEFAULT 5,
                    `show_content` TINYINT(1) DEFAULT 1,
                    `content_max_length` INT DEFAULT 200,
                    `enabled` TINYINT(1) DEFAULT 1,
                    `last_updated` INT DEFAULT NULL,
                    UNIQUE KEY `unique_channel` (`channel_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } else {
            // SQLite
            $this->db->query("
                CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `channel_id` INTEGER NOT NULL,
                    `news_limit` INTEGER DEFAULT 5,
                    `show_content` INTEGER DEFAULT 1,
                    `content_max_length` INTEGER DEFAULT 200,
                    `enabled` INTEGER DEFAULT 1,
                    `last_updated` INTEGER DEFAULT NULL,
                    UNIQUE(`channel_id`)
                )
            ");
        }
    }

    /**
     * Get all configurations
     */
    public function getConfigurations(): array {
        $this->ensureTableExists();
        return $this->db->select($this->tableName, "*", ["ORDER" => ["id" => "ASC"]]) ?: [];
    }

    /**
     * Get a specific configuration by ID
     */
    public function getConfiguration(int $id): ?array {
        $this->ensureTableExists();
        $result = $this->db->get($this->tableName, "*", ["id" => $id]);
        return $result ?: null;
    }

    /**
     * Get configuration by channel ID
     */
    public function getConfigurationByChannel(int $channelId): ?array {
        $this->ensureTableExists();
        $result = $this->db->get($this->tableName, "*", ["channel_id" => $channelId]);
        return $result ?: null;
    }

    /**
     * Add a new configuration
     */
    public function addConfiguration(
        int $channelId,
        int $newsLimit = 5,
        bool $showContent = true,
        int $contentMaxLength = 200
    ): bool {
        $this->ensureTableExists();
        
        // Check if configuration already exists for this channel
        if ($this->getConfigurationByChannel($channelId) !== null) {
            return false;
        }

        $result = $this->db->insert($this->tableName, [
            "channel_id" => $channelId,
            "news_limit" => $newsLimit,
            "show_content" => $showContent ? 1 : 0,
            "content_max_length" => $contentMaxLength,
            "enabled" => 1,
        ]);

        return $result->rowCount() > 0;
    }

    /**
     * Update a configuration
     */
    public function updateConfiguration(
        int $id,
        ?int $channelId = null,
        ?int $newsLimit = null,
        ?bool $showContent = null,
        ?int $contentMaxLength = null,
        ?bool $enabled = null
    ): bool {
        $this->ensureTableExists();
        
        $data = [];
        if ($channelId !== null) $data["channel_id"] = $channelId;
        if ($newsLimit !== null) $data["news_limit"] = $newsLimit;
        if ($showContent !== null) $data["show_content"] = $showContent ? 1 : 0;
        if ($contentMaxLength !== null) $data["content_max_length"] = $contentMaxLength;
        if ($enabled !== null) $data["enabled"] = $enabled ? 1 : 0;

        if (empty($data)) {
            return false;
        }

        $result = $this->db->update($this->tableName, $data, ["id" => $id]);
        return $result->rowCount() > 0;
    }

    /**
     * Remove a configuration
     */
    public function removeConfiguration(int $id): bool {
        $this->ensureTableExists();
        $result = $this->db->delete($this->tableName, ["id" => $id]);
        return $result->rowCount() > 0;
    }

    /**
     * Update a specific channel's description with latest news
     */
    public function updateChannelNews(int $configId): array {
        $config = $this->getConfiguration($configId);
        
        if (!$config) {
            return [
                "success" => false,
                "error" => "Configuration not found",
            ];
        }

        if (!$config["enabled"]) {
            return [
                "success" => false,
                "error" => "Configuration is disabled",
            ];
        }

        return $this->updateChannel($config);
    }

    /**
     * Update all configured channels with latest news
     */
    public function updateAllChannels(): array {
        $configurations = $this->getConfigurations();
        $results = [
            "total" => count($configurations),
            "updated" => 0,
            "failed" => 0,
            "skipped" => 0,
            "details" => [],
        ];

        foreach ($configurations as $config) {
            if (!$config["enabled"]) {
                $results["skipped"]++;
                $results["details"][] = [
                    "config_id" => $config["id"],
                    "channel_id" => $config["channel_id"],
                    "status" => "skipped",
                    "reason" => "Configuration disabled",
                ];
                continue;
            }

            $result = $this->updateChannel($config);
            
            if ($result["success"]) {
                $results["updated"]++;
                $results["details"][] = [
                    "config_id" => $config["id"],
                    "channel_id" => $config["channel_id"],
                    "status" => "success",
                ];
            } else {
                $results["failed"]++;
                $results["details"][] = [
                    "config_id" => $config["id"],
                    "channel_id" => $config["channel_id"],
                    "status" => "failed",
                    "error" => $result["error"] ?? "Unknown error",
                ];
            }
        }

        return $results;
    }

    /**
     * Update a channel with the formatted news description
     */
    private function updateChannel(array $config): array {
        try {
            // Check TeamSpeak connection
            if (!TeamSpeakUtils::i()->checkTSConnection()) {
                return [
                    "success" => false,
                    "error" => "Cannot connect to TeamSpeak server",
                ];
            }

            $server = TeamSpeakUtils::i()->getTSNodeServer();
            $channelId = (int) $config["channel_id"];
            
            // Get the channel
            try {
                $channel = $server->channelGetById($channelId);
            } catch (\Exception $e) {
                return [
                    "success" => false,
                    "error" => "Channel not found: " . $e->getMessage(),
                ];
            }

            // Fetch news from database
            $newsStore = new DefaultNewsStore();
            $newsLimit = (int) $config["news_limit"];
            $newsList = $newsStore->getNewsList($newsLimit);

            // Format the news for channel description
            $description = $this->formatNewsDescription($newsList, $config);

            // Update the channel description
            $channel->modify([
                "channel_description" => $description,
            ]);

            // Update last_updated timestamp
            $this->db->update($this->tableName, [
                "last_updated" => time(),
            ], [
                "id" => $config["id"],
            ]);

            return [
                "success" => true,
                "channel_id" => $channelId,
                "news_count" => count($newsList),
            ];

        } catch (\Exception $e) {
            return [
                "success" => false,
                "error" => $e->getMessage(),
            ];
        }
    }

    /**
     * Format news list into BBCode for TeamSpeak channel description
     */
    private function formatNewsDescription(array $newsList, array $config): string {
        $showContent = (bool) $config["show_content"];
        $maxLength = (int) $config["content_max_length"];

        $lines = [];
        $lines[] = "[center][b][size=15]Latest News[/size][/b][/center]";
        $lines[] = "";

        if (empty($newsList)) {
            $lines[] = "[center][i]No news available[/i][/center]";
        } else {
            $count = 0;
            foreach ($newsList as $news) {
                $count++;
                
                // News title
                $title = $this->escapeBb((string) $news["title"]);
                $lines[] = "[b]{$count}. {$title}[/b]";

                // News date
                $added = isset($news["added"]) ? (int) $news["added"] : null;
                if ($added) {
                    $dateStr = date("Y-m-d H:i", $added);
                    $lines[] = "[size=9][color=gray]Posted: {$dateStr}[/color][/size]";
                }

                // News content (if enabled)
                if ($showContent) {
                    $content = (string) $news["description"];
                    
                    // Strip HTML tags
                    $content = strip_tags($content);
                    
                    // Truncate if needed
                    if (mb_strlen($content) > $maxLength) {
                        $content = mb_substr($content, 0, $maxLength) . "...";
                    }
                    
                    $content = $this->escapeBb($content);
                    if (!empty($content)) {
                        $lines[] = $content;
                    }
                }

                $lines[] = "";
            }
        }

        // Footer with update time
        $lines[] = "[hr]";
        $lines[] = "[size=8][color=gray]Last updated: " . date("Y-m-d H:i:s") . "[/color][/size]";

        return implode("\n", $lines);
    }

    /**
     * Escape BBCode special characters
     */
    private function escapeBb(string $str): string {
        // Replace brackets to prevent BBCode injection
        return str_replace(["[", "]"], ["(", ")"], $str);
    }

    /**
     * Convert TeamSpeak3_Helper_String to scalar value
     */
    private function toScalar($val) {
        if ($val instanceof TeamSpeak3_Helper_String) {
            return (string) $val;
        }
        return $val;
    }
}
