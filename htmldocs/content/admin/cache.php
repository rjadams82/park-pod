<?php
$page = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$sort = $_GET['sort'] ?? 'fetched_at';
$dir = $_GET['dir'] ?? 'DESC';

$allowedSorts = ['fetched_at', 'topic', 'provider_id'];
$allowedDirs = ['ASC', 'DESC'];
if (!in_array($sort, $allowedSorts, true)) $sort = 'fetched_at';
if (!in_array($dir, $allowedDirs, true)) $dir = 'DESC';

$countStmt = $app->db->query("SELECT COUNT(*) FROM content_cache");
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$cacheStmt = $app->db->prepare("
    SELECT c.*, p.name AS provider_name
    FROM content_cache c
    LEFT JOIN providers p ON p.id = c.provider_id
    ORDER BY {$sort} {$dir}
    LIMIT ? OFFSET ?
");
$cacheStmt->execute([$perPage, $offset]);
$entries = $cacheStmt->fetchAll(PDO::FETCH_ASSOC);

function cacheSortLink(string $label, string $col, string $currentSort, string $currentDir): string {
    $newDir = ($col === $currentSort && $currentDir === 'DESC') ? 'ASC' : 'DESC';
    $arrow = '';
    if ($col === $currentSort) {
        $arrow = $currentDir === 'ASC' ? ' &uarr;' : ' &darr;';
    }
    $params = ['admin' => 'cache', 'sort' => $col, 'dir' => $newDir, 'p' => 1];
    $qs = http_build_query($params);
    return "<a href=\"?{$qs}\">{$label}{$arrow}</a>";
}
?>

<div class="admin-container">

    <div class="admin-box admin-box-full">
        <h2>Content Cache <a href="<?= $app->adminUrl('providers') ?>" style="font-size:0.6em; font-weight:normal;">&laquo; Back to Providers</a></h2>

        <?php if ($entries): ?>
        <table class="admin-table">
            <tr>
                <th><?= cacheSortLink('Fetched', 'fetched_at', $sort, $dir) ?></th>
                <th>Provider</th>
                <th><?= cacheSortLink('Topic', 'topic', $sort, $dir) ?></th>
                <th>Items</th>
                <th>Data</th>
            </tr>
            <?php foreach ($entries as $entry): ?>
            <tr>
                <td><?= date('Y-m-d H:i:s', (int) $entry['fetched_at']) ?></td>
                <td><?= htmlspecialchars($entry['provider_name'] ?? 'Unknown') ?></td>
                <td><?= htmlspecialchars($entry['topic']) ?></td>
                <td><?= count(json_decode($entry['data'], true) ?? []) ?></td>
                <td>
                    <button class="cache-view-btn" data-cache-id="<?= (int) $entry['id'] ?>" data-cache-data="<?= htmlspecialchars($entry['data'], ENT_QUOTES) ?>" style="padding:0; background:none; border:none; color:#ffcc00; cursor:pointer; text-decoration:underline;">View</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php if ($totalPages > 1): ?>
        <div style="margin-top:12px; text-align:center;">
            <?php
            if ($page > 1) {
                $p = ['admin' => 'cache', 'p' => $page - 1, 'sort' => $sort, 'dir' => $dir];
                echo '<a href="?' . http_build_query($p) . '">&laquo; Prev</a> ';
            }

            $range = 2;
            for ($i = max(1, $page - $range); $i <= min($totalPages, $page + $range); $i++) {
                if ($i === $page) {
                    echo "<strong>[{$i}]</strong> ";
                } else {
                    $p = ['admin' => 'cache', 'p' => $i, 'sort' => $sort, 'dir' => $dir];
                    echo '<a href="?' . http_build_query($p) . '">' . $i . '</a> ';
                }
            }

            if ($page < $totalPages) {
                $p = ['admin' => 'cache', 'p' => $page + 1, 'sort' => $sort, 'dir' => $dir];
                echo '<a href="?' . http_build_query($p) . '">Next &raquo;</a>';
            }
            ?>
        </div>
        <p style="text-align:center; margin-top:8px; color:#888;">
            Page <?= $page ?> of <?= $totalPages ?> &middot; <?= number_format($totalRows) ?> total entries
        </p>
        <?php endif; ?>

        <?php else: ?>
        <p class="muted">No cache entries found.</p>
        <?php endif; ?>
    </div>

</div>

<div id="cacheModal" class="cache-modal" style="display:none;">
    <div class="cache-modal-content">
        <div class="cache-modal-header">
            <span class="cache-modal-title">Cache Entry</span>
            <button class="cache-modal-close" onclick="document.getElementById('cacheModal').style.display='none'">&times;</button>
        </div>
        <pre class="cache-modal-body" id="cacheModalBody"></pre>
    </div>
</div>

<script>
document.querySelectorAll('.cache-view-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var raw = this.getAttribute('data-cache-data');
        try {
            var parsed = JSON.parse(raw);
            document.getElementById('cacheModalBody').textContent = JSON.stringify(parsed, null, 2);
        } catch (e) {
            document.getElementById('cacheModalBody').textContent = raw;
        }
        document.getElementById('cacheModal').style.display = 'flex';
    });
});

document.getElementById('cacheModal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});
</script>
