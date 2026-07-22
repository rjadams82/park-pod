<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $title ?? 'Domain Team' ?></title>
        
        <link rel="stylesheet" href="/content/service/style.css">        

        <!-- Google Analytics -->
        <?php $gaId = $app->getSetting('admin_google_analytics') ?? $config['admin']['ga_id'] ?? ''; ?>
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
    <body class="sp-body">
