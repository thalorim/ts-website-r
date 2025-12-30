<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Utils\StatusDisplayManager;
use Wruczek\TSWebsite\CacheManager;
use Wruczek\TSWebsite\Config;

require_once __DIR__ . "/../private/php/load.php";

// Check if user is logged in and is admin
if (!Auth::isLoggedIn()) {
    TemplateUtils::i()->renderErrorTemplate("401", "Unauthorized", "Please log in to access the admin panel.");
    exit;
}

// Simple admin check - you may want to enhance this based on your auth system
// For now, we'll check if user has admin server group
$adminGroups = Config::get("adminstatus_groups", []);
$userGroups = Auth::getServerGroups();
$isAdmin = false;

foreach ($adminGroups as $adminGroup) {
    if (in_array($adminGroup, $userGroups)) {
        $isAdmin = true;
        break;
    }
}

if (!$isAdmin) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You don't have permission to access this page.");
    exit;
}

$manager = StatusDisplayManager::i();
$manager->ensureTableExists();

$message = null;
$error = null;

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $action = $_POST["action"] ?? "";
        
        switch ($action) {
            case "add":
                $cldbid = (int) ($_POST["cldbid"] ?? 0);
                $channelId = (int) ($_POST["channel_id"] ?? 0);
                $serverGroupId = !empty($_POST["server_group_id"]) ? (int) $_POST["server_group_id"] : null;
                
                if ($cldbid <= 0 || $channelId <= 0) {
                    throw new \Exception("Invalid client database ID or channel ID");
                }
                
                if ($manager->addConfiguration($cldbid, $channelId, $serverGroupId)) {
                    $message = "Configuration added successfully";
                } else {
                    throw new \Exception("Failed to add configuration (it may already exist)");
                }
                break;
                
            case "delete":
                $id = (int) ($_POST["id"] ?? 0);
                
                if ($id <= 0) {
                    throw new \Exception("Invalid configuration ID");
                }
                
                if ($manager->removeConfiguration($id)) {
                    $message = "Configuration removed successfully";
                } else {
                    throw new \Exception("Failed to remove configuration");
                }
                break;
                
            case "test_update":
                $stats = $manager->processAllStatusUpdates();
                $message = "Test update completed: {$stats['updated']} updated, {$stats['failed']} failed, {$stats['skipped']} skipped (total: {$stats['total']})";
                break;
                
            default:
                throw new \Exception("Unknown action");
        }
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all configurations
$configurations = $manager->getConfigurations();

// Get channel list for dropdown
$channels = [];
try {
    $channelList = CacheManager::i()->getChannelList();
    foreach ($channelList as $channel) {
        $channels[(int) $channel['cid']] = (string) $channel['channel_name'];
    }
} catch (\Exception $e) {
    // Ignore
}

// Get server groups for dropdown
$serverGroups = [];
try {
    $groupList = CacheManager::i()->getServerGroupList();
    foreach ($groupList as $group) {
        $serverGroups[(int) $group['sgid']] = (string) $group['name'];
    }
} catch (\Exception $e) {
    // Ignore
}

