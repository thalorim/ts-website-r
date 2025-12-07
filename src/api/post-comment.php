<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Config;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "You must be logged in to post comments"]);
    exit;
}

$profileCldbid = isset($_POST["profile_cldbid"]) ? (int) $_POST["profile_cldbid"] : 0;
$comment = isset($_POST["comment"]) ? trim((string) $_POST["comment"]) : "";

if ($profileCldbid <= 0) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Invalid profile ID"]);
    exit;
}

if ($comment === "" || strlen($comment) > 1000) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Comment must be between 1 and 1000 characters"]);
    exit;
}

try {
    $db = DatabaseUtils::i()->getDb();
    $dbConfig = Config::i()->getDatabaseConfig();
    $prefix = isset($dbConfig["prefix"]) ? $dbConfig["prefix"] : "";
    $tableName = $prefix . "profile_comments";

    // Ensure table exists
    $db->query("CREATE TABLE IF NOT EXISTS `{$tableName}` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `profile_cldbid` INT(11) NOT NULL,
        `author_cldbid` INT(11) NOT NULL,
        `author_nickname` VARCHAR(128) NOT NULL,
        `comment` TEXT NOT NULL,
        `created_at` INT(11) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `profile_idx` (`profile_cldbid`),
        KEY `created_idx` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Insert comment
    $db->insert($tableName, [
        "profile_cldbid" => $profileCldbid,
        "author_cldbid" => Auth::getCldbid(),
        "author_nickname" => Auth::getNickname(),
        "comment" => $comment,
        "created_at" => time()
    ]);

    echo json_encode([
        "ok" => true,
        "comment" => [
            "id" => $db->id(),
            "author_cldbid" => Auth::getCldbid(),
            "author_nickname" => Auth::getNickname(),
            "comment" => htmlspecialchars($comment),
            "created_at" => time()
        ]
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Failed to post comment: " . $e->getMessage()]);
}
