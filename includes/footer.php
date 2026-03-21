<!-- includes/footer.php -->
</div><!-- /.main-content -->
</div><!-- /.app-layout -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<!-- JsBarcode -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<!-- QRCode.js -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<!-- Custom JS -->
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<?php if (!empty($extraJs)): ?>
  <?php foreach ((array)$extraJs as $js): ?>
    <script><?= $js ?></script>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