// Get profiles for reference
$db = DatabaseUtils::i()->getDb();
$profiles = [];
try {
    $profileList = $db->select("profiles", ["cldbid", "nickname"]);
    foreach ($profileList as $profile) {
        $profiles[(int) $profile['cldbid']] = (string) ($profile['nickname'] ?? 'Unknown');
    }
} catch (\Exception $e) {
    // Table may not exist yet
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Display Configuration - Admin Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .config-table {
            margin-top: 20px;
        }
        .badge-online {
            background-color: #28a745;
        }
        .badge-offline {
            background-color: #dc3545;
        }
        .alert {
            margin-top: 15px;
        }
        .btn-group-actions {
            margin-bottom: 20px;
        }
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2><i class="fas fa-broadcast-tower"></i> TeamSpeak Status Display Configuration</h2>
            <p class="text-muted">Manage channel status display configurations for TeamSpeak users</p>
            <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Admin Panel</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <h5><i class="fas fa-info-circle"></i> About Status Display</h5>
            <p>This feature automatically updates TeamSpeak channel descriptions with user status information. When a configured user connects or disconnects, their status will be reflected in the specified channel.</p>
            <p><strong>Bot Script:</strong> Run <code>php src/private/php/status-display-bot.php --daemon</code> to start the monitoring bot.</p>
        </div>

        <div class="btn-group-actions">
            <button class="btn btn-primary" data-toggle="modal" data-target="#addConfigModal">
                <i class="fas fa-plus"></i> Add New Configuration
            </button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="test_update">
                <button type="submit" class="btn btn-info">
                    <i class="fas fa-sync"></i> Test Update All
                </button>
            </form>
        </div>

        <h4>Current Configurations</h4>
        <table class="table table-striped table-bordered config-table">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Client Database ID</th>
                    <th>Nickname</th>
                    <th>Channel ID</th>
                    <th>Channel Name</th>
                    <th>Server Group</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($configurations)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">No configurations found. Add one to get started!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($configurations as $config): ?>
                        <?php
                            $cldbid = (int) $config['cldbid'];
                            $nickname = $profiles[$cldbid] ?? "Unknown (ID: {$cldbid})";
                            $channelId = (int) $config['channel_id'];
                            $channelName = $channels[$channelId] ?? "Unknown (ID: {$channelId})";
                            $serverGroupId = isset($config['server_group_id']) ? (int) $config['server_group_id'] : null;
                            $groupName = $serverGroupId ? ($serverGroups[$serverGroupId] ?? "Group #{$serverGroupId}") : "N/A";
                            $enabled = (bool) $config['enabled'];
                        ?>
                        <tr>
                            <td><?= (int) $config['id'] ?></td>
                            <td><?= $cldbid ?></td>
                            <td><?= htmlspecialchars($nickname) ?></td>
                            <td><?= $channelId ?></td>
                            <td><?= htmlspecialchars($channelName) ?></td>
                            <td><?= htmlspecialchars($groupName) ?></td>
                            <td>
                                <?php if ($enabled): ?>
                                    <span class="badge badge-online">Enabled</span>
                                <?php else: ?>
                                    <span class="badge badge-offline">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this configuration?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $config['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="mt-4">
            <h5><i class="fas fa-terminal"></i> Running the Bot</h5>
            <p>To start the status display monitoring bot, run one of these commands on your server:</p>
            <div class="bg-dark text-light p-3 rounded">
                <code class="text-success"># Run once (for testing)</code><br>
                <code>php src/private/php/status-display-bot.php --once</code><br><br>
                <code class="text-success"># Run as daemon (continuous monitoring)</code><br>
                <code>php src/private/php/status-display-bot.php --daemon --interval=30</code><br><br>
                <code class="text-success"># Run in background</code><br>
                <code>nohup php src/private/php/status-display-bot.php --daemon > /dev/null 2>&1 &</code>
            </div>
        </div>
    </div>

    <!-- Add Configuration Modal -->
    <div class="modal fade" id="addConfigModal" tabindex="-1" role="dialog" aria-labelledby="addConfigModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addConfigModalLabel">Add Status Display Configuration</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-group">
                            <label for="cldbid">Client Database ID *</label>
                            <input type="number" class="form-control" id="cldbid" name="cldbid" required>
                            <small class="form-text text-muted">The TeamSpeak client database ID (cldbid) to monitor</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="channel_id">Channel ID *</label>
                            <select class="form-control" id="channel_id" name="channel_id" required>
                                <option value="">Select a channel...</option>
                                <?php foreach ($channels as $cid => $cname): ?>
                                    <option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?> (ID: <?= $cid ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">The channel where the status will be displayed</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="server_group_id">Server Group (Optional)</label>
                            <select class="form-control" id="server_group_id" name="server_group_id">
                                <option value="">None</option>
                                <?php foreach ($serverGroups as $sgid => $gname): ?>
                                    <option value="<?= $sgid ?>"><?= htmlspecialchars($gname) ?> (ID: <?= $sgid ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">The server group icon to display (optional)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Configuration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
