<?php
$addHost = trim($_GET['add_host'] ?? '');
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editingDomain = null;

if ($editId > 0) {
    $stmt = $app->db->prepare("SELECT * FROM parked_domains WHERE id = ?");
    $stmt->execute([$editId]);
    $editingDomain = $stmt->fetch(PDO::FETCH_ASSOC);
}

$filterDomain = $_GET['filter_domain'] ?? '';
$filterCategory = $_GET['filter_category'] ?? '';
$filterTag = $_GET['filter_tag'] ?? '';

$where = [];
$params = [];
if ($filterDomain !== '') {
    $where[] = 'host LIKE ?';
    $params[] = '%' . $filterDomain . '%';
}
if ($filterCategory !== '') {
    $where[] = 'category = ?';
    $params[] = $filterCategory;
}
if ($filterTag !== '') {
    $where[] = 'subject_tags LIKE ?';
    $params[] = '%' . $filterTag . '%';
}

$sql = "SELECT * FROM parked_domains";
if ($where !== []) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY host ASC";

$stmt = $app->db->prepare($sql);
$stmt->execute($params);
$parkedDomains = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $app->db->query("SELECT DISTINCT category FROM parked_domains WHERE category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$allTags = $app->db->query("SELECT DISTINCT subject_tags FROM parked_domains WHERE subject_tags != '' ORDER BY subject_tags ASC")->fetchAll(PDO::FETCH_COLUMN);
$uniqueTags = [];
foreach ($allTags as $tagStr) {
    foreach (preg_split('/[,|\n]+/', $tagStr) as $t) {
        $t = trim($t);
        if ($t !== '') $uniqueTags[$t] = true;
    }
}
$uniqueTags = array_keys($uniqueTags);
sort($uniqueTags);
?>

<div class="admin-container">
    <div class="admin-box" style="flex: 1 1 100%;">

        <h2><?= $editingDomain ? 'Edit Domain' : 'Add Domain' ?></h2>

        <form method="POST" class="admin-form-row">
            <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
            <?php if ($editingDomain): ?>
                <input type="hidden" name="edit_parked_domain_id" value="<?= (int) $editingDomain['id'] ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Host</label>
                <input type="text" name="parked_domain_host" placeholder="example.com" value="<?= htmlspecialchars($editingDomain['host'] ?? $addHost) ?>" <?= $editingDomain ? 'readonly' : '' ?> required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <input type="text" name="parked_domain_category" placeholder="Real Estate" value="<?= htmlspecialchars($editingDomain['category'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Subject Tags</label>
                <input type="text" name="parked_domain_subject_tags" placeholder="buying, selling, investment" value="<?= htmlspecialchars($editingDomain['subject_tags'] ?? '') ?>">
            </div>
            <div class="admin-form-actions" style="align-self:flex-end;">
                <button type="submit"><?= $editingDomain ? 'Save Changes' : 'Add Domain' ?></button>
                <?php if ($editingDomain): ?>
                    <a href="<?= $app->adminUrl('domains') ?>" style="padding:8px 12px;">Cancel</a>
                <?php endif; ?>
            </div>
        </form>

        <div style="display:flex; gap:8px; margin-bottom:16px;">
            <form method="POST" enctype="multipart/form-data" style="display:flex; gap:8px; align-items:center;">
                <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
                <input type="file" name="parked_domain_csv" accept=".csv,text/csv" style="width:auto; padding:6px;">
                <button type="submit" style="width:auto; padding:8px 14px;">Import CSV</button>
            </form>
            <form method="POST" style="display:flex; align-items:center;">
                <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
                <input type="hidden" name="export_parked_domains" value="1">
                <button type="submit" style="width:auto; padding:8px 14px;">Export CSV</button>
            </form>
        </div>

    </div>

    <div class="admin-box" style="flex: 1 1 100%;">

        <h2>Domains (<?= count($parkedDomains) ?>)</h2>

        <form method="GET" class="admin-filter">
            <input type="hidden" name="admin" value="domains">
            <div class="filter-group">
                <label>Search Domain</label>
                <input type="text" name="filter_domain" placeholder="Search..." value="<?= htmlspecialchars($filterDomain) ?>">
            </div>
            <div class="filter-group">
                <label>Category</label>
                <select name="filter_category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($filterCategory === $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Tag</label>
                <select name="filter_tag">
                    <option value="">All Tags</option>
                    <?php foreach ($uniqueTags as $tag): ?>
                        <option value="<?= htmlspecialchars($tag) ?>" <?= ($filterTag === $tag) ? 'selected' : '' ?>><?= htmlspecialchars($tag) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit">Filter</button>
                <?php if ($filterDomain !== '' || $filterCategory !== '' || $filterTag !== ''): ?>
                    <a href="<?= $app->adminUrl('domains') ?>">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!empty($parkedDomains)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th data-sort-col="0">Host</th>
                        <th data-sort-col="1">Category</th>
                        <th data-sort-col="2">Tags</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parkedDomains as $rule): ?>
                        <tr>
                            <td><a href="http://<?= htmlspecialchars($rule['host']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($rule['host']) ?></a></td>
                            <td><?= htmlspecialchars($rule['category']) ?></td>
                            <td><?= htmlspecialchars($rule['subject_tags']) ?></td>
                            <td>
                                <a href="<?= $app->adminUrl('domains') ?>&edit=<?= $rule['id'] ?>">Edit</a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
                                    <input type="hidden" name="delete_parked_domain_id" value="<?= (int) $rule['id'] ?>">
                                    <button type="submit" onclick="return confirm('Are you sure you want to delete this domain?')" style="width:auto; padding:0; background:none; border:none; color:#ffcc00; cursor:pointer;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="muted">No domains found.</p>
        <?php endif; ?>

    </div>
</div>
