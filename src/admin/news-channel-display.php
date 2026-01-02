<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Utils\NewsChannelDisplayManager;
use Wruczek\TSWebsite\Utils\CsrfUtils;
use Wruczek\TSWebsite\CacheManager;
use Wruczek\TSWebsite\Config;

require_once __DIR__ . "/../private/php/load.php";

// Check if user is logged in and is admin
if (!Auth::isLoggedIn()) {
    TemplateUtils::i()->renderErrorTemplate("401", "Unauthorized", "Please log in to access the admin panel.");
    exit;
}

// Simple admin check - only CLDBID 116527 can access
// You can modify this to suit your needs
$userCldbid = Auth::getCldbid();
if ($userCldbid !== 116527) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You don't have permission to access this page. Only CLDBID 116527 can access the admin panel.");
    exit;
}

$manager = NewsChannelDisplayManager::i();
$manager->ensureTableExists();

$message = null;
$error = null;

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate CSRF token
    CsrfUtils::validateRequest();
    
    try {
        $action = $_POST["action"] ?? "";
        
        switch ($action) {
            case "add":
                $channelId = (int) ($_POST["channel_id"] ?? 0);
                $newsLimit = (int) ($_POST["news_limit"] ?? 5);
                $showContent = isset($_POST["show_content"]) ? (bool) $_POST["show_content"] : true;
                $contentMaxLength = (int) ($_POST["content_max_length"] ?? 200);
                
                if ($channelId <= 0) {
                    throw new \Exception("Invalid channel ID");
                }
                
                if ($newsLimit < 1 || $newsLimit > 20) {
                    throw new \Exception("News limit must be between 1 and 20");
                }
                
                if ($contentMaxLength < 50 || $contentMaxLength > 1000) {
                    throw new \Exception("Content max length must be between 50 and 1000");
                }
                
                if ($manager->addConfiguration($channelId, $newsLimit, $showContent, $contentMaxLength)) {
                    $message = "Configuration added successfully";
                } else {
                    throw new \Exception("Failed to add configuration (a configuration for this channel may already exist)");
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
                
            case "update_single":
                $id = (int) ($_POST["id"] ?? 0);
                
                if ($id <= 0) {
                    throw new \Exception("Invalid configuration ID");
                }
                
                $result = $manager->updateChannelNews($id);
                
                if ($result["success"]) {
                    $newsCount = $result["news_count"] ?? 0;
                    $message = "Channel updated successfully with {$newsCount} news items";
                } else {
                    throw new \Exception("Failed to update channel: " . ($result["error"] ?? "Unknown error"));
                }
                break;
                
            case "update_all":
                $stats = $manager->updateAllChannels();
                $message = "Update completed: {$stats['updated']} updated, {$stats['failed']} failed, {$stats['skipped']} skipped (total: {$stats['total']})";
                
                if ($stats["failed"] > 0) {
                    $error = "Some updates failed. Check the details below.";
                }
                break;
                
            case "toggle_enabled":
                $id = (int) ($_POST["id"] ?? 0);
                $enabled = isset($_POST["enabled"]) ? (bool) $_POST["enabled"] : false;
                
                if ($id <= 0) {
                    throw new \Exception("Invalid configuration ID");
                }
                
                if ($manager->updateConfiguration($id, null, null, null, null, $enabled)) {
                    $message = "Configuration " . ($enabled ? "enabled" : "disabled") . " successfully";
                } else {
                    throw new \Exception("Failed to toggle configuration");
                }
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Channel Display - Admin Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
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
            background-color: #6c757d;
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
        .action-buttons {
            white-space: nowrap;
        }
        .action-buttons form {
            display: inline-block;
            margin-right: 5px;
        }
        .last-updated {
            font-size: 0.85em;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2><i class="fas fa-newspaper"></i> News Channel Display Configuration</h2>
            <p class="text-muted">Manage TeamSpeak channels that display latest news from your website</p>
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
            <h5><i class="fas fa-info-circle"></i> About News Channel Display</h5>
            <p>This feature automatically updates TeamSpeak channel descriptions with the latest news from your website. The news is fetched from the database and formatted in BBCode for display in channel descriptions.</p>
            <p><strong>Features:</strong></p>
            <ul class="mb-0">
                <li>Configure multiple channels to display news</li>
                <li>Control how many news items to display (1-20)</li>
                <li>Choose whether to show news content or just titles</li>
                <li>Set maximum content length for preview</li>
                <li>Update channels on-demand with a single click</li>
            </ul>
        </div>

        <div class="btn-group-actions">
            <button class="btn btn-primary" data-toggle="modal" data-target="#addConfigModal">
                <i class="fas fa-plus"></i> Add New Configuration
            </button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="update_all">
                <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                <button type="submit" class="btn btn-success" onclick="return confirm('Update all enabled channels with latest news?');">
                    <i class="fas fa-sync-alt"></i> Update All Channels
                </button>
            </form>
        </div>

        <h4>Current Configurations</h4>
        <div class="table-responsive">
            <table class="table table-striped table-bordered config-table">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Channel</th>
                        <th>News Limit</th>
                        <th>Show Content</th>
                        <th>Max Length</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th style="min-width: 200px;">Actions</th>
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
                                $channelId = (int) $config['channel_id'];
                                $channelName = $channels[$channelId] ?? "Unknown (ID: {$channelId})";
                                $enabled = (bool) $config['enabled'];
                                $lastUpdated = isset($config['last_updated']) && $config['last_updated'] > 0
                                    ? date("Y-m-d H:i:s", (int) $config['last_updated'])
                                    : "Never";
                            ?>
                            <tr>
                                <td><?= (int) $config['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($channelName) ?></strong><br>
                                    <small class="text-muted">CID: <?= $channelId ?></small>
                                </td>
                                <td><?= (int) $config['news_limit'] ?></td>
                                <td>
                                    <?php if ((bool) $config['show_content']): ?>
                                        <span class="badge badge-success">Yes</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $config['content_max_length'] ?> chars</td>
                                <td>
                                    <?php if ($enabled): ?>
                                        <span class="badge badge-online">Enabled</span>
                                    <?php else: ?>
                                        <span class="badge badge-offline">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td class="last-updated"><?= htmlspecialchars($lastUpdated) ?></td>
                                <td class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_single">
                                        <input type="hidden" name="id" value="<?= (int) $config['id'] ?>">
                                        <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                                        <button type="submit" class="btn btn-info btn-sm" title="Update this channel now">
                                            <i class="fas fa-sync"></i>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_enabled">
                                        <input type="hidden" name="id" value="<?= (int) $config['id'] ?>">
                                        <input type="hidden" name="enabled" value="<?= $enabled ? '0' : '1' ?>">
                                        <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                                        <button type="submit" class="btn btn-<?= $enabled ? 'warning' : 'success' ?> btn-sm" title="<?= $enabled ? 'Disable' : 'Enable' ?>">
                                            <i class="fas fa-<?= $enabled ? 'pause' : 'play' ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this configuration?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $config['id'] ?>">
                                        <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            <h5><i class="fas fa-lightbulb"></i> Usage Tips</h5>
            <ul>
                <li><strong>Channel Selection:</strong> Choose a channel where you want to display news (typically an info or announcement channel)</li>
                <li><strong>News Limit:</strong> Control how many recent news items to display (1-20). Lower numbers work better for shorter descriptions</li>
                <li><strong>Show Content:</strong> Enable to show news preview content, or disable to show only titles and dates</li>
                <li><strong>Max Length:</strong> Control how many characters of content to show (50-1000). Longer content will be truncated</li>
                <li><strong>Manual Updates:</strong> Click the sync button <i class="fas fa-sync"></i> to update a channel immediately</li>
                <li><strong>Bulk Updates:</strong> Use "Update All Channels" to refresh all enabled configurations at once</li>
            </ul>
        </div>

        <div class="mt-4">
            <h5><i class="fas fa-robot"></i> Automatic Updates (Optional)</h5>
            <p>You can set up automatic updates using a cron job. Add this to your crontab to update news channels every hour:</p>
            <div class="bg-dark text-light p-3 rounded">
                <code class="text-success"># Update news channels every hour</code><br>
                <code>0 * * * * cd /path/to/your/website && php -r "require 'src/private/php/load.php'; \Wruczek\TSWebsite\Utils\NewsChannelDisplayManager::i()->updateAllChannels();"</code>
            </div>
        </div>
    </div>

    <!-- Add Configuration Modal -->
    <div class="modal fade" id="addConfigModal" tabindex="-1" role="dialog" aria-labelledby="addConfigModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addConfigModalLabel">Add News Channel Configuration</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                        
                        <div class="form-group">
                            <label for="channel_id">Channel *</label>
                            <select class="form-control" id="channel_id" name="channel_id" required>
                                <option value="">Select a channel...</option>
                                <?php foreach ($channels as $cid => $cname): ?>
                                    <option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?> (ID: <?= $cid ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">The channel where news will be displayed</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="news_limit">Number of News Items *</label>
                            <input type="number" class="form-control" id="news_limit" name="news_limit" value="5" min="1" max="20" required>
                            <small class="form-text text-muted">How many recent news items to display (1-20)</small>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="show_content" name="show_content" value="1" checked>
                                <label class="custom-control-label" for="show_content">Show News Content</label>
                            </div>
                            <small class="form-text text-muted">Show a preview of the news content (not just titles)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="content_max_length">Maximum Content Length *</label>
                            <input type="number" class="form-control" id="content_max_length" name="content_max_length" value="200" min="50" max="1000" required>
                            <small class="form-text text-muted">Maximum characters of content to display (50-1000)</small>
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
