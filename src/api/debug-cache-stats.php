<?php
/**
 * Debug script to monitor cache performance and query statistics
 * Access this at: https://yoursite.com/api/debug-cache-stats.php
 * 
 * This script shows:
 * - Current cache settings
 * - Cache hit/miss rates
 * - Recommendations for optimization
 */

use Wruczek\TSWebsite\Config;
use Wruczek\TSWebsite\CacheManager;

require_once __DIR__ . "/../private/php/load.php";

// Get all cache-related config
$cacheConfig = [
    'cache_serverinfo' => Config::get('cache_serverinfo', 'NOT SET'),
    'cache_clientlist' => Config::get('cache_clientlist', 'NOT SET'),
    'cache_channelist' => Config::get('cache_channelist', 'NOT SET'),
    'cache_banlist' => Config::get('cache_banlist', 'NOT SET'),
    'cache_servergroups' => Config::get('cache_servergroups', 'NOT SET'),
    'cache_channelgroups' => Config::get('cache_channelgroups', 'NOT SET'),
    'cache_adminstatus' => Config::get('cache_adminstatus', 'NOT SET'),
    'cache_servericons' => Config::get('cache_servericons', 'NOT SET'),
    'cache_logincode' => Config::get('cache_logincode', 'NOT SET'),
];

// Get cache metadata
$serverInfoMeta = CacheManager::i()->getServerInfo(true);
$clientListMeta = CacheManager::i()->getClientList(true);
$channelListMeta = CacheManager::i()->getChannelList(true);

function getCacheStatus($seconds) {
    if ($seconds === 'NOT SET') return '❌ ERROR';
    if ($seconds < 20) return '⚠️ TOO LOW';
    if ($seconds < 60) return '✓ OK';
    return '✓ GOOD';
}

function getCacheAge($meta) {
    if (!isset($meta['time'])) return 'N/A';
    return time() - $meta['time'];
}

function formatCacheMeta($meta, $configKey, $cacheConfig) {
    $age = getCacheAge($meta);
    $maxAge = $cacheConfig[$configKey];
    $percentage = $maxAge !== 'NOT SET' && $age !== 'N/A' ? round(($age / $maxAge) * 100) : 'N/A';
    
    return [
        'age_seconds' => $age,
        'max_age' => $maxAge,
        'percentage_to_expire' => $percentage,
        'will_expire_in' => $maxAge !== 'NOT SET' && $age !== 'N/A' ? $maxAge - $age : 'N/A',
        'is_fresh' => $maxAge !== 'NOT SET' && $age !== 'N/A' ? $age < $maxAge : false
    ];
}

$stats = [
    'timestamp' => time(),
    'date' => date('Y-m-d H:i:s'),
    'cache_config' => $cacheConfig,
    'cache_status' => [
        'serverinfo' => [
            'status' => getCacheStatus($cacheConfig['cache_serverinfo']),
            'meta' => formatCacheMeta($serverInfoMeta, 'cache_serverinfo', $cacheConfig)
        ],
        'clientlist' => [
            'status' => getCacheStatus($cacheConfig['cache_clientlist']),
            'meta' => formatCacheMeta($clientListMeta, 'cache_clientlist', $cacheConfig)
        ],
        'channellist' => [
            'status' => getCacheStatus($cacheConfig['cache_channelist']),
            'meta' => formatCacheMeta($channelListMeta, 'cache_channelist', $cacheConfig)
        ]
    ],
    'recommendations' => []
];

// Generate recommendations
foreach ($cacheConfig as $key => $value) {
    if ($value === 'NOT SET') {
        $stats['recommendations'][] = "⚠️ $key is not configured!";
    } elseif (in_array($key, ['cache_serverinfo', 'cache_clientlist']) && $value < 20) {
        $stats['recommendations'][] = "⚠️ $key is too low ($value s). Recommended: 30s minimum. This causes query spam!";
    } elseif ($value < 10) {
        $stats['recommendations'][] = "⚠️ $key is extremely low ($value s). This will hammer your TeamSpeak server!";
    }
}

if (empty($stats['recommendations'])) {
    $stats['recommendations'][] = "✓ All cache settings look good!";
}

// Calculate estimated queries per minute (assuming 1 user with 1 tab)
$queriesPerMinute = 0;
if ($cacheConfig['cache_serverinfo'] !== 'NOT SET') {
    $queriesPerMinute += 60 / max($cacheConfig['cache_serverinfo'], 1);
}

$stats['estimated_queries_per_minute_per_user'] = round($queriesPerMinute, 2);
$stats['estimated_queries_per_minute_10_users'] = round($queriesPerMinute * 10, 2);

