<div class="admin-container">

    <?php
    require_once $app->rootPath . '/app/charts.php';
    $charts = new Charts($app->db);

    $ranges = [
        '1d'   => '1 Day',
        '7d'   => '1 Week',
        '30d'  => '1 Month',
        '6m'   => '6 Months',
        '1y'   => '1 Year',
        'all'  => 'All Time',
    ];
    $range = $_GET['chart_range'] ?? '30d';
    if (!array_key_exists($range, $ranges)) $range = '30d';
    ?>

    <?php
    $trafficTrend = $charts->getTrafficTrend($range);
    $topDomains = $charts->getTopDomains(8, $range);
    $referrers = $charts->getReferrerSources(6, $range);
    $rangeLabel = $ranges[$range];
    if ($trafficTrend || $topDomains || $referrers):
    ?>
    <div class="admin-box">
        <div class="chart-header">
            <h2>Traffic Overview</h2>
            <div class="chart-filters">
                <?php foreach ($ranges as $key => $label): ?>
                <a href="?admin=dashboard&chart_range=<?= $key ?>" class="chart-filter <?= $key === $range ? 'active' : '' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="chart-grid">
            <?php if ($trafficTrend): ?>
            <div class="chart-cell">
                <?= $charts->renderLineChart($trafficTrend, 'day', 'hits', $rangeLabel . ' Traffic Trend') ?>
            </div>
            <?php endif; ?>
            <?php if ($topDomains): ?>
            <div class="chart-cell">
                <?= $charts->renderBarChart($topDomains, 'domain', 'hits', $rangeLabel . ' Top Domains') ?>
            </div>
            <?php endif; ?>
            <?php if ($referrers): ?>
            <div class="chart-cell">
                <?= $charts->renderHorizontalBarChart($referrers, 'source', 'hits', $rangeLabel . ' Referrer Sources') ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="admin-box">
        <h2>Recent Access Hosts  <a href="<?= $app->adminUrl('traffic') ?>" style="font-size:0.7em; font-weight:normal;">View All Traffic &raquo;</a></h2>

        <?php
        $recentHosts = $app->getRecentAccessHosts(25);
        if ($recentHosts):
        ?>
        <table class="admin-table">
            <tr>
                <th data-sort-col="0">Domain</th>
                <th data-sort-col="1">Status</th>
                <th data-sort-col="2" data-sort-type="number">Hits</th>
                <th data-sort-col="3" data-sort-type="date">Last Access</th>
            </tr>
            <?php foreach ($recentHosts as $row): ?>
            <tr>
                <td><a href="<?= $app->adminUrl('traffic') ?>&domain=<?= urlencode($row['domain']) ?>"><?= htmlspecialchars($row['domain']) ?></a></td>
                <td>
                    <?php if ($row['is_parked']): ?>
                        <span style="color:#2ecc71;">Parked</span>
                    <?php else: ?>
                        <a href="<?= $app->adminUrl('domains') ?>&add_host=<?= urlencode($row['domain']) ?>" style="color:#e74c3c;">Not setup!</a>
                    <?php endif; ?>
                </td>
                <td><?= (int) $row['hits'] ?></td>
                <td><?= date('Y-m-d H:i', (int) $row['last_access']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
        <p>No access data yet. Visits to parked domains will appear here.</p>
        <?php endif; ?>
    </div>

    <div class="admin-box">
        <h2>Recent Leads  <a href="<?= $app->adminUrl('leads') ?>" style="font-size:0.7em; font-weight:normal;">View All Leads &raquo;</a></h2>

        <?php
        $recentLeads = $app->db->query("SELECT * FROM leads ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        if ($recentLeads):
        ?>
        <table class="admin-table">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Domain</th>
                <th>Date</th>
            </tr>
            <?php foreach ($recentLeads as $lead): ?>
            <tr>
                <td><?= htmlspecialchars($lead['name']) ?></td>
                <td><?= htmlspecialchars($lead['email']) ?></td>
                <td><?= htmlspecialchars($lead['domain'] ?? '') ?></td>
                <td><?= date('Y-m-d H:i', (int) $lead['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
        <p>No leads yet.</p>
        <?php endif; ?>
    </div>

</div>
