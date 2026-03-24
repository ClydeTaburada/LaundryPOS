<?php
/* includes/head.php — shared <head> block
 * Usage: require_once ROOT.'/includes/head.php';
 * Set $pageTitle before including.
 */
$pageTitle = $pageTitle ?? 'Lavenderia';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> — Lavenderia Laundry Services</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome 6 -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Custom CSS -->
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
  <!-- Expose base URL to JavaScript -->
  <script>const SITE_URL = '<?= SITE_URL ?>';</script>
</head>
<body>
