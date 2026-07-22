<?php
$page = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$view = ($_GET['view'] ?? 'active') === 'archived' ? 'archived' : 'active';
$archived = $view === 'archived' ? 1 : 0;

$countStmt = $app->db->prepare("SELECT COUNT(*) FROM leads WHERE archived = ?");
$countStmt->execute([$archived]);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$stmt = $app->db->prepare("SELECT * FROM leads WHERE archived = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$archived, $perPage, $offset]);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

function leadsSortParams(string $view, int $page): string {
    return http_build_query(['admin' => 'leads', 'view' => $view, 'p' => $page]);
}
?>

<div class="admin-container">

    <div class="admin-box admin-box-full">
        <h2>Leads</h2>

        <div style="margin-bottom:12px; display:flex; gap:8px; align-items:center;">
            <a href="?admin=leads&view=active" style="padding:6px 14px; border-radius:4px; text-decoration:none; <?= $view === 'active' ? 'background:#222; color:#fff;' : 'background:#eee; color:#333;' ?>">Active (<?= $app->db->query("SELECT COUNT(*) FROM leads WHERE archived = 0")->fetchColumn() ?>)</a>
            <a href="?admin=leads&view=archived" style="padding:6px 14px; border-radius:4px; text-decoration:none; <?= $view === 'archived' ? 'background:#222; color:#fff;' : 'background:#eee; color:#333;' ?>">Archived (<?= $app->db->query("SELECT COUNT(*) FROM leads WHERE archived = 1")->fetchColumn() ?>)</a>

            <span style="flex:1;"></span>

            <form method="POST" onsubmit="return confirm('Export <?= $view ?> leads as CSV?')">
                <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
                <input type="hidden" name="export" value="1">
                <?php if ($archived): ?><input type="hidden" name="export_archived" value="1"><?php endif; ?>
                <button type="submit">Export CSV</button>
            </form>
        </div>

        <?php if ($leads): ?>
        <table class="admin-table">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Message</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($leads as $lead): ?>
            <tr>
                <td><?= htmlspecialchars($lead['name']) ?></td>
                <td><?= htmlspecialchars($lead['email']) ?></td>
                <td><?= htmlspecialchars($lead['message']) ?></td>
                <td><?= date('Y-m-d H:i', (int) $lead['created_at']) ?></td>
                <td style="white-space:nowrap;">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
                        <input type="hidden" name="toggle_archive_id" value="<?= $lead['id'] ?>">
                        <button type="submit" style="background:none; border:none; cursor:pointer; color:<?= $archived ? '#2ecc71' : '#f39c12' ?>; text-decoration:underline; font-size:13px; padding:0;"><?= $archived ? 'Restore' : 'Archive' ?></button>
                    </form>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this lead permanently?')">
                        <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
                        <input type="hidden" name="delete_id" value="<?= $lead['id'] ?>">
                        <button type="submit" style="background:none; border:none; cursor:pointer; color:#ff6b6b; text-decoration:underline; font-size:13px; padding:0;">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php if ($totalPages > 1): ?>
        <div style="margin-top:12px; text-align:center;">
            <?php
            if ($page > 1) {
                echo '<a href="?admin=leads&view=' . $view . '&p=' . ($page - 1) . '">&laquo; Prev</a> ';
            }
            $range = 2;
            for ($i = max(1, $page - $range); $i <= min($totalPages, $page + $range); $i++) {
                if ($i === $page) {
                    echo "<strong>[{$i}]</strong> ";
                } else {
                    echo '<a href="?admin=leads&view=' . $view . '&p=' . $i . '">' . $i . '</a> ';
                }
            }
            if ($page < $totalPages) {
                echo '<a href="?admin=leads&view=' . $view . '&p=' . ($page + 1) . '">Next &raquo;</a>';
            }
            ?>
        </div>
        <p style="text-align:center; margin-top:8px; color:#888;">
            Page <?= $page ?> of <?= $totalPages ?> &middot; <?= number_format($totalRows) ?> total leads
        </p>
        <?php endif; ?>

        <?php else: ?>
        <p class="muted">No <?= $view ?> leads.</p>
        <?php endif; ?>
    </div>

</div>
