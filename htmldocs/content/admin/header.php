<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?= $config['admin']['title'] ?? 'ParkPod' ?> - <?= $title ?></title>
        <link rel="stylesheet" href="/content/admin/style.css">

        <?php
        $activePage = $_GET['admin'] ?? 'dashboard';
        $gaId = $app->getSetting('admin_google_analytics') ?? $config['admin']['ga_id'] ?? '';
        ?>

        <?php if (!empty($gaId)): ?>
            <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($gaId) ?>"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '<?= htmlspecialchars($gaId) ?>');
            </script>
        <?php endif; ?>

    </head>
    <body class="admin-body">
        <header class="admin-header">
            <div class="admin-header-inner">
                <span class="admin-header-title"><?= $config['admin']['title'] ?? 'ParkPod' ?> - <?= htmlspecialchars($config['site']['domain'] ?? 'Admin') ?></span>
                <?php if ($app->auth->isAuthenticated()): ?>
                <a href="<?= $app->adminUrl('logout') ?>" class="logout-link">Logout</a>
                <?php endif; ?>
            </div>
        </header>
        <div class="admin-wrapper">
            <?php if ($app->auth->isAuthenticated()): ?>
            <h1><?= $config['admin']['title'] ?? 'ParkPod' ?> - <?= $title ?></h1>
            <nav>
                <ul class="admin-tabs">
                    <?php if ($app->auth->canAccessPage('dashboard')): ?>
                    <li>
                        <a href="<?= $app->adminUrl('dashboard') ?>" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                    </li>
                    <?php endif; ?>
                    <?php if ($app->auth->canAccessPage('domains')): ?>
                    <li>
                        <a href="<?= $app->adminUrl('domains') ?>" class="<?= $activePage === 'domains' ? 'active' : '' ?>">Domains</a>
                    </li>
                    <?php endif; ?>
                    <?php if ($app->auth->canAccessPage('providers')): ?>
                    <li>
                        <a href="<?= $app->adminUrl('providers') ?>" class="<?= $activePage === 'providers' ? 'active' : '' ?>">Content Providers</a>
                    </li>
                    <?php endif; ?>
                    <?php if ($app->auth->canAccessPage('leads')): ?>
                    <li>
                        <a href="<?= $app->adminUrl('leads') ?>" class="<?= $activePage === 'leads' ? 'active' : '' ?>">Lead Tracking</a>
                    </li>
                    <?php endif; ?>
                    <?php if ($app->auth->canAccessPage('traffic')): ?>
                    <li>
                        <a href="<?= $app->adminUrl('traffic') ?>" class="<?= $activePage === 'traffic' ? 'active' : '' ?>">Traffic</a>
                    </li>
                    <?php endif; ?>
                    <?php if ($app->auth->canAccessPage('users')): ?>
                    <li>
                        <a href="<?= $app->adminUrl('users') ?>" class="<?= $activePage === 'users' ? 'active' : '' ?>">Users & Access</a>
                    </li>
                    <?php endif; ?>
                    <?php if ($app->auth->canAccessPage('settings')): ?>
                    <li>
                        <a href="<?= $app->adminUrl('settings') ?>" class="<?= $activePage === 'settings' ? 'active' : '' ?>">System Settings</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>