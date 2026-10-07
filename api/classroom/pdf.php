<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';

use Edexcel\Services\SecureUploadService;

$loaded = classroom_load_lesson($pdo);
$access = $loaded['access'];
$meeting = $loaded['meeting'];
$settings = classroom_settings($pdo);

if (!$meeting) {
    classroom_json(['ok' => false, 'error' => 'Class not found.'], 404);
}
classroom_api_require_access($access);

$meetingId = (int)$meeting['id'];
$userId = (int)($_SESSION['user_id'] ?? 0);
$pdfAccess = classroom_pdf_access($access, $settings);

$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? 'state')));
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

// ── 1. DOWNLOAD / STREAM ACTION ──────────────────────────────────────────────
if ($action === 'download') {
    $token = trim((string)($_GET['token'] ?? ''));
    $pdfId = 0;

    if ($token !== '') {
        $decoded = classroom_pdf_verify_download_token($token);
        if ($decoded !== null && (int)$decoded['user_id'] === $userId) {
            $pdfId = (int)$decoded['pdf_id'];
        }
    }

    // Fallback: If token expired or omitted, allow authorized class participants to view the meeting's active PDF
    if ($pdfId < 1) {
        $reqDocId = (int)($_GET['doc_id'] ?? 0);
        if ($reqDocId > 0 && $reqDocId === (int)($meeting['active_pdf_id'] ?? 0)) {
            $pdfId = $reqDocId;
        }
    }

    if ($pdfId < 1) {
        classroom_json(['ok' => false, 'error' => 'Invalid or expired document token.'], 403);
    }

    if (!$pdfAccess['can_view']) {
        classroom_json(['ok' => false, 'error' => 'You are not authorized to view this PDF.'], 403);
    }
    classroom_rate_limit('pdf_dl_' . $userId, 40, 60);

    $isAttachment = !empty($_GET['download']) && (string)$_GET['download'] === '1';
    if ($isAttachment && !$pdfAccess['can_download']) {
        classroom_json(['ok' => false, 'error' => 'You are not authorized to download this PDF file.'], 403);
    }

    $doc = classroom_pdf_get_document($pdo, $pdfId, $meetingId);
    if (!$doc) {
        classroom_json(['ok' => false, 'error' => 'PDF document not found in this class.'], 404);
    }

    $fullPath = (new SecureUploadService())->resolveStoredFile((string)$doc['stored_path']);
    if ($fullPath === null || !is_readable($fullPath)) {
        classroom_json(['ok' => false, 'error' => 'Document file is unavailable.'], 404);
    }

    // Log the download only for attachment downloads
    if ($isAttachment) {
        classroom_pdf_log_download($pdo, $pdfId, $userId);
    }

    // Clean output buffer before sending file
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $cleanFilename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', (string)$doc['original_filename']);
    if (!str_ends_with(strtolower($cleanFilename), '.pdf')) {
        $cleanFilename .= '.pdf';
    }

    $isAttachment = !empty($_GET['download']) && (string)$_GET['download'] === '1';
    $disposition = $isAttachment ? 'attachment' : 'inline';

    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . $disposition . '; filename="' . $cleanFilename . '"');
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . (string)filesize($fullPath));
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');

    readfile($fullPath);
    exit;
}

// ── 2. GET SIGNED DOWNLOAD TOKEN ──────────────────────────────────────────────
if ($action === 'sign' && $method === 'GET') {
    if (!$pdfAccess['can_download']) {
        classroom_json(['ok' => false, 'error' => 'Download not permitted.'], 403);
    }
    $pdfId = (int)($_GET['doc_id'] ?? $meeting['active_pdf_id'] ?? 0);
    if ($pdfId < 1) {
        classroom_json(['ok' => false, 'error' => 'No active PDF document.'], 400);
    }
    $doc = classroom_pdf_get_document($pdo, $pdfId, $meetingId);
    if (!$doc) {
        classroom_json(['ok' => false, 'error' => 'Document not found.'], 404);
    }
    $token = classroom_pdf_download_token($pdfId, $userId, 900);
    $downloadUrl = rtrim((string)BASE_URL, '/') . '/api/classroom/pdf.php?action=download&lesson=' . (int)$loaded['lesson']['id'] . '&token=' . urlencode($token);
    classroom_json([
        'ok' => true,
        'doc_id' => $pdfId,
        'token' => $token,
        'download_url' => $downloadUrl,
        'filename' => (string)$doc['original_filename'],
    ]);
}

