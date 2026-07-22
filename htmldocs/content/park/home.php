<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($config['site']['domain']) ?></title>
<link rel="stylesheet" href="/content/style.css">
</head>
<body class="parkbody">

<?php
$guideItems = $app->content->getDynamicContent($config['site']['content_query_phrases'], $config['site']['domain'], 'wikipedia');
$trendingItems = $app->content->getDynamicContent($config['site']['content_query_phrases'], $config['site']['domain'], 'tinyfish');
$resourceTypes = ['openlibrary', 'duckduckgo'];
$resourceItems = $app->content->getDynamicContent($config['site']['content_query_phrases'], $config['site']['domain'], $resourceTypes[array_rand($resourceTypes)]);

?>

<div class="park-container">

    <!-- Header -->
    <div class="park-header">
        <h1><?= htmlspecialchars($config['site']['domain']) ?></h1>
        <p>A content resource focused on <strong><?= htmlspecialchars($config['site']['topic']) ?></strong></p>
    </div>

    <!-- SEO Content Sections -->
    <div class="park-sections">

        <div class="park-section">
            <h2>Overview</h2>
            <?= $app->content->generateSEOBlock($config['site']['topic'], 'overview'); ?>
        </div>

        <div class="park-section">
            <h2>Guides & Insights</h2>
            <?= $app->content->generateSEOBlock($config['site']['topic'], 'guides'); ?>
            <?php foreach ($guideItems as $item): ?>
                <div class="dyn-item">
                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                    <p><?= htmlspecialchars($item['summary']) ?></p>
                    <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank">Read more</a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="park-section">
            <h2>Trending Topics</h2>
            <?= $app->content->generateSEOBlock($config['site']['topic'], 'trending'); ?>
            <?php foreach ($trendingItems as $item): ?>
                <div class="dyn-item">
                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                    <p><?= htmlspecialchars($item['summary']) ?></p>
                    <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank">Read more</a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="park-section">
            <h2>Helpful Resources</h2>
            <?= $app->content->generateSEOBlock($config['site']['topic'], 'resources'); ?>
            <?php foreach ($resourceItems as $item): ?>
                <div class="dyn-item">
                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                    <p><?= htmlspecialchars($item['summary']) ?></p>
                    <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank">Read more</a>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <div class="park-about">
        <a href="/about">About this domain</a>
    </div>
