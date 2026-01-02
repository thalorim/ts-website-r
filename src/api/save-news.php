<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\Utils;
use Wruczek\TSWebsite\Utils\NewsDisplayManager;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

if (!Auth::isLoggedIn() || Auth::getCldbid() !== 3) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "forbidden"]);
    exit;
}

$title = trim((string) (@$_POST["title"] ?? ''));
$content = (string) (@$_POST["content"] ?? '');

if ($title === '' || $content === '') {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "title and content required"]);
    exit;
}

try {
    Utils::getNewsStore()->addNews($title, $content);
    
    // Automatically update news display channels
    try {
        $newsManager = NewsDisplayManager::i();
        $newsManager->processAllNewsUpdates();
    } catch (\Exception $e) {
        // Log but don't fail the news save if channel update fails
        error_log("Failed to update news display channels: " . $e->getMessage());
    }
    
    echo json_encode(["ok" => true]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}

