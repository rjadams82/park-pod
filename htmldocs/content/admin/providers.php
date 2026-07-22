<?php
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editingProvider = $editId > 0 ? $app->content->getProviderById($editId) : null;
?>

<div class="admin-container">

    <div class="admin-box admin-box-full">
        <h2><?= $editingProvider ? 'Edit Provider' : 'Add Provider' ?></h2>

        <form class="panel" method="POST">
            <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
            <?php if ($editingProvider): ?>
                <input type="hidden" name="edit_id" value="<?= (int) $editingProvider['id'] ?>">
            <?php endif; ?>
            <div class="group"> 
                <input type="text" name="name" placeholder="Provider Name" value="<?= htmlspecialchars($editingProvider['name'] ?? '') ?>" required>
                <select name="type" required>
                    <option value="">Select Type</option>
                    <?php
                    $types = ['rss' => 'RSS Feed', 'wikipedia' => 'Wikipedia', 'reddit' => 'Reddit', 'duckduckgo' => 'DuckDuckGo', 'openlibrary' => 'Open Library', 'tinyfish' => 'TinyFish'];
                    $current = strtolower($editingProvider['type'] ?? '');
                    foreach ($types as $val => $label):
                    ?>
                    <option value="<?= $val ?>" <?= $current === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="group">
                <input type="text" name="endpoint" placeholder="Feed/API URL" value="<?= htmlspecialchars($editingProvider['endpoint'] ?? '') ?>" required>
                <input type="text" name="api_key" placeholder="API Key (optional)" value="<?= htmlspecialchars($editingProvider['api_key'] ?? '') ?>">
            </div>
            <div class="group">
                <input type="number" name="ttl" placeholder="Cache TTL (seconds)" value="<?= htmlspecialchars((string) ($editingProvider['ttl'] ?? '3600')) ?>">
                <select name="enabled">
                    <option value="1" <?= (!empty($editingProvider['enabled']) ? 'selected' : '') ?>>Enabled</option>
                    <option value="0" <?= (empty($editingProvider['enabled']) ? 'selected' : '') ?>>Disabled</option>
                </select>
                <button type="submit"><?= $editingProvider ? 'Save Changes' : 'Add Provider' ?></button>
                <?php if ($editingProvider): ?>
                    <a href="<?= $app->adminUrl('providers') ?>" style="text-align:center; color:#ffcc00; margin-top:4px; display:block;">Cancel Edit</a>
                <?php endif; ?>
            </div>
            
            

        </form>
    </div>

    <div class="admin-box">
        <h2>Active Providers <a href="<?= $app->adminUrl('cache') ?>" style="font-size:0.6em; font-weight:normal;">View Cache &raquo;</a></h2>

        <table class="admin-table">
            <tr>
                <th data-sort-col="0">Name</th>
                <th data-sort-col="1">Type</th>
                <th data-sort-col="2">Endpoint</th>
                <th data-sort-col="3" data-sort-type="number">TTL</th>
                <th data-sort-col="4">Enabled</th>
                <th>Actions</th>
            </tr>

            <?php
            foreach ($app->content->getAllProviders() as $p):
            ?>
            <tr>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['type']) ?></td>
                <td><?= htmlspecialchars($p['endpoint']) ?></td>
                <td><?= htmlspecialchars($p['ttl']) ?> sec</td>
                <td><?= $p['enabled'] ? 'Yes' : 'No' ?></td>
                <td>
                    <a href="<?= $app->adminUrl('providers') ?>&edit=<?= $p['id'] ?>">Edit</a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
                        <input type="hidden" name="delete_id" value="<?= (int) $p['id'] ?>">
                        <button type="submit" onclick="return confirm('Are you sure you want to delete this provider?')" style="padding:0; background:none; border:none; color:#ffcc00; cursor:pointer;">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="admin-box">
        <h2>Recent Errors <a href="<?= $app->adminUrl('content') ?>" style="font-size:0.6em; font-weight:normal;">Full Fetch Log &raquo;</a></h2>

        <div class="admin-log">
            <?php
            $logEntries = $app->content->getFetchLogs(20, null, null, 'error');

            if ($logEntries):
                foreach ($logEntries as $entry):
            ?>
                <div class="admin-log-entry">
                    <div class="admin-log-meta">
                        <strong><?= htmlspecialchars($entry['provider_name']) ?></strong>
                        <span style="color:#e74c3c;"><?= htmlspecialchars($entry['status']) ?></span>
                        <?php if (!empty($entry['host'])): ?>
                            <span><?= htmlspecialchars($entry['host']) ?></span>
                        <?php endif; ?>
                        <span><?= date('Y-m-d H:i:s', (int) $entry['created_at']) ?></span>
                    </div>
                    <div><?= htmlspecialchars($entry['message']) ?></div>
                    <div class="admin-log-details">
                        Topic: <?= htmlspecialchars($entry['topic']) ?> · Items: <?= (int) $entry['item_count'] ?>
                    </div>
                </div>
            <?php
                endforeach;
            else:
            ?>
                <p class="muted">No errors recorded. Everything looks good.</p>
            <?php endif; ?>
        </div>
    </div>

    

</div>
