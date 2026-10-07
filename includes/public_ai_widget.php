<?php
declare(strict_types=1);

/**
 * Floating Talk with AI widget for the public homepage.
 */
$publicAiApi = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/api/public_ai.php';
$publicAiCsrf = function_exists('csrf_token') ? csrf_token() : '';
$publicAiSignedIn = isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'student'
    && (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0) > 0;
?>
<div id="publicAiRoot"
     class="public-ai"
     data-api="<?= e($publicAiApi) ?>"
     data-csrf="<?= e($publicAiCsrf) ?>"
     data-signed-in="<?= $publicAiSignedIn ? '1' : '0' ?>">
    <button type="button" class="public-ai-launcher" id="publicAiOpen" aria-expanded="false" aria-controls="publicAiPanel">
        <i class="fas fa-comments" aria-hidden="true"></i>
        <span>Talk with AI</span>
    </button>
    <div class="public-ai-panel" id="publicAiPanel" hidden role="dialog" aria-labelledby="publicAiTitle">
        <div class="public-ai-head">
            <div>
                <strong id="publicAiTitle">Talk with AI</strong>
                <p>Edexcel College — classes, teachers, how to join, and IGCSE / IAL help.</p>
            </div>
            <button type="button" class="public-ai-close" id="publicAiClose" aria-label="Close chat">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="public-ai-log" id="publicAiLog" aria-live="polite"></div>
        <form class="public-ai-form" id="publicAiForm">
            <label class="visually-hidden" for="publicAiMessage">Message</label>
            <textarea id="publicAiMessage" rows="2" maxlength="2000" required placeholder="Ask about a class, a teacher, how to register, or a topic…"></textarea>
            <button type="submit" id="publicAiSend">Send</button>
        </form>
    </div>
</div>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/public_ai.css?v=<?= is_file(__DIR__ . '/../assets/css/public_ai.css') ? filemtime(__DIR__ . '/../assets/css/public_ai.css') : '1' ?>">
<script src="<?= BASE_URL ?>assets/js/public_ai.js?v=<?= is_file(__DIR__ . '/../assets/js/public_ai.js') ? filemtime(__DIR__ . '/../assets/js/public_ai.js') : '1' ?>" defer></script>
