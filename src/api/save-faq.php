<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\DatabaseUtils;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

if (!Auth::isLoggedIn() || Auth::getCldbid() !== 3) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "forbidden"]);
    exit;
}

$action = trim((string) (@$_POST["action"] ?? 'add'));
$question = trim((string) (@$_POST["question"] ?? ''));
$answer = (string) (@$_POST["answer"] ?? '');

if ($question === '' || $answer === '') {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "question and answer required"]);
    exit;
}

try {
    $db = DatabaseUtils::i()->getDb();
    
    if ($action === 'edit') {
        $faqId = isset($_POST["faqid"]) ? (int) $_POST["faqid"] : 0;
        
        if ($faqId <= 0) {
            throw new \InvalidArgumentException("Invalid FAQ ID");
        }
        
        $update = $db->update("faq", [
            "question" => $question,
            "answer" => $answer
        ], [
            "id" => $faqId
        ]);
        
        if ($update->rowCount() === 0) {
            throw new \Exception("Failed to update FAQ");
        }
        
        echo json_encode(["ok" => true, "action" => "edit"]);
    } else {
        $db->insert("faq", [
            "question" => $question,
            "answer" => $answer
        ]);
        
        echo json_encode(["ok" => true, "action" => "add"]);
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}
