<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/ops.php';
require_admin();
ini_set('display_errors', '0');

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "CREATE TABLE IF NOT EXISTS support_tickets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_no VARCHAR(40) NOT NULL,
            requester_type ENUM('student','parent','teacher','admin') NOT NULL,
            requester_id INT NOT NULL,
            category VARCHAR(60) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
            status ENUM('open','assigned','in_progress','waiting_user','resolved','closed') NOT NULL DEFAULT 'open',
            assigned_to INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            resolved_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_support_ticket_no (ticket_no),
            KEY idx_support_ticket_status (status, priority, updated_at),
            KEY idx_support_ticket_requester (requester_type, requester_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "Support tickets OK.\n";
    
    $sql2 = "CREATE TABLE IF NOT EXISTS support_ticket_messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id BIGINT UNSIGNED NOT NULL,
            author_type ENUM('student','parent','teacher','admin') NOT NULL,
            author_id INT NOT NULL,
            message TEXT NOT NULL,
            attachment_path VARCHAR(1000) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_support_ticket_messages_ticket (ticket_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql2);
    echo "Messages OK.\n";
} catch (Throwable $e) {
    error_log('test_schema: ' . $e->getMessage());
    http_response_code(500);
    echo "Could not prepare support tables.\n";
}
