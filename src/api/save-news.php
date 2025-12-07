<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\Utils;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

if (!Auth::isLoggedIn() || Auth::getUid() !== "Jv/d1+pX7/q343RrIMPTTVpob+U=") {
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
    echo json_encode(["ok" => true]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}

