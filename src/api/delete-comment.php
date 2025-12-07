<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Config;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "You must be logged in"]);
    exit;
}

$commentId = isset($_POST["comment_id"]) ? (int) $_POST["comment_id"] : 0;

if ($commentId <= 0) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Invalid comment ID"]);
    exit;
}

try {
    $db = DatabaseUtils::i()->getDb();
    $dbConfig = Config::i()->getDatabaseConfig();
    $prefix = isset($dbConfig["prefix"]) ? $dbConfig["prefix"] : "";
    $tableName = $prefix . "profile_comments";

    // Get comment to check ownership
    $comment = $db->get($tableName, "*", ["id" => $commentId]);

    if (!$comment) {
        http_response_code(404);
        echo json_encode(["ok" => false, "error" => "Comment not found"]);
        exit;
    }

    // Check if user owns the comment or is admin (CLDBID 3)
    $userCldbid = Auth::getCldbid();
    if ($comment["author_cldbid"] != $userCldbid && $userCldbid !== 3) {
        http_response_code(403);
        echo json_encode(["ok" => false, "error" => "You can only delete your own comments"]);
        exit;
    }

    // Delete comment
    $db->delete($tableName, ["id" => $commentId]);

    echo json_encode(["ok" => true]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Failed to delete comment: " . $e->getMessage()]);
}
