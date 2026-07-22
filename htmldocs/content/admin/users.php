<?php
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editingUser = null;

if ($editId > 0) {
    $stmt = $app->db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$editId]);
    $editingUser = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="admin-container">


    <div class="admin-box admin-box-full">
        <h2><?= $editingUser ? 'Edit User' : 'Add User' ?></h2>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
            <?php if ($editingUser): ?>
                <input type="hidden" name="edit_id" value="<?= (int) $editingUser['id'] ?>">
            <?php endif; ?>
            <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($editingUser['username'] ?? '') ?>" required autocomplete="off">
            <input type="password" name="password" placeholder="<?= $editingUser ? 'New Password (optional)' : 'Password' ?>" <?= $editingUser ? '' : 'required' ?> autocomplete="new-password">
            <select name="role">
                <option value="admin" <?= (($editingUser['role'] ?? 'admin') === 'admin' ? 'selected' : '') ?>>Admin</option>
                <option value="editor" <?= (($editingUser['role'] ?? 'admin') === 'editor' ? 'selected' : '') ?>>Editor</option>
                <option value="viewer" <?= (($editingUser['role'] ?? 'admin') === 'viewer' ? 'selected' : '') ?>>Viewer</option>
            </select>
            <button type="submit"><?= $editingUser ? 'Save Changes' : 'Add User' ?></button>
            <?php if ($editingUser): ?>
                <a href="<?= $app->adminUrl('users') ?>" style="text-align:center; color:#ffcc00; margin-top:4px; display:block;">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>


    <div class="admin-box">
        <h2>Users</h2>

        <table class="admin-table">
            <tr>
                <th>Username</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>

            <?php
            $stmt = $app->db->query("SELECT * FROM users ORDER BY id ASC");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u):
            ?>
            <tr>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['role'] ?? 'admin') ?></td>
                <td>
                    <a href="<?= $app->adminUrl('users') ?>&edit=<?= $u['id'] ?>">Edit</a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
                        <input type="hidden" name="delete_id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" onclick="return confirm('Are you sure you want to delete this user?')" style="padding:0; background:none; border:none; color:#ffcc00; cursor:pointer;">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

</div>
