<?php
require 'public_html/config/database.php';
global $pdo;
if ($pdo instanceof PDO) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $r['setting_key'] . " = " . $r['setting_value'] . "\n";
    }
}
