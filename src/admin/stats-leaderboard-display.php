<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Utils\StatsDisplayManager;
use Wruczek\TSWebsite\Utils\CsrfUtils;
use Wruczek\TSWebsite\CacheManager;

require_once __DIR__ . "/../private/php/load.php";

// Check if user is logged in and is admin
if (!Auth::isLoggedIn()) {
    TemplateUtils::i()->renderErrorTemplate("401", "Unauthorized", "Please log in to access the admin panel.");
    exit;
}

$userCldbid = Auth::getCldbid();
if ($userCldbid !== 3) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You don't have permission to access this page.");
    exit;
}

$manager = StatsDisplayManager::i();
$manager->ensureTablesExist();

$message = null;
$error = null;

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    CsrfUtils::validateRequest();
    
    try {
        $action = $_POST["action"] ?? "";
        
        switch ($action) {
            case "add":
                $channelId = (int) ($_POST["channel_id"] ?? 0);
                $displayType = $_POST["display_type"] ?? 'combined';
                $topCount = (int) ($_POST["top_count"] ?? 10);
                
                if ($channelId <= 0) {
                    throw new \Exception("Invalid channel ID");
                }
                
                if ($topCount < 1 || $topCount > 50) {
                    throw new \Exception("Top count must be between 1 and 50");
                }
                
                if ($manager->addConfiguration($channelId, $displayType, $topCount)) {
                    $manager->updateChannelDescription($channelId, $displayType, $topCount);
                    $message = "Configuration added and channel updated successfully";
                } else {
                    throw new \Exception("Failed to add configuration (channel may already be configured)");
                }
                break;
                
            case "update":
                $id = (int) ($_POST["id"] ?? 0);
                $displayType = $_POST["display_type"] ?? 'combined';
                $topCount = (int) ($_POST["top_count"] ?? 10);
                
                if ($id <= 0) {
                    throw new \Exception("Invalid configuration ID");
                }
                
                if ($topCount < 1 || $topCount > 50) {
                    throw new \Exception("Top count must be between 1 and 50");
                }
                
                if ($manager->updateConfiguration($id, $displayType, $topCount)) {
                    $message = "Configuration updated successfully";
                } else {
                    throw new \Exception("Failed to update configuration");
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
                
            case "update_channel":
                $channelId = (int) ($_POST["channel_id"] ?? 0);
                $displayType = $_POST["display_type"] ?? 'combined';
                $topCount = (int) ($_POST["top_count"] ?? 10);
                
                if ($channelId <= 0) {
                    throw new \Exception("Invalid channel ID");
                }
                
                if ($manager->updateChannelDescription($channelId, $displayType, $topCount)) {
                    $message = "Channel updated successfully";
                } else {
                    throw new \Exception("Failed to update channel");
                }
                break;
                
            case "update_all":
                $stats = $manager->processAllStatsUpdates();
                $message = "Update complete: {$stats['updated']} updated, {$stats['failed']} failed (total: {$stats['total']})";
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

// Get channel list
$channels = [];
try {
    $channelList = CacheManager::i()->getChannelList();
    foreach ($channelList as $channel) {
        $channels[(int) $channel['cid']] = (string) $channel['channel_name'];
    }
} catch (\Exception $e) {
    // Ignore
}

// Get server stats
$serverStats = $manager->getServerStats();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stats & Leaderboard Display - Admin Panel</title>
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
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 28px;
            font-weight: bold;
        }
        .stat-label {
            font-size: 12px;
            opacity: 0.9;
        }
        .badge-active {
            background-color: #28a745;
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
            <h2><i class="fas fa-chart-line"></i> Stats & Leaderboard Display Configuration</h2>
            <p class="text-muted">Track user activity and display leaderboards in TeamSpeak channels</p>
            <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Admin Panel</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="stats-card">
            <h5><i class="fas fa-server"></i> Server Statistics Overview</h5>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($serverStats['total_users']) ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($serverStats['current_online']) ?></div>
                    <div class="stat-label">Online Now</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($serverStats['total_connections']) ?></div>
                    <div class="stat-label">Total Connections</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        <?php 
                            $hours = floor($serverStats['total_online_time'] / 3600);
                            echo number_format($hours) . 'h';
                        ?>
                    </div>
                    <div class="stat-label">Total Time</div>
                </div>
            </div>
        </div>

        <div class="info-box">
            <h5><i class="fas fa-info-circle"></i> About Stats & Leaderboards</h5>
            <p>This feature automatically tracks user connections and online time, then displays leaderboards in TeamSpeak channels with beautiful BBCode tables.</p>
            <p><strong>Bot Required:</strong> Run <code>php src/private/php/stats-tracking-bot.php --daemon</code> to start tracking.</p>
            <p><strong>Display Types:</strong></p>
            <ul class="mb-0">
                <li><strong>Combined:</strong> Server stats + top connections + top online time</li>
                <li><strong>Connections:</strong> Full leaderboard of most connections</li>
                <li><strong>Online Time:</strong> Full leaderboard of most online time</li>
                <li><strong>Server Stats:</strong> Overall server statistics only</li>
            </ul>
        </div>

        <div class="btn-group-actions mb-3">
            <button class="btn btn-primary" data-toggle="modal" data-target="#addConfigModal">
                <i class="fas fa-plus"></i> Add Channel Configuration
            </button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="update_all">
                <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                <button type="submit" class="btn btn-info">
                    <i class="fas fa-sync"></i> Update All Channels
                </button>
            </form>
        </div>

        <h4>Channel Configurations</h4>
        <table class="table table-striped table-bordered">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Channel</th>
                    <th>Display Type</th>
                    <th>Top Count</th>
                    <th>Last Updated</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($configurations)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">No configurations found. Add one to get started!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($configurations as $config): ?>
                        <?php
                            $configId = (int) $config['id'];
                            $channelId = (int) $config['channel_id'];
                            $channelName = $channels[$channelId] ?? "Unknown (ID: {$channelId})";
                            $displayType = $config['display_type'] ?? 'combined';
                            $topCount = (int) ($config['top_count'] ?? 10);
                            $lastUpdated = $config['last_updated'] ?? 'Never';
                            $enabled = (bool) ($config['enabled'] ?? true);
                            
                            $displayTypeLabels = [
                                'combined' => '<span class="badge badge-primary">Combined</span>',
                                'connections' => '<span class="badge badge-info">Connections</span>',
                                'online_time' => '<span class="badge badge-warning">Online Time</span>',
                                'server_stats' => '<span class="badge badge-success">Server Stats</span>'
                            ];
                            $displayLabel = $displayTypeLabels[$displayType] ?? $displayType;
                        ?>
                        <tr>
                            <td><?= $configId ?></td>
                            <td><?= htmlspecialchars($channelName) ?></td>
                            <td><?= $displayLabel ?></td>
                            <td><?= $topCount ?></td>
                            <td><small><?= htmlspecialchars($lastUpdated) ?></small></td>
                            <td>
                                <?php if ($enabled): ?>
                                    <span class="badge badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editModal<?= $configId ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display: inline;" class="ml-1">
                                    <input type="hidden" name="action" value="update_channel">
                                    <input type="hidden" name="channel_id" value="<?= $channelId ?>">
                                    <input type="hidden" name="display_type" value="<?= $displayType ?>">
                                    <input type="hidden" name="top_count" value="<?= $topCount ?>">
                                    <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                                    <button type="submit" class="btn btn-success btn-sm" title="Update now">
                                        <i class="fas fa-sync"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" class="ml-1" onsubmit="return confirm('Delete this configuration?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $configId ?>">
                                    <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        
                        <!-- Edit Modal -->
                        <div class="modal fade" id="editModal<?= $configId ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Configuration</h5>
                                        <button type="button" class="close" data-dismiss="modal">
                                            <span>&times;</span>
                                        </button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="id" value="<?= $configId ?>">
                                            <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                                            
                                            <div class="form-group">
                                                <label>Display Type</label>
                                                <select class="form-control" name="display_type" required>
                                                    <option value="combined" <?= $displayType === 'combined' ? 'selected' : '' ?>>Combined (Stats + Leaderboards)</option>
                                                    <option value="connections" <?= $displayType === 'connections' ? 'selected' : '' ?>>Connections Leaderboard</option>
                                                    <option value="online_time" <?= $displayType === 'online_time' ? 'selected' : '' ?>>Online Time Leaderboard</option>
                                                    <option value="server_stats" <?= $displayType === 'server_stats' ? 'selected' : '' ?>>Server Statistics</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Top Count (for leaderboards)</label>
                                                <input type="number" class="form-control" name="top_count" value="<?= $topCount ?>" min="1" max="50" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="mt-4">
            <h5><i class="fas fa-robot"></i> Running the Tracking Bot</h5>
            <p>To track user connections and online time:</p>
            <div class="bg-dark text-light p-3 rounded">
                <code class="text-success"># Test run</code><br>
                <code>php src/private/php/stats-tracking-bot.php --once</code><br><br>
                <code class="text-success"># Run as daemon (60 second tracking interval)</code><br>
                <code>php src/private/php/stats-tracking-bot.php --daemon --interval=60</code><br><br>
                <code class="text-success"># Run in background</code><br>
                <code>nohup php src/private/php/stats-tracking-bot.php --daemon > /tmp/stats-bot.log 2>&1 &</code>
            </div>
            <p class="mt-2"><small class="text-muted">Bot tracks users every 60 seconds and updates channels every 5 minutes.</small></p>
        </div>
    </div>

    <!-- Add Configuration Modal -->
    <div class="modal fade" id="addConfigModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Stats Channel Configuration</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                        
                        <div class="form-group">
                            <label>Channel *</label>
                            <select class="form-control" name="channel_id" required>
                                <option value="">Select a channel...</option>
                                <?php foreach ($channels as $cid => $cname): ?>
                                    <option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?> (ID: <?= $cid ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Display Type *</label>
                            <select class="form-control" name="display_type" required>
                                <option value="combined">Combined (Stats + Leaderboards)</option>
                                <option value="connections">Connections Leaderboard</option>
                                <option value="online_time">Online Time Leaderboard</option>
                                <option value="server_stats">Server Statistics</option>
                            </select>
                            <small class="form-text text-muted">What to display in the channel</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Top Count *</label>
                            <input type="number" class="form-control" name="top_count" value="10" min="1" max="50" required>
                            <small class="form-text text-muted">How many users to show in leaderboards (1-50)</small>
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
