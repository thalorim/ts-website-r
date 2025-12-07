<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\News\DefaultNewsStore;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

if (!Auth::isLoggedIn() || Auth::getCldbid() !== 3) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "forbidden"]);
    exit;
}

try {
    $newsId = isset($_POST["newsid"]) ? (int) $_POST["newsid"] : 0;
    
    if ($newsId <= 0) {
        throw new \InvalidArgumentException("Invalid news ID");
    }

    $newsStore = new DefaultNewsStore();
    $success = $newsStore->deleteNews($newsId);

    if ($success) {
        echo json_encode(["ok" => true]);
    } else {
        throw new \Exception("Failed to delete news post");
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}
