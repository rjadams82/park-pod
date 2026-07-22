    </div>
    <div class="footer">
        <p>Contact: <strong><?= $app->getSetting('lease_email')?></strong></p>
        <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($app->getSetting('business_name') ?? 'Domain Team') ?></p>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('th[data-sort-col]').forEach(function(th) {
            th.style.cursor = 'pointer';
            th.title = 'Click to sort';
            th.addEventListener('click', function() {
                var table = th.closest('table');
                var col = parseInt(th.getAttribute('data-sort-col'));
                var type = th.getAttribute('data-sort-type') || 'text';
                var tbody = table.querySelector('tbody') || table;
                var rows = Array.from(tbody.querySelectorAll('tr')).filter(function(r) { return !r.querySelector('th'); });
                var dir = th.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';
                th.setAttribute('data-sort-dir', dir);

                table.querySelectorAll('th[data-sort-col]').forEach(function(h) {
                    if (h !== th) h.removeAttribute('data-sort-dir');
                });

                rows.sort(function(a, b) {
                    var av = (a.cells[col] || {}).textContent || '';
                    var bv = (b.cells[col] || {}).textContent || '';
                    var cmp = 0;
                    if (type === 'number') {
                        cmp = parseFloat(av.replace(/[^0-9.\-]/g, '')) - parseFloat(bv.replace(/[^0-9.\-]/g, ''));
                    } else if (type === 'date') {
                        cmp = new Date(av) - new Date(bv);
                    } else {
                        cmp = av.localeCompare(bv);
                    }
                    return dir === 'asc' ? cmp : -cmp;
                });

                rows.forEach(function(r) { tbody.appendChild(r); });

                table.querySelectorAll('th[data-sort-col]').forEach(function(h) {
                    var arrow = h.querySelector('.sort-arrow');
                    if (!arrow) { arrow = document.createElement('span'); arrow.className = 'sort-arrow'; h.appendChild(arrow); }
                    if (h === th) {
                        arrow.textContent = dir === 'asc' ? ' \u2191' : ' \u2193';
                    } else {
                        arrow.textContent = '';
                    }
                });
            });
        });
    });
    </script>
    </body>
</html>