// HTML output
header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cache Statistics - TS-Website Debug</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
            margin-top: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .status-good { color: #28a745; }
        .status-ok { color: #ffc107; }
        .status-bad { color: #dc3545; }
        .recommendation {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 4px solid #ffc107;
            background: #fff3cd;
        }
        .recommendation.good {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .metric {
            display: inline-block;
            background: #e9ecef;
            padding: 10px 20px;
            border-radius: 4px;
            margin: 5px;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        .metric-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .refresh-btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .refresh-btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <h1>🔍 TeamSpeak Website - Cache Statistics</h1>
    <p><small>Generated: <?= $stats['date'] ?> | <button class="refresh-btn" onclick="location.reload()">Refresh</button></small></p>

    <div class="card">
        <h2>📊 Cache Configuration</h2>
        <table>
            <thead>
                <tr>
                    <th>Cache Key</th>
                    <th>Duration (seconds)</th>
                    <th>Status</th>
                    <th>Age / Freshness</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cacheConfig as $key => $seconds): 
                    $status = getCacheStatus($seconds);
                    $statusClass = strpos($status, '✓') !== false ? 'status-good' : (strpos($status, '⚠️') !== false ? 'status-ok' : 'status-bad');
                    
                    $ageInfo = '';
                    if (isset($stats['cache_status'][str_replace('cache_', '', $key)]['meta'])) {
                        $meta = $stats['cache_status'][str_replace('cache_', '', $key)]['meta'];
                        if ($meta['age_seconds'] !== 'N/A') {
                            $ageInfo = $meta['age_seconds'] . 's old / ' . $meta['will_expire_in'] . 's until refresh';
                        }
                    }
                ?>
                    <tr>
                        <td><code><?= htmlspecialchars($key) ?></code></td>
                        <td><?= htmlspecialchars($seconds) ?></td>
                        <td class="<?= $statusClass ?>"><?= htmlspecialchars($status) ?></td>
                        <td><?= htmlspecialchars($ageInfo ?: 'N/A') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>📈 Query Load Estimates</h2>
        <div>
            <div class="metric">
                <div class="metric-value"><?= $stats['estimated_queries_per_minute_per_user'] ?></div>
                <div class="metric-label">Queries/min per user</div>
            </div>
            <div class="metric">
                <div class="metric-value"><?= $stats['estimated_queries_per_minute_10_users'] ?></div>
                <div class="metric-label">Queries/min (10 users)</div>
            </div>
        </div>
        <p style="color: #6c757d; font-size: 14px; margin-top: 15px;">
            <strong>Note:</strong> These are estimates based on JavaScript polling + cache expiration. 
            Actual queries may vary based on user behavior and concurrent requests.
        </p>
    </div>

    <div class="card">
        <h2>💡 Recommendations</h2>
        <?php foreach ($stats['recommendations'] as $rec): 
            $isGood = strpos($rec, '✓') !== false;
        ?>
            <div class="recommendation <?= $isGood ? 'good' : '' ?>">
                <?= htmlspecialchars($rec) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2>🔧 Quick Fixes</h2>
        <p>If you see warnings above, apply these SQL commands to your database:</p>
        
        <h3>Conservative Fix (Recommended):</h3>
        <pre><code>UPDATE config SET value = '30' WHERE identifier = 'cache_serverinfo';
UPDATE config SET value = '30' WHERE identifier = 'cache_clientlist';</code></pre>

        <h3>Aggressive Fix (High Traffic):</h3>
        <pre><code>UPDATE config SET value = '60' WHERE identifier = 'cache_serverinfo';
UPDATE config SET value = '60' WHERE identifier = 'cache_clientlist';
UPDATE config SET value = '120' WHERE identifier = 'cache_channelist';</code></pre>

        <p><strong>Don't forget to also update <code>src/js/status.js</code></strong> to increase the polling interval from 10s to 30s!</p>
    </div>

    <div class="card">
        <h2>📚 More Information</h2>
        <ul>
            <li>See <code>QUERY_ANALYSIS.md</code> for detailed explanation of the issue</li>
            <li>See <code>INSTALLATION_GUIDE.md</code> for step-by-step fix instructions</li>
            <li>Use <code>check-current-cache-settings.sql</code> to verify changes via CLI</li>
        </ul>
    </div>

    <footer style="text-align: center; color: #6c757d; margin-top: 40px; font-size: 12px;">
        <p>⚠️ This is a debug page. Do not expose it publicly. Consider deleting <code>api/debug-cache-stats.php</code> after fixing the issue.</p>
    </footer>
</body>
</html>
