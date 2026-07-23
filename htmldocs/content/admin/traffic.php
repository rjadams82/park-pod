<?php
$page = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$filterDomain = $_GET['domain'] ?? '';
$filterHost = $_GET['host'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$dir = $_GET['dir'] ?? 'DESC';

$allowedSorts = ['created_at', 'host', 'domain', 'path', 'referrer', 'user_ip'];
$allowedDirs = ['ASC', 'DESC'];
if (!in_array($sort, $allowedSorts, true)) $sort = 'created_at';
if (!in_array($dir, $allowedDirs, true)) $dir = 'DESC';

$where = [];
$params = [];

if ($filterDomain !== '') {
    $where[] = 'domain = ?';
    $params[] = $filterDomain;
}
if ($filterHost !== '') {
    $where[] = 'host LIKE ?';
    $params[] = '%' . $filterHost . '%';
}
if ($filterDateFrom !== '') {
    $where[] = 'created_at >= ?';
    $params[] = (int) strtotime($filterDateFrom);
}
if ($filterDateTo !== '') {
    $where[] = 'created_at <= ?';
    $params[] = (int) strtotime($filterDateTo . ' 23:59:59');
}

$whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $app->db->prepare("SELECT COUNT(*) FROM access_logs {$whereClause}");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$queryParams = $params;
$queryParams[] = $perPage;
$queryParams[] = $offset;

$logStmt = $app->db->prepare("
    SELECT * FROM access_logs
    {$whereClause}
    ORDER BY {$sort} {$dir}
    LIMIT ? OFFSET ?
");
$logStmt->execute($queryParams);
$entries = $logStmt->fetchAll(PDO::FETCH_ASSOC);

$allDomains = $app->db->query("SELECT DISTINCT domain FROM access_logs WHERE domain IS NOT NULL AND domain != '' ORDER BY domain ASC")->fetchAll(PDO::FETCH_COLUMN);

function trafficSortLink(string $label, string $col, string $currentSort, string $currentDir, array $filters): string {
    $newDir = ($col === $currentSort && $currentDir === 'DESC') ? 'ASC' : 'DESC';
    $arrow = '';
    if ($col === $currentSort) {
        $arrow = $currentDir === 'ASC' ? ' &uarr;' : ' &darr;';
    }
    $params = array_merge(['admin' => 'traffic'], $filters, ['sort' => $col, 'dir' => $newDir, 'p' => 1]);
    $qs = http_build_query($params);
    return "<a href=\"?{$qs}\">{$label}{$arrow}</a>";
}

$filters = [];
if ($filterDomain !== '') $filters['domain'] = $filterDomain;
if ($filterHost !== '') $filters['host'] = $filterHost;
if ($filterDateFrom !== '') $filters['date_from'] = $filterDateFrom;
if ($filterDateTo !== '') $filters['date_to'] = $filterDateTo;
?>

<div class="admin-container">

    <div class="admin-box admin-box-full">
        <h2>Traffic Log</h2>

        <form class="panel filter-bar" method="GET" style="margin-bottom:12px;">
            <input type="hidden" name="admin" value="traffic">
            <select name="domain">
                <option value="">All Domains</option>
                <?php foreach ($allDomains as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= $filterDomain === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="host" value="<?= htmlspecialchars($filterHost) ?>" placeholder="Filter by host...">
            <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>" placeholder="From date">
            <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>" placeholder="To date">
            <button type="submit">Filter</button>
            <?php if ($filterDomain !== '' || $filterHost !== '' || $filterDateFrom !== '' || $filterDateTo !== ''): ?>
                <a href="<?= $app->adminUrl('traffic') ?>" style="margin-left:8px;">Clear</a>
            <?php endif; ?>
        </form>

        <div style="margin-bottom:12px; display:flex; gap:8px;">
            <form method="POST" onsubmit="return confirm('Export all matching traffic entries as CSV?')">
                <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
                <input type="hidden" name="export_domain" value="<?= htmlspecialchars($filterDomain) ?>">
                <input type="hidden" name="export_host" value="<?= htmlspecialchars($filterHost) ?>">
                <input type="hidden" name="export_date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
                <input type="hidden" name="export_date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
                <button type="submit" name="export_traffic_log" value="1">Export CSV</button>
            </form>
            <form method="POST" onsubmit="return confirm('Clear all matching traffic entries? This cannot be undone.')">
                <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
                <input type="hidden" name="clear_domain" value="<?= htmlspecialchars($filterDomain) ?>">
                <input type="hidden" name="clear_host" value="<?= htmlspecialchars($filterHost) ?>">
                <input type="hidden" name="clear_date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
                <input type="hidden" name="clear_date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
                <button type="submit" name="clear_traffic_log" value="1" style="color:#ff6b6b;">Clear Log</button>
            </form>
        </div>

        <?php if ($entries): ?>
        <table class="admin-table">
            <tr>
                <th><?= trafficSortLink('Date', 'created_at', $sort, $dir, $filters) ?></th>
                <th><?= trafficSortLink('Domain', 'domain', $sort, $dir, $filters) ?></th>
                <th><?= trafficSortLink('Host', 'host', $sort, $dir, $filters) ?></th>
                <th><?= trafficSortLink('Path', 'path', $sort, $dir, $filters) ?></th>
                <th><?= trafficSortLink('Referrer', 'referrer', $sort, $dir, $filters) ?></th>
                <th><?= trafficSortLink('IP', 'user_ip', $sort, $dir, $filters) ?></th>
                <th>Headers</th>
            </tr>
            <?php foreach ($entries as $entry): ?>
            <tr>
                <td><?= date('Y-m-d H:i:s', (int) $entry['created_at']) ?></td>
                <td>
                    <?php $domain = htmlspecialchars($entry['domain']); ?>
                    <a href="http://<?= $domain ?>" target="_blank" rel="noopener"><?= $domain ?></a>
                </td>
                <td>
                    <?php $host = htmlspecialchars($entry['host']); ?>
                    <a href="http://<?= $host ?>" target="_blank" rel="noopener"><?= $host ?></a>
                </td>
                <td><?= htmlspecialchars($entry['path']) ?></td>
                <td>
                    <?php $referrer = $entry['referrer'] ?? ''; ?>
                    <?php if ($referrer !== ''): ?>
                        <a href="<?= htmlspecialchars($referrer) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($referrer) ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td>
                    <?php $ip = htmlspecialchars($entry['user_ip'] ?? ''); ?>
                    <?php if ($ip !== ''): ?>
                        <a href="https://ipinfo.io/<?= $ip ?>" target="_blank" rel="noopener"><?= $ip ?></a>
                        <a href="https://reverseip.domaintools.com/search/?q=<?= $ip ?>" target="_blank" rel="noopener" style="font-size:0.85em;">lookup</a>
                        <?php $ipInfo = getIPinfo($entry['user_ip'] ?? ''); ?>
                        <?php if ($ipInfo && $ipInfo['country'] !== ''): ?>
                        <div style="font-size:11px; color:#aaa; margin-top:2px;">
                            <img class="ipflag" src="<?= $ipInfo['flag'] ?>"> <?= htmlspecialchars($ipInfo['country']) ?><?= $ipInfo['company'] !== '' ? ' | ' . htmlspecialchars($ipInfo['company']) : '' ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                    <div id="meta-modal" class="modal-overlay" onclick="closeMetaModal(event)">
                        <div class="modal-box">
                            <div class="modal-header">
                                <span class="modal-title">Request Headers</span>
                                <button class="modal-close" onclick="closeMetaModal()">&times;</button>
                            </div>
                            <div class="modal-body" id="meta-modal-body"></div>
                        </div>
                    </div>                    
                </td>
                <td>
                    <?php
                    $meta = $entry['meta'] ?? '';
                    $metaData = $meta !== '' ? json_decode($meta, true) : null;
                    if ($metaData):
                    ?>
                        <a href="#" class="meta-link" onclick="openMetaModal(this); return false;" data-meta='<?= htmlspecialchars(json_encode($metaData), ENT_QUOTES) ?>'>View</a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php if ($totalPages > 1): ?>
        <div style="margin-top:12px; text-align:center;">
            <?php
            $baseParams = ['admin' => 'traffic'];
            $baseParams = array_merge($baseParams, $filters);
            $baseParams['sort'] = $sort;
            $baseParams['dir'] = $dir;

            if ($page > 1) {
                $p = array_merge($baseParams, ['p' => $page - 1]);
                echo '<a href="?' . http_build_query($p) . '">&laquo; Prev</a> ';
            }

            $range = 2;
            for ($i = max(1, $page - $range); $i <= min($totalPages, $page + $range); $i++) {
                if ($i === $page) {
                    echo "<strong>[{$i}]</strong> ";
                } else {
                    $p = array_merge($baseParams, ['p' => $i]);
                    echo '<a href="?' . http_build_query($p) . '">' . $i . '</a> ';
                }
            }

            if ($page < $totalPages) {
                $p = array_merge($baseParams, ['p' => $page + 1]);
                echo '<a href="?' . http_build_query($p) . '">Next &raquo;</a>';
            }
            ?>
        </div>
        <p style="text-align:center; margin-top:8px; color:#888;">
            Page <?= $page ?> of <?= $totalPages ?> &middot; <?= number_format($totalRows) ?> total entries
        </p>
        <?php endif; ?>

        <?php else: ?>
        <p class="muted">No access entries match your filters.</p>
        <?php endif; ?>
    </div>

</div>
<!-- meta modal -->
<script>
    function openMetaModal(el) {
        var data = JSON.parse(el.getAttribute('data-meta'));
        var html = '';
        var labels = {
            user_agent: 'User Agent',
            accept_language: 'Accept Language',
            accept_encoding: 'Accept Encoding',
            x_forwarded_for: 'X-Forwarded-For',
            query_string: 'Query String',
            referrer: 'Referrer'
        };
        for (var key in data) {
            if (data[key]) {
                html += '<div class="meta-row"><span class="meta-key">' + (labels[key] || key) + '</span><span class="meta-val">' + escapeHtml(String(data[key])) + '</span></div>';
            }
        }
        document.getElementById('meta-modal-body').innerHTML = html || '<p style="color:#888;">No header data recorded.</p>';
        document.getElementById('meta-modal').classList.add('active');
    }
    function closeMetaModal(e) {
        if (!e || e.target === document.getElementById('meta-modal')) {
            document.getElementById('meta-modal').classList.remove('active');
        }
    }
    function escapeHtml(t) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(t));
        return d.innerHTML;
    }
</script>