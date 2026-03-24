<?php
/* ─── Database Configuration ───────────────────────────────────────────────── */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'laundry_db');
define('DB_PORT', 3306);

define('SITE_NAME', 'Lavenderia Laundry Services');
define('BASE_PATH', dirname(__DIR__));

// Dynamically resolve SITE_URL from the actual document root and folder name
// Works regardless of what the folder is named or where it lives under htdocs
(function () {
    $docRoot   = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $basePath  = rtrim(str_replace('\\', '/', BASE_PATH), '/');
    $subPath   = substr($basePath, strlen($docRoot)); // e.g. "/4A" or "/myapp"
    $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('SITE_URL', $scheme . '://' . $host . $subPath);
})();

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    die('
    <div style="font-family:Segoe UI,sans-serif;padding:40px;text-align:center;background:#F3E5F5;min-height:100vh;">
      <div style="max-width:480px;margin:auto;background:#fff;padding:2rem;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.1);">
        <h3 style="color:#8A2BE2">⚠️ Database Connection Failed</h3>
        <p style="color:#555">Could not connect to MySQL.<br>Please run the installer first.</p>
        <code style="font-size:.8rem;color:#c0392b">' . htmlspecialchars($conn->connect_error) . '</code>
      </div>
    </div>');
}

$conn->set_charset('utf8mb4');
