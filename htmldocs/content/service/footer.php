
    <div class="footer">
        <p><a href="#contact-modal" class="cta-btn footer-cta">Contact Us</a></p>
        <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($app->getSetting('business_name') ?? 'Domain Team') ?></p>
    </div>
    <script>
        <?php $recaptchaKey = $app->getSetting('recaptcha_site_key'); ?>
        <?php if (!empty($recaptchaKey)): ?>
        document.getElementById('contact-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var form = this;
            grecaptcha.ready(function() {
                grecaptcha.execute('<?= htmlspecialchars($recaptchaKey) ?>', {action: 'submit'}).then(function(token) {
                    document.getElementById('g-recaptcha-response').value = token;
                    form.submit();
                });
            });
        });
        <?php endif; ?>
        function fadeOutModal(clearContent) {
            var modal = document.getElementById('contact-modal');
            modal.style.opacity = '0';
            modal.addEventListener('transitionend', function handler() {
                modal.removeEventListener('transitionend', handler);
                modal.style.display = 'none';
                modal.style.opacity = '1';
                if (clearContent) clearContent();
            });
        }
        document.addEventListener('click', function(e) {
            var link = e.target.closest('a[href="#contact-modal"]');
            if (link) {
                e.preventDefault();
                var modal = document.getElementById('contact-modal');
                modal.style.display = 'flex';
                modal.style.opacity = '1';
            }
            if (e.target.closest('.contact-modal-close')) {
                e.preventDefault();
                fadeOutModal();
            }
        });
        document.getElementById('contact-modal').addEventListener('click', function(e) {
            if (e.target === this) fadeOutModal();
        });
        var thankYou = document.getElementById('contact-thankyou');
        if (thankYou) {
            setTimeout(function() {
                fadeOutModal(function() { thankYou.innerHTML = ''; });
            }, 3000);
        }
    </script>
    </body>
</html>
