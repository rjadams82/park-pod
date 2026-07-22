<div class="first-run-wrapper">
    <h1>ParkPod</h1>
    <h3>Create an Admin Account</h3>
    <p>Create your initial administrator account below.</p>

    <?php if (!empty($error)): ?>
        <div class="login-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $app->adminUrl() ?>">
        <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="password_confirm" placeholder="Confirm Password" required>
        <button type="submit">Create Admin Account</button>
    </form>
</div>
