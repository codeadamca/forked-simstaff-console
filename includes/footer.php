</main>

<footer class="footer">
    <?= APP_NAME ?> &copy; <?= date('Y') ?>
</footer>

<script src="<?= BASE_URL ?>/../assets/js/app.js"></script>
<?php if (!empty($extraJs)): ?>
    <?= $extraJs ?>
<?php endif; ?>
</body>
</html>
