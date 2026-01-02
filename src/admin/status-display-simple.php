<?php
/**
 * Simple Status Display Admin Interface (Debug Version)
 * This version shows errors and doesn't require strict authentication
 * Use this to debug issues with the main status-display.php
 */

// Show all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><title>Status Display Admin</title>";
echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css'>";
echo "<style>body{padding:20px;} .error{background:#fee;padding:10px;border:1px solid red;margin:10px 0;}</style>";
echo "</head><body><div class='container'>";

try {
    require_once __DIR__ . "/../private/php/load.php";
    echo "<div class='alert alert-success'>✓ Framework loaded</div>";
} catch (Exception $e) {
    echo "<div class='error'><strong>Error loading framework:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
    echo "</body></html>";
    exit;
}

use Wruczek\TSWebsite\Utils\DatabaseUtils;
use Wruczek\TSWebsite\Utils\CsrfUtils;
use Wruczek\TSWebsite\Config;

echo "<h1>Status Display Admin (Simple Version)</h1>";
echo "<p class='text-muted'>This is a simplified debug version. Use this to troubleshoot issues.</p>";

// Load StatusDisplayManager
try {
    require_once __DIR__ . "/../private/php/Utils/StatusDisplayManager.php";
    echo "<div class='alert alert-success'>✓ StatusDisplayManager loaded</div>";
} catch (Exception $e) {
    echo "<div class='error'><strong>Error loading StatusDisplayManager:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
    echo "</body></html>";
    exit;
}

use Wruczek\TSWebsite\Utils\StatusDisplayManager;

$manager = StatusDisplayManager::i();
$message = null;
$error = null;

// Ensure table exists
try {
    $manager->ensureTableExists();
    echo "<div class='alert alert-success'>✓ Database table exists</div>";
} catch (Exception $e) {
    echo "<div class='error'><strong>Error with database table:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<p>You may need to create the table manually. See INSTALL_NOW.txt</p>";
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate CSRF token
    $csrfValid = false;
    if (isset($_POST["csrf-token"])) {
        $csrfValid = CsrfUtils::validateToken($_POST["csrf-token"]);
    }
    
    if (!$csrfValid) {
        echo "<div class='alert alert-danger'>CSRF token validation failed. Please try again.</div>";
    } else {
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
        }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Display messages
if ($message) {
    echo "<div class='alert alert-success'>{$message}</div>";
}
if ($error) {
    echo "<div class='alert alert-danger'>{$error}</div>";
}

// Get configurations
try {
    $configurations = $manager->getConfigurations();
    echo "<div class='alert alert-info'>Found " . count($configurations) . " configuration(s)</div>";
} catch (Exception $e) {
    echo "<div class='error'>Error loading configurations: " . htmlspecialchars($e->getMessage()) . "</div>";
    $configurations = [];
}

?>

<div class="card mb-3">
    <div class="card-header">
        <h3>Add New Configuration</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
            
            <div class="form-group">
                <label>Client Database ID (cldbid)</label>
                <input type="number" class="form-control" name="cldbid" required placeholder="e.g., 116527">
                <small class="form-text text-muted">The TeamSpeak user's database ID to monitor</small>
            </div>
            
            <div class="form-group">
                <label>Channel ID</label>
                <input type="number" class="form-control" name="channel_id" required placeholder="e.g., 251746">
                <small class="form-text text-muted">The channel where status will be displayed</small>
            </div>
            
            <div class="form-group">
                <label>Server Group ID (optional)</label>
                <input type="number" class="form-control" name="server_group_id" placeholder="e.g., 9">
                <small class="form-text text-muted">Server group icon to display (optional)</small>
            </div>
            
            <button type="submit" class="btn btn-primary">Add Configuration</button>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3>Current Configurations</h3>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="test_update">
            <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
            <button type="submit" class="btn btn-sm btn-info float-right">Test Update All</button>
        </form>
    </div>
    <div class="card-body">
        <?php if (empty($configurations)): ?>
            <p class="text-muted">No configurations found. Add one above to get started!</p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client DB ID</th>
                        <th>Channel ID</th>
                        <th>Server Group ID</th>
                        <th>Enabled</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($configurations as $config): ?>
                        <tr>
                            <td><?= (int) $config['id'] ?></td>
                            <td><?= (int) $config['cldbid'] ?></td>
                            <td><?= (int) $config['channel_id'] ?></td>
                            <td><?= isset($config['server_group_id']) ? (int) $config['server_group_id'] : 'N/A' ?></td>
                            <td><?= (bool) $config['enabled'] ? 'Yes' : 'No' ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this configuration?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $config['id'] ?>">
                                    <input type="hidden" name="csrf-token" value="<?= htmlspecialchars(CsrfUtils::getToken()) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Next Steps</h3>
    </div>
    <div class="card-body">
        <ol>
            <li>Add a configuration above (cldbid + channel ID)</li>
            <li>Click "Test Update All" to test the update</li>
            <li>Start the bot: <code>php src/private/php/status-display-bot.php --daemon --interval=30</code></li>
            <li>Check the channel in TeamSpeak to see the status display</li>
        </ol>
        
        <h5>Troubleshooting</h5>
        <ul>
            <li>Check error logs: <code>tail -f /var/log/apache2/error.log</code></li>
            <li>Test bot manually: <code>php src/private/php/status-display-bot.php --once</code></li>
            <li>View bot logs: <code>tail -f /var/log/ts-status-bot.log</code></li>
        </ul>
    </div>
</div>

<div class="mt-3">
    <a href="check-status-display.php" class="btn btn-secondary">Run Diagnostic Check</a>
    <a href="index.php" class="btn btn-secondary">Back to Admin Panel</a>
</div>

</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
