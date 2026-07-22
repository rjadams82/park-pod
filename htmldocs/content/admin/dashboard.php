<div class="admin-container">

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
                <th>Date</th>
            </tr>
            <?php foreach ($recentLeads as $lead): ?>
            <tr>
                <td><?= htmlspecialchars($lead['name']) ?></td>
                <td><?= htmlspecialchars($lead['email']) ?></td>
                <td><?= date('Y-m-d H:i', (int) $lead['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
        <p>No leads yet.</p>
        <?php endif; ?>
    </div>

</div>
