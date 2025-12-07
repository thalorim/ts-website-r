<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\Utils\DatabaseUtils;

require_once __DIR__ . "/../private/php/load.php";

header('Content-Type: application/json');

// Check admin access
if (!Auth::isLoggedIn() || Auth::getUid() !== "Jv/d1+pX7/q343RrIMPTTVpob+U=") {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "forbidden"]);
    exit;
}

$db = DatabaseUtils::i()->getDb();
$dbConfig = Config::i()->getDatabaseConfig();
$prefix = isset($dbConfig["prefix"]) ? $dbConfig["prefix"] : "";
$tableName = $prefix . "clan_groups";

// Ensure table exists
try {
    $existsStmt = $db->query("SHOW TABLES LIKE '" . addslashes($tableName) . "'");
    $exists = $existsStmt && $existsStmt->fetchColumn();

    if (!$exists) {
        $createSql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `group_id` INT(11) NOT NULL,
            `clan_name` VARCHAR(255) DEFAULT NULL,
            `clan_description` TEXT NULL,
            `clan_avatar` VARCHAR(255) DEFAULT NULL,
            `editor_cldbids` TEXT NULL,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_group_id` (`group_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $db->query($createSql);
    } else {
        // Add editor_cldbids column if it doesn't exist
        try {
            $columnsStmt = $db->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'editor_cldbids'");
            $columnExists = $columnsStmt && $columnsStmt->fetchColumn();
            if (!$columnExists) {
                $db->query("ALTER TABLE `{$tableName}` ADD COLUMN `editor_cldbids` TEXT NULL AFTER `clan_avatar`");
            }
        } catch (\Exception $e) {
            // Non-fatal
        }
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Database error: " . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Get single clan group or list all
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if ($id > 0) {
            $group = $db->get("clan_groups", "*", ["id" => $id]);
            if ($group) {
                echo json_encode(["ok" => true, "data" => $group]);
            } else {
                http_response_code(404);
                echo json_encode(["ok" => false, "error" => "Clan group not found"]);
            }
        } else {
            $groups = $db->select("clan_groups", "*", ["ORDER" => ["group_id" => "ASC"]]);
            echo json_encode(["ok" => true, "data" => $groups]);
        }
    } elseif ($method === 'POST') {
        // Create or update clan group
        $id = isset($_POST['clan_group_id']) && $_POST['clan_group_id'] !== '' ? (int) $_POST['clan_group_id'] : 0;
        $groupId = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;
        $clanName = isset($_POST['clan_name']) ? trim((string) $_POST['clan_name']) : '';
        $clanDescription = isset($_POST['clan_description']) ? trim((string) $_POST['clan_description']) : null;
        $editorCldbids = isset($_POST['editor_cldbids']) ? trim((string) $_POST['editor_cldbids']) : '';
        
        // Validate
        if ($groupId <= 0) {
            throw new \InvalidArgumentException("Group ID must be a positive integer");
        }
        if ($clanName === '') {
            throw new \InvalidArgumentException("Clan name is required");
        }
        
        // Validate and normalize editor_cldbids
        if ($editorCldbids !== '') {
            $cldbidArray = array_map('intval', array_filter(explode(',', $editorCldbids), function($v) {
                return trim($v) !== '' && is_numeric(trim($v));
            }));
            $editorCldbids = implode(',', $cldbidArray);
        } else {
            $editorCldbids = null;
        }
        
        $data = [
            'group_id' => $groupId,
            'clan_name' => $clanName,
            'clan_description' => $clanDescription !== '' ? $clanDescription : null,
            'editor_cldbids' => $editorCldbids,
        ];
        
        if ($id > 0) {
            // Update existing
            $existing = $db->get("clan_groups", "*", ["id" => $id]);
            if (!$existing) {
                throw new \InvalidArgumentException("Clan group not found");
            }
            
            // Check if changing group_id would conflict
            if ((int)$existing['group_id'] !== $groupId) {
                $conflict = $db->get("clan_groups", "*", ["group_id" => $groupId]);
                if ($conflict) {
                    throw new \InvalidArgumentException("A clan group with Group ID {$groupId} already exists");
                }
            }
            
            $db->update("clan_groups", $data, ["id" => $id]);
            echo json_encode(["ok" => true, "message" => "Clan group updated"]);
        } else {
            // Create new
            $existing = $db->get("clan_groups", "*", ["group_id" => $groupId]);
            if ($existing) {
                throw new \InvalidArgumentException("A clan group with Group ID {$groupId} already exists");
            }
            
            $db->insert("clan_groups", $data);
            echo json_encode(["ok" => true, "message" => "Clan group created"]);
        }
    } elseif ($method === 'DELETE') {
        // Delete clan group
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        
        if ($id <= 0) {
            throw new \InvalidArgumentException("Invalid ID");
        }
        
        $existing = $db->get("clan_groups", "*", ["id" => $id]);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(["ok" => false, "error" => "Clan group not found"]);
            exit;
        }
        
        $db->delete("clan_groups", ["id" => $id]);
        echo json_encode(["ok" => true, "message" => "Clan group deleted"]);
    } else {
        http_response_code(405);
        echo json_encode(["ok" => false, "error" => "Method not allowed"]);
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}
