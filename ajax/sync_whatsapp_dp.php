<?php
declare(strict_types=1);

/**
 * ajax/sync_whatsapp_dp.php
 *
 * Asynchronous background endpoint for syncing WhatsApp Profile Picture (DP)
 * from Evolution API without blocking page renders.
 */

require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = get_logged_in_user($pdo);
if (!$user) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

if (!empty($user['profile_image'])) {
    echo json_encode(['success' => true, 'profile_picture_url' => $user['profile_image'], 'cached' => true]);
    exit;
}

$phone = '';
try {
    $p = $pdo->prepare('SELECT whatsapp_number FROM student_profiles WHERE user_id = ? LIMIT 1');
    $p->execute([(int)$user['id']]);
    $phone = normalize_phone((string)($p->fetchColumn() ?: ''));
} catch (Throwable $e) {
    $phone = '';
}
if ($phone === '') {
    $phone = normalize_phone((string)($user['username'] ?? ''));
}
if ($phone === '') {
    echo json_encode(['success' => false, 'profile_picture_url' => null, 'reason' => 'No valid phone']);
    exit;
}

try {
    // 1. Check contact table cache
    $stmt = $pdo->prepare("
        SELECT profile_picture_url, profile_picture_checked_at
        FROM whatsapp_bot_contacts
        WHERE phone = ?
        LIMIT 1
    ");
    $stmt->execute([$phone]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $cachedUrl = trim((string)($contact['profile_picture_url'] ?? ''));
    $checkedAt = (string)($contact['profile_picture_checked_at'] ?? '');

    // If checked within the last 6 hours, return cached URL immediately
    if (
        $cachedUrl !== '' &&
        $checkedAt !== '' &&
        strtotime($checkedAt) !== false &&
        strtotime($checkedAt) > (time() - 21600)
    ) {
        echo json_encode(['success' => true, 'profile_picture_url' => $cachedUrl, 'cached' => true]);
        exit;
    }

    // 2. Query Evolution API (not available on Meta Cloud API)
    require_once __DIR__ . '/../config/whatsapp_gateway.php';
    if (whatsapp_provider($pdo) === 'meta') {
        echo json_encode(['success' => true, 'profile_picture_url' => $cachedUrl ?: null, 'cached' => true]);
        exit;
    }

    $evolution = new \Edexcel\Services\EvolutionApiService();
    $freshUrl = $evolution->fetchProfilePictureUrl($phone);

    if ($freshUrl !== null && $freshUrl !== '') {
        $updateStmt = $pdo->prepare("
            INSERT INTO whatsapp_bot_contacts (phone, student_id, profile_picture_url, profile_picture_checked_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                profile_picture_url = VALUES(profile_picture_url),
                profile_picture_checked_at = NOW()
        ");
        $updateStmt->execute([$phone, (int)$user['id'], $freshUrl]);

        echo json_encode(['success' => true, 'profile_picture_url' => $freshUrl, 'cached' => false]);
        exit;
    }

    echo json_encode(['success' => true, 'profile_picture_url' => $cachedUrl ?: null]);
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'profile_picture_url' => $cachedUrl ?? null, 'error' => $e->getMessage()]);
}
