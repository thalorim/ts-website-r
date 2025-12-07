<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\Utils;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

if (!Auth::isLoggedIn() || Auth::getCldbid() !== 3) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "forbidden"]);
    exit;
}

$action = trim((string) (@$_POST["action"] ?? 'add'));
$title = trim((string) (@$_POST["title"] ?? ''));
$content = (string) (@$_POST["content"] ?? '');

if ($title === '' || $content === '') {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "title and content required"]);
    exit;
}

try {
    if ($action === 'edit') {
        $newsId = isset($_POST["newsid"]) ? (int) $_POST["newsid"] : 0;
        
        if ($newsId <= 0) {
            throw new \InvalidArgumentException("Invalid news ID");
        }
        
        $success = Utils::getNewsStore()->editNews($newsId, $title, $content, null, time());
        
        if (!$success) {
            throw new \Exception("Failed to update news post");
        }
        
        echo json_encode(["ok" => true, "action" => "edit"]);
    } else {
        Utils::getNewsStore()->addNews($title, $content);
        echo json_encode(["ok" => true, "action" => "add"]);
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}