// ── 3. GET STATE (POLL / SYNC) ────────────────────────────────────────────────
if ($action === 'state' && $method === 'GET') {
    if (!$pdfAccess['can_view']) {
        classroom_json(['ok' => false, 'error' => 'Access denied.'], 403);
    }
    $state = classroom_pdf_active_state($pdo, $meetingId, $userId);
    classroom_json([
        'ok' => true,
        'active' => $state !== null,
        'pdf_state' => $state,
        'can_upload' => $pdfAccess['can_upload'],
        'can_download' => $pdfAccess['can_download'],
        'pdf_enabled' => $pdfAccess['enabled'],
    ]);
}

// ── 4. POST ACTIONS (Teacher / Host Only) ──────────────────────────────────────
classroom_require_post();

if (!$pdfAccess['can_upload']) {
    classroom_json(['ok' => false, 'error' => 'Only the teacher can modify classroom PDFs.'], 403);
}

// ── 4a. PAGE NAVIGATION / ZOOM ───────────────────────────────────────────────
if ($action === 'page') {
    $input = classroom_read_json_body();
    $page = max(1, min(2000, (int)($input['page'] ?? 1)));
    $zoom = max(0.25, min(4.0, (float)($input['zoom'] ?? 1.0)));
    $totalPages = max(0, (int)($input['total_pages'] ?? 0));
    $docId = (int)($input['doc_id'] ?? $meeting['active_pdf_id'] ?? 0);

    if ($docId > 0 && $totalPages > 0) {
        $pdo->prepare("UPDATE classroom_pdf_documents SET total_pages = ? WHERE id = ? AND meeting_id = ?")
            ->execute([$totalPages, $docId, $meetingId]);
    }

    $pdo->prepare("UPDATE online_meetings SET active_pdf_page = ?, active_pdf_zoom = ? WHERE id = ?")
        ->execute([$page, $zoom, $meetingId]);

    $loaded['svc']->event($meetingId, $userId, 'pdf_page', ['page' => $page, 'zoom' => $zoom, 'doc_id' => $docId]);

    classroom_json([
        'ok' => true,
        'page' => $page,
        'zoom' => $zoom,
        'doc_id' => $docId,
    ]);
}

// ── 4b. CLOSE PDF ─────────────────────────────────────────────────────────────
if ($action === 'close') {
    $pdo->prepare("UPDATE online_meetings SET active_pdf_id = NULL, active_pdf_page = 1 WHERE id = ?")
        ->execute([$meetingId]);

    $loaded['svc']->event($meetingId, $userId, 'pdf_closed', null);

    classroom_json([
        'ok' => true,
        'closed' => true,
    ]);
}

