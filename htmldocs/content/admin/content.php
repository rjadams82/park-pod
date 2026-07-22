<?php
$page = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$filterProvider = $_GET['log_provider'] ?? '';
$filterHost = $_GET['log_host'] ?? '';
$filterStatus = $_GET['log_status'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$dir = $_GET['dir'] ?? 'DESC';

$allowedSorts = ['created_at', 'provider_name', 'host', 'status', 'item_count'];
$allowedDirs = ['ASC', 'DESC'];
if (!in_array($sort, $allowedSorts, true)) $sort = 'created_at';
if (!in_array($dir, $allowedDirs, true)) $dir = 'DESC';

$where = [];
$params = [];

if ($filterProvider !== '') {
    $where[] = 'provider_id = ?';
    $params[] = (int) $filterProvider;
}
if ($filterHost !== '') {
    $where[] = 'host = ?';
    $params[] = $filterHost;
}
if ($filterStatus !== '') {
    $where[] = 'status = ?';
    $params[] = $filterStatus;
}

$whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $app->db->prepare("SELECT COUNT(*) FROM provider_fetch_logs {$whereClause}");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$queryParams = $params;
$queryParams[] = $perPage;
$queryParams[] = $offset;

$logStmt = $app->db->prepare("
    SELECT * FROM provider_fetch_logs
    {$whereClause}
    ORDER BY {$sort} {$dir}
    LIMIT ? OFFSET ?
");
$logStmt->execute($queryParams);
$entries = $logStmt->fetchAll(PDO::FETCH_ASSOC);

$allProviders = $app->content->getAllProviders();
$allHosts = $app->content->getAllFetchLogHosts();
$allStatuses = $app->db->query("SELECT DISTINCT status FROM provider_fetch_logs WHERE status IS NOT NULL AND status != '' ORDER BY status ASC")->fetchAll(PDO::FETCH_COLUMN);

function contentSortLink(string $label, string $col, string $currentSort, string $currentDir, array $filters): string {
    $newDir = ($col === $currentSort && $currentDir === 'DESC') ? 'ASC' : 'DESC';
    $arrow = '';
    if ($col === $currentSort) {
        $arrow = $currentDir === 'ASC' ? ' &uarr;' : ' &darr;';
    }
    $params = array_merge(['admin' => 'content'], $filters, ['sort' => $col, 'dir' => $newDir, 'p' => 1]);
    $qs = http_build_query($params);
    return "<a href=\"?{$qs}\">{$label}{$arrow}</a>";
}

$filters = [];
if ($filterProvider !== '') $filters['log_provider'] = $filterProvider;
if ($filterHost !== '') $filters['log_host'] = $filterHost;
if ($filterStatus !== '') $filters['log_status'] = $filterStatus;
?>

<div class="admin-container">

    <div class="admin-box admin-box-full">
        <h2>Content Fetch Log <a href="<?= $app->adminUrl('providers') ?>" style="font-size:0.6em; font-weight:normal;">&laquo; Back to Providers</a></h2>

        <form class="panel filter-bar" method="GET" style="margin-bottom:12px;">
            <input type="hidden" name="admin" value="content">
            <select name="log_provider">
                <option value="">All Providers</option>
                <?php foreach ($allProviders as $fp): ?>
                <option value="<?= (int) $fp['id'] ?>" <?= $filterProvider === (string) $fp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($fp['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="log_host">
                <option value="">All Hosts</option>
                <?php foreach ($allHosts as $h): ?>
                <option value="<?= htmlspecialchars($h) ?>" <?= $filterHost === $h ? 'selected' : '' ?>><?= htmlspecialchars($h) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="log_status">
                <option value="">All Statuses</option>
                <?php foreach ($allStatuses as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Filter</button>
            <?php if ($filterProvider !== '' || $filterHost !== '' || $filterStatus !== ''): ?>
                <a href="<?= $app->adminUrl('content') ?>" style="margin-left:8px;">Clear</a>
            <?php endif; ?>
        </form>

        <div style="margin-bottom:12px; display:flex; gap:8px;">
            <form method="POST" onsubmit="return confirm('Export all matching fetch log entries as CSV?')">
                <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
                <input type="hidden" name="export_provider" value="<?= htmlspecialchars($filterProvider) ?>">
                <input type="hidden" name="export_host" value="<?= htmlspecialchars($filterHost) ?>">
                <input type="hidden" name="export_status" value="<?= htmlspecialchars($filterStatus) ?>">
                <button type="submit" name="export_fetch_log" value="1">Export CSV</button>
            </form>
            <form method="POST" onsubmit="return confirm('Clear all matching fetch log entries? This cannot be undone.')">
                <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
                <input type="hidden" name="clear_provider" value="<?= htmlspecialchars($filterProvider) ?>">
                <input type="hidden" name="clear_host" value="<?= htmlspecialchars($filterHost) ?>">
                <input type="hidden" name="clear_status" value="<?= htmlspecialchars($filterStatus) ?>">
                <button type="submit" name="clear_fetch_log" value="1" style="color:#ff6b6b;">Clear Log</button>
            </form>
        </div>

        <?php if ($entries): ?>
        <table class="admin-table">
            <tr>
                <th><?= contentSortLink('Date', 'created_at', $sort, $dir, $filters) ?></th>
                <th><?= contentSortLink('Provider', 'provider_name', $sort, $dir, $filters) ?></th>
                <th><?= contentSortLink('Status', 'status', $sort, $dir, $filters) ?></th>
                <th><?= contentSortLink('Host', 'host', $sort, $dir, $filters) ?></th>
                <th><?= contentSortLink('Items', 'item_count', $sort, $dir, $filters) ?></th>
                <th>Topic</th>
                <th>Message</th>
            </tr>
            <?php foreach ($entries as $entry): ?>
            <tr>
                <td><?= date('Y-m-d H:i:s', (int) $entry['created_at']) ?></td>
                <td><?= htmlspecialchars($entry['provider_name']) ?></td>
                <td><?= htmlspecialchars($entry['status']) ?></td>
                <td><?= htmlspecialchars($entry['host'] ?? '') ?></td>
                <td><?= (int) $entry['item_count'] ?></td>
                <td><?= htmlspecialchars($entry['topic']) ?></td>
                <td><?= htmlspecialchars($entry['message']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php if ($totalPages > 1): ?>
        <div style="margin-top:12px; text-align:center;">
            <?php
            $baseParams = ['admin' => 'content'];
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
        <p class="muted">No fetch entries match your filters.</p>
        <?php endif; ?>
    </div>

</div>
