<?php
$guideItems = $app->content->getDynamicContent($config['site']['content_query_phrases'], $config['site']['domain'], 'wikipedia');
$trendingItems = $app->content->getDynamicContent($config['site']['content_query_phrases'], $config['site']['domain'], 'tinyfish');
$resourceTypes = ['openlibrary', 'duckduckgo'];
$resourceItems = $app->content->getDynamicContent($config['site']['content_query_phrases'], $config['site']['domain'], $resourceTypes[array_rand($resourceTypes)]);
?>

<div class="park-container">

    <!-- Header -->
    <div class="park-header">
        <img src="/content/park/graphics/<?= htmlspecialchars($visual['svg']) ?>.svg" alt="" class="park-watermark" width="300" height="300">
        <h1><?= htmlspecialchars($config['site']['domain']) ?></h1>
        <p><span class="topic-glyph"><?= $visual['glyph'] ?></span> A content resource focused on <strong><?= htmlspecialchars($config['site']['topic']) ?></strong></p>
    </div>

    <!-- SEO Content Sections -->
    <div class="park-sections">

        <div class="park-section" data-pattern="<?= htmlspecialchars($visual['pattern']) ?>">
            <h2><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg></span> Overview</h2>
            <?= $app->content->generateSEOBlock($config['site']['topic'], 'overview'); ?>
            <div class="domain-callout">
                <span class="domain-callout-label">Domain Inquiry</span>
                <p>This domain is part of a curated portfolio. It may be available for lease, partnership, or acquisition.</p>
                <a href="/about">View details &amp; get in touch &rsaquo;</a>
            </div>
        </div>

        <div class="park-section" data-pattern="<?= htmlspecialchars($visual['pattern']) ?>">
            <h2><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></span> Guides &amp; Insights</h2>
            <?= $app->content->generateSEOBlock($config['site']['topic'], 'guides'); ?>
            <?php foreach ($guideItems as $item): ?>
                <div class="dyn-item">
                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                    <p><?= htmlspecialchars($item['summary']) ?></p>
                    <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank">Read more</a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="park-section" data-pattern="<?= htmlspecialchars($visual['pattern']) ?>">
            <h2><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg></span> Trending Topics</h2>
            <?= $app->content->generateSEOBlock($config['site']['topic'], 'trending'); ?>
            <?php foreach ($trendingItems as $item): ?>
                <div class="dyn-item">
                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                    <p><?= htmlspecialchars($item['summary']) ?></p>
                    <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank">Read more</a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="park-section" data-pattern="<?= htmlspecialchars($visual['pattern']) ?>">
            <h2><span class="section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></span> Helpful Resources</h2>
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