// ── 4c. UPLOAD PDF ────────────────────────────────────────────────────────────
if ($action === 'upload') {
    classroom_rate_limit('pdf_upload_' . $userId, 10, 60);

    if (empty($_FILES['pdf_file'])) {
        classroom_json(['ok' => false, 'error' => 'No PDF file was provided.'], 400);
    }

    $file = $_FILES['pdf_file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = match ((int)($file['error'] ?? 0)) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large for the server.',
            UPLOAD_ERR_PARTIAL => 'File upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            default => 'Upload failed. Please try again.',
        };
        classroom_json(['ok' => false, 'error' => $msg], 400);
    }

    $maxMb = (int)($settings['pdf_max_mb'] ?? 30);
    $maxBytes = $maxMb * 1024 * 1024;
    $fileSize = (int)($file['size'] ?? 0);

    if ($fileSize > $maxBytes) {
        classroom_json(['ok' => false, 'error' => "PDF exceeds the maximum allowed size of {$maxMb}MB."], 422);
    }

    // Verify session PDF count limit
    $maxPerSession = (int)($settings['pdf_max_per_session'] ?? 5);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM classroom_pdf_documents WHERE meeting_id = ?");
    $countStmt->execute([$meetingId]);
    $existingCount = (int)$countStmt->fetchColumn();
    if ($existingCount >= $maxPerSession) {
        classroom_json(['ok' => false, 'error' => "Maximum of {$maxPerSession} PDFs per class session reached."], 422);
    }

    // Validate PDF magic bytes: must start with %PDF-
    $tmpPath = (string)($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmpPath)) {
        classroom_json(['ok' => false, 'error' => 'Invalid upload.'], 400);
    }

    $fh = @fopen($tmpPath, 'rb');
    if ($fh === false) {
        classroom_json(['ok' => false, 'error' => 'Could not read uploaded file.'], 500);
    }
    $header = (string)fread($fh, 8);
    fclose($fh);

    if (!str_starts_with($header, '%PDF-')) {
        classroom_json(['ok' => false, 'error' => 'Only valid PDF documents are accepted.'], 422);
    }

    try {
        $uploader = new SecureUploadService();
        $stored = $uploader->store(
            $file,
            'classroom_pdfs/' . $meetingId,
            $maxBytes,
            ['pdf']
        );
    } catch (Throwable $e) {
        classroom_json(['ok' => false, 'error' => $e->getMessage()], 422);
    }

    $originalName = (string)$stored['original_name'];
    $storedPath = (string)$stored['relative_path'];
    $mimeType = (string)$stored['mime'];
    $size = (int)$stored['size'];
    $teacherId = (int)($_SESSION['teacher_id'] ?? 0);
    $sessionId = (string)($meeting['public_id'] ?? '');

    // Set any previous active PDFs in this meeting to 'replaced'
    $pdo->prepare("UPDATE classroom_pdf_documents SET status = 'replaced' WHERE meeting_id = ? AND status = 'active'")
        ->execute([$meetingId]);

    // Insert new document record
    $ins = $pdo->prepare("
        INSERT INTO classroom_pdf_documents
        (meeting_id, timetable_id, uploaded_by, teacher_id, session_id, original_filename, stored_path, mime_type, file_size, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $ins->execute([
        $meetingId,
        (int)$loaded['lesson']['id'],
        $userId,
        $teacherId,
        $sessionId,
        $originalName,
        $storedPath,
        $mimeType,
        $size,
    ]);
    $newPdfId = (int)$pdo->lastInsertId();

    // Set active in online_meetings
    $pdo->prepare("UPDATE online_meetings SET active_pdf_id = ?, active_pdf_page = 1, active_pdf_zoom = 1.00 WHERE id = ?")
        ->execute([$newPdfId, $meetingId]);

    $loaded['svc']->event($meetingId, $userId, 'pdf_uploaded', [
        'doc_id' => $newPdfId,
        'filename' => $originalName,
        'size' => $size,
    ]);

    $downloadToken = classroom_pdf_download_token($newPdfId, $userId, 900);
    $downloadUrl = rtrim((string)BASE_URL, '/') . '/api/classroom/pdf.php?action=download&lesson=' . (int)$loaded['lesson']['id'] . '&token=' . urlencode($downloadToken);

    classroom_json([
        'ok' => true,
        'doc_id' => $newPdfId,
        'filename' => $originalName,
        'page' => 1,
        'zoom' => 1.0,
        'download_token' => $downloadToken,
        'download_url' => $downloadUrl,
    ]);
}

classroom_json(['ok' => false, 'error' => 'Unknown action.'], 400);

