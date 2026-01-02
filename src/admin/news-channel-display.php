<?php

use Wruczek\TSWebsite\Auth;
use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\TemplateUtils;
use Wruczek\TSWebsite\Utils\NewsDisplayManager;
use Wruczek\TSWebsite\Utils\CsrfUtils;
use Wruczek\TSWebsite\CacheManager;

require_once __DIR__ . "/../private/php/load.php";

// Check if user is logged in and is admin
// Use the same simple check as the main admin panel (CLDBID 3)
if (!Auth::isLoggedIn()) {
    TemplateUtils::i()->renderErrorTemplate("401", "Unauthorized", "Please log in to access the admin panel.");
    exit;
}

// Simple admin check - only CLDBID 3 can access (same as main admin panel)
// You can modify this to suit your needs
$userCldbid = Auth::getCldbid();
if ($userCldbid !== 3) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You don't have permission to access this page. Only CLDBID 3 can access the admin panel.");
    exit;
}

$manager = NewsDisplayManager::i();
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
                $selectedNewsIds = isset($_POST["selected_news"]) && is_array($_POST["selected_news"]) 
                    ? implode(',', array_map('intval', $_POST["selected_news"])) 
                    : null;
                
                if ($channelId <= 0) {
                    throw new \Exception("Invalid channel ID");
                }
                
                if ($newsLimit < 1 || $newsLimit > 20) {
                    throw new \Exception("News limit must be between 1 and 20");
                }
                
                if ($manager->addConfiguration($channelId, $newsLimit, $selectedNewsIds)) {
                    // Immediately update the channel with news
                    $manager->updateChannelDescription($channelId, $newsLimit, $selectedNewsIds);
                    $message = "Configuration added successfully and channel updated with news";
                } else {
                    throw new \Exception("Failed to add configuration (channel may already be configured)");
                }
                break;
                
            case "update":
                $id = (int) ($_POST["id"] ?? 0);
                $newsLimit = (int) ($_POST["news_limit"] ?? 5);
                
                if ($id <= 0) {
                    throw new \Exception("Invalid configuration ID");
                }
                
                if ($newsLimit < 1 || $newsLimit > 20) {
                    throw new \Exception("News limit must be between 1 and 20");
                }
                
                if ($manager->updateConfiguration($id, $newsLimit)) {
                    $message = "Configuration updated successfully";
                } else {
                    throw new \Exception("Failed to update configuration");
                }
                break;
                
            case "update_selected_news":
                $id = (int) ($_POST["id"] ?? 0);
                $selectedNewsIds = isset($_POST["selected_news"]) && is_array($_POST["selected_news"]) 
                    ? implode(',', array_map('intval', $_POST["selected_news"])) 
                    : '';
                
                if ($id <= 0) {
                    throw new \Exception("Invalid configuration ID");
                }
                
                // Get current config
                $configs = $manager->getConfigurations();
                $config = null;
                foreach ($configs as $c) {
                    if ((int)$c['id'] === $id) {
                        $config = $c;
                        break;
                    }
                }
                
                if (!$config) {
                    throw new \Exception("Configuration not found");
                }
                
                $newsLimit = (int)($config['news_limit'] ?? 5);
                $channelId = (int)($config['channel_id'] ?? 0);
                
                if ($manager->updateConfiguration($id, $newsLimit, $selectedNewsIds)) {
                    // Immediately update the channel
                    $manager->updateChannelDescription($channelId, $newsLimit, $selectedNewsIds);
                    $message = "Selected news updated and channel refreshed";
                } else {
                    throw new \Exception("Failed to update selected news");
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
                $id = (int) ($_POST["id"] ?? 0);
                $channelId = (int) ($_POST["channel_id"] ?? 0);
                $newsLimit = (int) ($_POST["news_limit"] ?? 5);
                $selectedNewsIds = $_POST["selected_news_ids"] ?? null;
                
                if ($id <= 0 || $channelId <= 0) {
                    throw new \Exception("Invalid configuration or channel ID");
                }
                
                if ($manager->updateChannelDescription($channelId, $newsLimit, $selectedNewsIds)) {
                    $message = "Channel description updated successfully";
                } else {
                    throw new \Exception("Failed to update channel description");
                }
                break;
                
            case "update_all":
                $stats = $manager->processAllNewsUpdates();
                $message = "Bulk update completed: {$stats['updated']} updated, {$stats['failed']} failed (total: {$stats['total']})";
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

// Get news count and list from database
$db = DatabaseUtils::i()->getDb();
$newsCount = 0;
$allNews = [];
try {
    $newsCount = $db->count("news");
    $allNews = $db->select("news", ["newsid", "title", "added"], [
        "ORDER" => ["added" => "DESC"]
    ]);
} catch (\Exception $e) {
    // Table may not exist yet
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Channel Display Configuration - Admin Panel</title>
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
        .badge-active {
            background-color: #28a745;
        }
        .badge-inactive {
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
        .stats-box {
            background-color: #f0f0f0;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
            display: inline-block;
            min-width: 150px;
        }
        .stats-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        .stats-label {
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2><i class="fas fa-newspaper"></i> News Channel Display Configuration</h2>
            <p class="text-muted">Manage channel configurations to display news from your database on TeamSpeak channel descriptions</p>
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
            <p>This feature automatically displays your latest news in TeamSpeak channel descriptions. Configure which channels should display news and how many news items to show.</p>
            <p><strong>Auto-Update:</strong> Channels are updated when you add/edit news or when you manually trigger an update here.</p>
        </div>

        <div class="mb-3">
            <div class="stats-box">
                <div class="stats-number"><?= $newsCount ?></div>
                <div class="stats-label">Total News Items</div>
            </div>
            <div class="stats-box ml-3">
                <div class="stats-number"><?= count($configurations) ?></div>
                <div class="stats-label">Configured Channels</div>
            </div>
        </div>

        <div class="btn-group-actions">
            <button class="btn btn-primary" data-toggle="modal" data-target="#addConfigModal">
                <i class="fas fa-plus"></i> Add Channel Configuration
            </button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="update_all">
                <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                <button type="submit" class="btn btn-info">
                    <i class="fas fa-sync"></i> Update All Channels Now
                </button>
            </form>
        </div>

        <h4>Current Configurations</h4>
        <table class="table table-striped table-bordered config-table">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Channel ID</th>
                    <th>Channel Name</th>
                    <th>Display Mode</th>
                    <th>News Limit</th>
                    <th>Last Updated</th>
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
                            $configId = (int) $config['id'];
                            $channelId = (int) $config['channel_id'];
                            $channelName = $channels[$channelId] ?? "Unknown (ID: {$channelId})";
                            $newsLimit = (int) ($config['news_limit'] ?? 5);
                            $selectedNewsIds = isset($config['selected_news_ids']) && !empty($config['selected_news_ids']) 
                                ? $config['selected_news_ids'] 
                                : null;
                            $selectedNewsArray = $selectedNewsIds ? explode(',', $selectedNewsIds) : [];
                            $lastUpdated = isset($config['last_updated']) && $config['last_updated'] ? $config['last_updated'] : 'Never';
                            $enabled = (bool) ($config['enabled'] ?? true);
                            
                            // Determine display mode
                            if (!empty($selectedNewsIds)) {
                                $displayMode = '<span class="badge badge-info">Specific News</span>';
                                $displayModeText = count($selectedNewsArray) . ' news selected';
                            } else {
                                $displayMode = '<span class="badge badge-secondary">Latest News</span>';
                                $displayModeText = 'Show latest';
                            }
                        ?>
                        <tr>
                            <td><?= $configId ?></td>
                            <td><?= $channelId ?></td>
                            <td><?= htmlspecialchars($channelName) ?></td>
                            <td>
                                <?= $displayMode ?>
                                <button type="button" class="btn btn-sm btn-outline-primary ml-1" 
                                        data-toggle="modal" 
                                        data-target="#selectNewsModal<?= $configId ?>"
                                        title="Select news to display">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                            <td>
                                <form method="POST" style="display: inline-flex; align-items: center;">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= $configId ?>">
                                    <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                                    <input type="number" name="news_limit" value="<?= $newsLimit ?>" min="1" max="20" class="form-control form-control-sm" style="width: 70px; display: inline-block;">
                                    <button type="submit" class="btn btn-sm btn-outline-primary ml-1" title="Update limit">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </form>
                            </td>
                            <td><?= htmlspecialchars($lastUpdated) ?></td>
                            <td>
                                <?php if ($enabled): ?>
                                    <span class="badge badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;" class="mr-1">
                                    <input type="hidden" name="action" value="update_channel">
                                    <input type="hidden" name="id" value="<?= $configId ?>">
                                    <input type="hidden" name="channel_id" value="<?= $channelId ?>">
                                    <input type="hidden" name="news_limit" value="<?= $newsLimit ?>">
                                    <input type="hidden" name="selected_news_ids" value="<?= htmlspecialchars($selectedNewsIds ?? '') ?>">
                                    <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                                    <button type="submit" class="btn btn-success btn-sm" title="Update channel now">
                                        <i class="fas fa-sync"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this configuration?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $configId ?>">
                                    <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        
                        <!-- Select News Modal for this configuration -->
                        <div class="modal fade" id="selectNewsModal<?= $configId ?>" tabindex="-1" role="dialog">
                            <div class="modal-dialog modal-lg" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Select News to Display</h5>
                                        <button type="button" class="close" data-dismiss="modal">
                                            <span>&times;</span>
                                        </button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="update_selected_news">
                                            <input type="hidden" name="id" value="<?= $configId ?>">
                                            <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                                            
                                            <p class="text-muted">
                                                <i class="fas fa-info-circle"></i> 
                                                <strong>Leave all unchecked to show latest news automatically.</strong><br>
                                                Or select specific news items to display (ignore News Limit if specific news selected).
                                            </p>
                                            
                                            <div class="form-group">
                                                <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                                                    <?php if (empty($allNews)): ?>
                                                        <p class="text-muted">No news items found in database.</p>
                                                    <?php else: ?>
                                                        <?php foreach ($allNews as $newsItem): ?>
                                                            <?php
                                                                $newsId = (int)$newsItem['newsid'];
                                                                $newsTitle = htmlspecialchars($newsItem['title']);
                                                                $newsDate = isset($newsItem['added']) && $newsItem['added'] > 0 
                                                                    ? date('M j, Y', (int)$newsItem['added']) 
                                                                    : 'Unknown date';
                                                                $isChecked = in_array($newsId, $selectedNewsArray) ? 'checked' : '';
                                                            ?>
                                                            <div class="custom-control custom-checkbox mb-2">
                                                                <input type="checkbox" 
                                                                       class="custom-control-input" 
                                                                       id="news_<?= $configId ?>_<?= $newsId ?>" 
                                                                       name="selected_news[]" 
                                                                       value="<?= $newsId ?>"
                                                                       <?= $isChecked ?>>
                                                                <label class="custom-control-label" for="news_<?= $configId ?>_<?= $newsId ?>">
                                                                    <strong><?= $newsTitle ?></strong> 
                                                                    <small class="text-muted">(ID: <?= $newsId ?>, <?= $newsDate ?>)</small>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Save & Update Channel
                                            </button>
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
            <h5><i class="fas fa-robot"></i> Automatic Updates</h5>
            <p>To automatically update channels when news is added/edited, the bot script monitors for changes and updates channels accordingly.</p>
            <div class="bg-dark text-light p-3 rounded">
                <code class="text-success"># Run once (for testing)</code><br>
                <code>php src/private/php/news-display-bot.php --once</code><br><br>
                <code class="text-success"># Run as daemon (continuous monitoring)</code><br>
                <code>php src/private/php/news-display-bot.php --daemon --interval=300</code><br><br>
                <code class="text-success"># Run in background</code><br>
                <code>nohup php src/private/php/news-display-bot.php --daemon > /dev/null 2>&1 &</code>
            </div>
            <p class="mt-2"><small class="text-muted">The bot checks for news updates every 5 minutes (300 seconds) by default and updates all configured channels.</small></p>
        </div>
    </div>

    <!-- Add Configuration Modal -->
    <div class="modal fade" id="addConfigModal" tabindex="-1" role="dialog" aria-labelledby="addConfigModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addConfigModalLabel">Add News Channel Display Configuration</h5>
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
                            <small class="form-text text-muted">The channel where news will be displayed in the description</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="news_limit">Number of News Items *</label>
                            <input type="number" class="form-control" id="news_limit" name="news_limit" value="5" min="1" max="20" required>
                            <small class="form-text text-muted">How many news items to display (1-20) - only used if no specific news selected below</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Select Specific News (Optional)</label>
                            <p class="text-muted small">
                                <i class="fas fa-info-circle"></i> 
                                Leave unchecked to automatically show latest news. Or check specific news items to display only those.
                            </p>
                            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                                <?php if (empty($allNews)): ?>
                                    <p class="text-muted">No news items found in database.</p>
                                <?php else: ?>
                                    <?php foreach ($allNews as $newsItem): ?>
                                        <?php
                                            $newsId = (int)$newsItem['newsid'];
                                            $newsTitle = htmlspecialchars($newsItem['title']);
                                            $newsDate = isset($newsItem['added']) && $newsItem['added'] > 0 
                                                ? date('M j, Y', (int)$newsItem['added']) 
                                                : 'Unknown date';
                                        ?>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" 
                                                   class="custom-control-input" 
                                                   id="add_news_<?= $newsId ?>" 
                                                   name="selected_news[]" 
                                                   value="<?= $newsId ?>">
                                            <label class="custom-control-label" for="add_news_<?= $newsId ?>">
                                                <strong><?= $newsTitle ?></strong> 
                                                <small class="text-muted">(ID: <?= $newsId ?>, <?= $newsDate ?>)</small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
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
