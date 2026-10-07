<?php
declare(strict_types=1);

/**
 * Edexcel College
 * WhatsApp Admin Inbox
 *
 * URL:
 * https://edexcel.college/admin/whatsapp/
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/load_env.php';
require_once __DIR__ . '/../../config/whatsapp_gateway.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Edexcel\Services\EvolutionApiService;

require_admin();

if (!function_exists('is_admin') || !is_admin()) {
    http_response_code(403);
    exit('Access denied.');
}

/* =========================================================
   HELPERS
========================================================= */

function wa_e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function wa_phone(string $phone): string
{
    $phone = preg_replace('/@.*$/', '', trim($phone)) ?? '';
    $phone = preg_replace('/:\d+$/', '', $phone) ?? '';
    $phone = preg_replace('/\D+/', '', $phone) ?? '';

    if (
        str_starts_with($phone, '0') &&
        strlen($phone) === 10
    ) {
        $phone = '94' . substr($phone, 1);
    }

    return $phone;
}

function wa_time(?string $value): string
{
    if (!$value) {
        return '';
    }

    $time = strtotime($value);

    if (!$time) {
        return '';
    }

    return date('d M, h:i A', $time);
}

/* =========================================================
   DATABASE COLUMN CHECK
========================================================= */

$columns = [];

try {
    $columnStmt = $pdo->query("
        SHOW COLUMNS FROM whatsapp_bot_messages
    ");

    foreach ($columnStmt as $column) {
        $columns[$column['Field']] = true;
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('Unable to inspect WhatsApp message table.');
}

$hasCreatedAt = isset($columns['created_at']);

/* =========================================================
   AJAX / API REQUESTS
========================================================= */

if (
    isset($_GET['ajax']) ||
    isset($_POST['ajax'])
) {

    header('Content-Type: application/json; charset=utf-8');

    $action =
        $_GET['action']
        ?? $_POST['action']
        ?? '';

    try {

        /* -------------------------------------------------
           SEND MESSAGE
        ------------------------------------------------- */

        if ($action === 'send') {

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException(
                    'POST request required.'
                );
            }

            if (
                !verify_csrf_token(
                    $_POST['csrf_token'] ?? ''
                )
            ) {
                throw new RuntimeException(
                    'Security token expired. Refresh the page.'
                );
            }

            $phone = wa_phone(
                (string)($_POST['phone'] ?? '')
            );

            $message = trim(
                (string)($_POST['message'] ?? '')
            );

            if ($phone === '') {
                throw new RuntimeException(
                    'Invalid WhatsApp number.'
                );
            }

            if ($message === '') {
                throw new RuntimeException(
                    'Message cannot be empty.'
                );
            }

            if (mb_strlen($message) > 4000) {
                throw new RuntimeException(
                    'Message is too long.'
                );
            }

            $evolution = whatsapp_sender();

            /*
             * Send through the configured WhatsApp provider.
             */
            $result =
                $evolution->sendText(
                    $phone,
                    $message
                );

            /*
             * Save outgoing message.
             */
            $stmt = $pdo->prepare("
                INSERT INTO whatsapp_bot_messages
                    (
                        phone,
                        direction,
                        message,
                        event_name,
                        message_id
                    )
                VALUES
                    (
                        ?,
                        'outbound',
                        ?,
                        'ADMIN_REPLY',
                        ?
                    )
            ");

            $messageId = '';

            if (
                is_array($result) &&
                isset($result['key']['id'])
            ) {
                $messageId =
                    (string)$result['key']['id'];
            }

            $stmt->execute([
                $phone,
                $message,
                $messageId
            ]);

            echo json_encode([
                'ok' => true,
                'message_id' => $messageId
            ]);

            exit;
        }

        /* -------------------------------------------------
           LOAD CONVERSATION
        ------------------------------------------------- */

        if ($action === 'messages') {

            $phone =
                wa_phone(
                    (string)($_GET['phone'] ?? '')
                );

            if ($phone === '') {
                throw new RuntimeException(
                    'Phone number is required.'
                );
            }

            $orderColumn =
                $hasCreatedAt
                    ? 'm.created_at'
                    : 'm.id';

            $sql = "
                SELECT
                    m.id,
                    m.phone,
                    m.direction,
                    m.message,
                    m.event_name,
                    m.message_id
                    " .
                    (
                        $hasCreatedAt
                        ? ", m.created_at"
                        : ""
                    ) . "
                FROM whatsapp_bot_messages m
                WHERE m.phone = ?
                ORDER BY {$orderColumn} ASC
                LIMIT 500
            ";

            $stmt =
                $pdo->prepare($sql);

            $stmt->execute([
                $phone
            ]);

            $messages =
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );

            echo json_encode([
                'ok' => true,
                'messages' => $messages
            ]);

            exit;
        }

        /* -------------------------------------------------
           CONVERSATIONS
        ------------------------------------------------- */

        if ($action === 'conversations') {

            $orderColumn =
                $hasCreatedAt
                    ? 'MAX(m.created_at)'
                    : 'MAX(m.id)';

            $timeSelect =
                $hasCreatedAt
                    ? 'MAX(m.created_at) AS last_time'
                    : 'NULL AS last_time';

            /*
             * Get latest conversation per phone.
             */
            $sql = "
                SELECT
                    m.phone,
                    MAX(m.id) AS last_id,
                    {$timeSelect},

                    COUNT(*) AS message_count,

                    SUM(
                        CASE
                            WHEN m.direction = 'inbound'
                            THEN 1
                            ELSE 0
                        END
                    ) AS inbound_count,

                    MAX(
                        CASE
                            WHEN m.id = (
                                SELECT MAX(x.id)
                                FROM whatsapp_bot_messages x
                                WHERE x.phone = m.phone
                            )
                            THEN m.message
                            ELSE NULL
                        END
                    ) AS last_message

                FROM whatsapp_bot_messages m

                GROUP BY
                    m.phone

                ORDER BY
                    {$orderColumn} DESC

                LIMIT 100
            ";

            $stmt =
                $pdo->query($sql);

            $rows =
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );

            /*
             * Attach student information.
             */
            foreach ($rows as &$row) {

                $phone =
                    wa_phone(
                        (string)$row['phone']
                    );

                $student = null;

                /*
                 * First try WhatsApp contact mapping.
                 */
                $stmtStudent =
                    $pdo->prepare("
                        SELECT
                            w.student_id,
                            u.username
                        FROM whatsapp_bot_contacts w
                        LEFT JOIN users u
                            ON u.id = w.student_id
                        WHERE w.phone = ?
                        LIMIT 1
                    ");

                $stmtStudent->execute([
                    $phone
                ]);

                $student =
                    $stmtStudent->fetch(
                        PDO::FETCH_ASSOC
                    );

                $row['student_id'] =
                    $student['student_id']
                    ?? null;

                $row['student_name'] =
                    $student['username']
                    ?? '';

                $row['phone'] =
                    $phone;
            }

            unset($row);

            echo json_encode([
                'ok' => true,
                'conversations' => $rows
            ]);

            exit;
        }

        /* -------------------------------------------------
           STATUS
        ------------------------------------------------- */

        if ($action === 'status') {

            if (whatsapp_provider() === 'meta') {
                echo json_encode([
                    'ok' => true,
                    'provider' => 'meta',
                    'instances' => [],
                ]);
                exit;
            }

            $evolution =
                new EvolutionApiService();

            $instances =
                $evolution->fetchInstances();

            echo json_encode([
                'ok' => true,
                'instances' => $instances
            ]);

            exit;
        }

        throw new RuntimeException(
            'Unknown WhatsApp action.'
        );

    } catch (Throwable $e) {

        http_response_code(400);

        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage()
        ]);

        exit;
    }
}

/* =========================================================
   INITIAL SELECTED PHONE
========================================================= */

$selectedPhone =
    wa_phone(
        (string)($_GET['phone'] ?? '')
    );

/* =========================================================
   CSRF
========================================================= */

$csrf =
    $_SESSION['csrf_token']
    ?? '';

if (
    function_exists('generate_csrf_token')
) {
    $csrf =
        generate_csrf_token();
} elseif (
    function_exists('csrf_token')
) {
    $csrf =
        csrf_token();
}

?>
<!doctype html>
<html lang="en">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>WhatsApp Inbox | Edexcel College</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>

<style>

:root {
    --wa-green: #128c7e;
    --wa-dark: #075e54;
    --wa-light: #e9f5f1;
    --border: #e5e7eb;
    --muted: #6b7280;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f3f4f6;
    font-family:
        Inter,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;
}

.wa-page {
    height: 100vh;
    display: flex;
    flex-direction: column;
}

/* =========================================================
   HEADER
========================================================= */

.wa-header {
    height: 64px;
    background: #fff;
    border-bottom: 1px solid var(--border);

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 20px;

    flex-shrink: 0;
}

.wa-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.wa-title-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;

    background: #25d366;
    color: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 22px;
}

.wa-title h1 {
    margin: 0;
    font-size: 19px;
    font-weight: 700;
}

.wa-title small {
    color: var(--muted);
}

/* =========================================================
   MAIN
========================================================= */

.wa-main {
    flex: 1;
    min-height: 0;

    display: grid;
    grid-template-columns: 350px minmax(0, 1fr);

    background: #fff;
}

/* =========================================================
   CONVERSATION LIST
========================================================= */

.conversations {
    border-right: 1px solid var(--border);

    display: flex;
    flex-direction: column;

    min-width: 0;
}

.conversation-search {
    padding: 12px;
    border-bottom: 1px solid var(--border);
}

.conversation-list {
    flex: 1;
    overflow-y: auto;
}

.conversation {
    padding: 13px 15px;

    display: flex;
    gap: 12px;

    border-bottom: 1px solid #f0f0f0;

    cursor: pointer;

    transition: background .15s;
}

.conversation:hover {
    background: #f7faf9;
}

.conversation.active {
    background: #e8f5f2;
}

.avatar {
    width: 44px;
    height: 44px;

    flex: 0 0 44px;

    border-radius: 50%;

    background: #d9fdd3;
    color: #075e54;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 700;
}

.conversation-body {
    min-width: 0;
    flex: 1;
}

.conversation-top {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.conversation-name {
    font-weight: 700;
    color: #111827;

    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversation-time {
    font-size: 11px;
    color: #9ca3af;
    white-space: nowrap;
}

.conversation-preview {
    margin-top: 3px;

    color: #6b7280;
    font-size: 13px;

    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.phone-label {
    font-size: 11px;
    color: #9ca3af;
}

/* =========================================================
   CHAT
========================================================= */

.chat {
    min-width: 0;

    display: flex;
    flex-direction: column;

    background:
        radial-gradient(
            circle at 20px 20px,
            rgba(0,0,0,.025) 1px,
            transparent 1px
        );

    background-size: 40px 40px;
}

.chat-empty {
    flex: 1;

    display: flex;
    align-items: center;
    justify-content: center;

    color: #6b7280;

    text-align: center;
}

.chat-empty-icon {
    font-size: 55px;
    color: #25d366;
    margin-bottom: 10px;
}

.chat-header {
    height: 66px;

    background: #fff;

    border-bottom: 1px solid var(--border);

    display: none;

    align-items: center;

    padding: 0 18px;

    gap: 12px;

    flex-shrink: 0;
}

.chat-header.visible {
    display: flex;
}

.chat-header-info {
    flex: 1;
}

.chat-header-name {
    font-weight: 700;
}

.chat-header-phone {
    font-size: 12px;
    color: #6b7280;
}

.messages {
    flex: 1;

    overflow-y: auto;

    padding: 22px;

    display: flex;
    flex-direction: column;
    gap: 7px;
}

.message {
    max-width: min(70%, 650px);

    padding: 8px 11px;

    border-radius: 9px;

    line-height: 1.45;

    font-size: 14px;

    white-space: pre-wrap;

    word-break: break-word;

    box-shadow:
        0 1px 1px rgba(0,0,0,.06);
}

.message.inbound {
    align-self: flex-start;

    background: #fff;

    border-top-left-radius: 2px;
}

.message.outbound {
    align-self: flex-end;

    background: #d9fdd3;

    border-top-right-radius: 2px;
}

.message-meta {
    margin-top: 4px;

    display: flex;
    justify-content: flex-end;

    gap: 4px;

    color: #8696a0;

    font-size: 10px;
}

.message-direction {
    font-weight: 600;
}

.chat-composer {
    background: #f0f2f5;

    padding: 10px;

    display: none;

    gap: 8px;

    flex-shrink: 0;
}

.chat-composer.visible {
    display: flex;
}

.chat-input {
    flex: 1;

    border: none;
    outline: none;

    border-radius: 22px;

    padding: 11px 16px;

    resize: none;

    min-height: 44px;
    max-height: 120px;
}

.send-btn {
    width: 46px;
    height: 46px;

    border-radius: 50%;

    border: none;

    background: #25d366;
    color: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 19px;
}

.send-btn:hover {
    background: #20bd5b;
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 800px) {

    .wa-main {
        grid-template-columns: 1fr;
    }

    .conversations {
        display: flex;
    }

    .chat {
        display: none;
    }

    .wa-main.chat-open .conversations {
        display: none;
    }

    .wa-main.chat-open .chat {
        display: flex;
    }

    .mobile-back {
        display: inline-flex !important;
    }

    .message {
        max-width: 86%;
    }

}

.mobile-back {
    display: none;
}

</style>

</head>

<body>

<div class="wa-page">

<!-- =====================================================
     HEADER
====================================================== -->

<header class="wa-header">

    <div class="wa-title">

        <div class="wa-title-icon">
            <i class="bi bi-whatsapp"></i>
        </div>

        <div>
            <h1>WhatsApp Inbox</h1>
            <small>Edexcel College</small>
        </div>

    </div>

    <div class="d-flex align-items-center gap-2">

        <span
            id="connectionStatus"
            class="badge text-bg-secondary"
        >
            Checking...
        </span>

        <button
            class="btn btn-sm btn-outline-secondary"
            onclick="loadConversations()"
        >
            <i class="bi bi-arrow-clockwise"></i>
        </button>

    </div>

</header>

<!-- =====================================================
     MAIN
====================================================== -->

<main
    class="wa-main"
    id="waMain"
>

<!-- =====================================================
     CONVERSATIONS
====================================================== -->

<section class="conversations">

    <div class="conversation-search">

        <div class="input-group">

            <span class="input-group-text">
                <i class="bi bi-search"></i>
            </span>

            <input
                type="search"
                id="searchConversation"
                class="form-control"
                placeholder="Search conversations..."
                autocomplete="off"
            >

        </div>

    </div>

    <div
        id="conversationList"
        class="conversation-list"
    >

        <div class="text-center p-4 text-muted">
            Loading conversations...
        </div>

    </div>

</section>

<!-- =====================================================
     CHAT
====================================================== -->

<section class="chat">

    <div
        id="chatEmpty"
        class="chat-empty"
    >

        <div>

            <div class="chat-empty-icon">
                <i class="bi bi-whatsapp"></i>
            </div>

            <h4>WhatsApp Inbox</h4>

            <p>
                Select a conversation to view messages.
            </p>

        </div>

    </div>

    <div
        id="chatHeader"
        class="chat-header"
    >

        <button
            class="btn btn-light mobile-back"
            onclick="closeMobileChat()"
        >
            <i class="bi bi-arrow-left"></i>
        </button>

        <div class="avatar">
            <i class="bi bi-person"></i>
        </div>

        <div class="chat-header-info">

            <div
                id="chatName"
                class="chat-header-name"
            >
                WhatsApp
            </div>

            <div
                id="chatPhone"
                class="chat-header-phone"
            ></div>

        </div>

        <a
            id="openWhatsApp"
            href="#"
            target="_blank"
            class="btn btn-sm btn-success"
        >
            <i class="bi bi-whatsapp"></i>
        </a>

    </div>

    <div
        id="messages"
        class="messages"
    ></div>

    <div
        id="chatComposer"
        class="chat-composer"
    >

        <textarea
            id="messageInput"
            class="chat-input"
            placeholder="Type a WhatsApp message..."
            rows="1"
        ></textarea>

        <button
            id="sendButton"
            class="send-btn"
            type="button"
            onclick="sendMessage()"
        >
            <i class="bi bi-send-fill"></i>
        </button>

    </div>

</section>

</main>

</div>

<script>

const CSRF_TOKEN =
    <?= json_encode($csrf) ?>;

const BASE_URL =
    <?= json_encode($_SERVER['SCRIPT_NAME']) ?>;

let selectedPhone =
    <?= json_encode($selectedPhone) ?>;

let selectedName = '';

let conversations = [];

let refreshTimer = null;


/* =========================================================
   API
========================================================= */

async function api(
    action,
    options = {}
) {

    const url =
        BASE_URL +
        '?ajax=1&action=' +
        encodeURIComponent(action);

    const response =
        await fetch(
            url,
            {
                credentials: 'same-origin',
                ...options
            }
        );

    const data =
        await response.json();

    if (!response.ok || !data.ok) {

        throw new Error(
            data.error ||
            'Request failed.'
        );

    }

    return data;
}


/* =========================================================
   CONVERSATIONS
========================================================= */

async function loadConversations() {

    const list =
        document.getElementById(
            'conversationList'
        );

    try {

        const data =
            await api(
                'conversations'
            );

        conversations =
            data.conversations || [];

        renderConversations();

    } catch (error) {

        list.innerHTML = `
            <div class="alert alert-danger m-3">
                ${escapeHtml(error.message)}
            </div>
        `;

    }

}


/* =========================================================
   RENDER CONVERSATIONS
========================================================= */

function renderConversations() {

    const list =
        document.getElementById(
            'conversationList'
        );

    const search =
        document
            .getElementById(
                'searchConversation'
            )
            .value
            .toLowerCase()
            .trim();

    const filtered =
        conversations.filter(
            item => {

                const text =
                    (
                        item.phone +
                        ' ' +
                        item.student_name +
                        ' ' +
                        item.last_message
                    )
                    .toLowerCase();

                return text.includes(
                    search
                );

            }
        );

    if (!filtered.length) {

        list.innerHTML = `
            <div class="text-center text-muted p-4">
                <i class="bi bi-chat-square-text fs-2"></i>
                <div class="mt-2">
                    No conversations found.
                </div>
            </div>
        `;

        return;
    }

    list.innerHTML =
        filtered
            .map(
                item => {

                    const phone =
                        escapeHtml(
                            item.phone
                        );

                    const name =
                        item.student_name
                            ? escapeHtml(
                                item.student_name
                              )
                            : phone;

                    const preview =
                        escapeHtml(
                            item.last_message || ''
                        );

                    const active =
                        selectedPhone ===
                        item.phone
                            ? 'active'
                            : '';

                    const time =
                        item.last_time
                            ? escapeHtml(
                                formatDate(
                                    item.last_time
                                )
                              )
                            : '';

                    return `
                        <div
                            class="conversation ${active}"
                            onclick="openConversation(
                                '${escapeJs(item.phone)}',
                                '${escapeJs(
                                    item.student_name ||
                                    item.phone
                                )}'
                            )"
                        >

                            <div class="avatar">
                                ${
                                    item.student_name
                                    ? escapeHtml(
                                        getInitials(
                                            item.student_name
                                        )
                                      )
                                    : '<i class="bi bi-person"></i>'
                                }
                            </div>

                            <div class="conversation-body">

                                <div class="conversation-top">

                                    <div class="conversation-name">
                                        ${name}
                                    </div>

                                    <div class="conversation-time">
                                        ${time}
                                    </div>

                                </div>

                                <div class="phone-label">
                                    +${phone}
                                </div>

                                <div class="conversation-preview">
                                    ${preview}
                                </div>

                            </div>

                        </div>
                    `;
                }
            )
            .join('');
}


/* =========================================================
   OPEN CONVERSATION
========================================================= */

async function openConversation(
    phone,
    name
) {

    selectedPhone =
        phone;

    selectedName =
        name || phone;

    document
        .getElementById(
            'waMain'
        )
        .classList.add(
            'chat-open'
        );

    document
        .getElementById(
            'chatEmpty'
        )
        .style.display =
            'none';

    document
        .getElementById(
            'chatHeader'
        )
        .classList.add(
            'visible'
        );

    document
        .getElementById(
            'chatComposer'
        )
        .classList.add(
            'visible'
        );

    document
        .getElementById(
            'chatName'
        )
        .textContent =
            selectedName;

    document
        .getElementById(
            'chatPhone'
        )
        .textContent =
            '+' + phone;

    document
        .getElementById(
            'openWhatsApp'
        )
        .href =
            'https://wa.me/' +
            phone;

    renderConversations();

    await loadMessages();

}


/* =========================================================
   LOAD MESSAGES
========================================================= */

async function loadMessages() {

    if (!selectedPhone) {
        return;
    }

    const container =
        document.getElementById(
            'messages'
        );

    try {

        const data =
            await api(
                'messages' +
                '&phone=' +
                encodeURIComponent(
                    selectedPhone
                )
            );

        const messages =
            data.messages || [];

        container.innerHTML =
            messages
                .map(
                    message => {

                        const direction =
                            message.direction ===
                            'inbound'
                                ? 'inbound'
                                : 'outbound';

                        const time =
                            message.created_at
                                ? formatDate(
                                    message.created_at
                                  )
                                : '#' +
                                  message.id;

                        return `
                            <div
                                class="message ${direction}"
                            >

                                <div>
                                    ${escapeHtml(
                                        message.message
                                    )}
                                </div>

                                <div class="message-meta">

                                    <span>
                                        ${escapeHtml(
                                            time
                                        )}
                                    </span>

                                    ${
                                        direction ===
                                        'inbound'
                                        ? '<span>←</span>'
                                        : '<span>✓</span>'
                                    }

                                </div>

                            </div>
                        `;
                    }
                )
                .join('');

        container.scrollTop =
            container.scrollHeight;

    } catch (error) {

        container.innerHTML = `
            <div class="alert alert-danger">
                ${escapeHtml(
                    error.message
                )}
            </div>
        `;

    }

}


/* =========================================================
   SEND MESSAGE
========================================================= */

async function sendMessage() {

    if (!selectedPhone) {
        return;
    }

    const input =
        document.getElementById(
            'messageInput'
        );

    const button =
        document.getElementById(
            'sendButton'
        );

    const message =
        input.value.trim();

    if (!message) {
        return;
    }

    button.disabled = true;

    try {

        const body =
            new URLSearchParams();

        body.append(
            'ajax',
            '1'
        );

        body.append(
            'action',
            'send'
        );

        body.append(
            'csrf_token',
            CSRF_TOKEN
        );

        body.append(
            'phone',
            selectedPhone
        );

        body.append(
            'message',
            message
        );

        const response =
            await fetch(
                BASE_URL,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type':
                            'application/x-www-form-urlencoded'
                    },
                    body
                }
            );

        const data =
            await response.json();

        if (
            !response.ok ||
            !data.ok
        ) {
            throw new Error(
                data.error ||
                'Message could not be sent.'
            );
        }

        input.value = '';

        await loadMessages();

        await loadConversations();

    } catch (error) {

        alert(
            error.message
        );

    } finally {

        button.disabled = false;

        input.focus();

    }

}


/* =========================================================
   CONNECTION STATUS
========================================================= */

async function checkStatus() {

    const badge =
        document.getElementById(
            'connectionStatus'
        );

    try {

        const data =
            await api(
                'status'
            );

        const instances =
            data.instances;

        let instance = null;

        if (
            Array.isArray(instances)
        ) {

            instance =
                instances.find(
                    item =>
                        item.name ===
                        'edexcel'
                );

        } else if (
            instances &&
            instances.name
        ) {

            instance =
                instances;

        }

        if (
            instance &&
            (
                instance.connectionStatus ===
                'open'
            )
        ) {

            badge.className =
                'badge text-bg-success';

            badge.textContent =
                'WhatsApp Connected';

        } else {

            badge.className =
                'badge text-bg-danger';

            badge.textContent =
                'WhatsApp Disconnected';

        }

    } catch (error) {

        badge.className =
            'badge text-bg-danger';

        badge.textContent =
            'API Error';

    }

}


/* =========================================================
   MOBILE
========================================================= */

function closeMobileChat() {

    document
        .getElementById(
            'waMain'
        )
        .classList.remove(
            'chat-open'
        );

}


/* =========================================================
   SEARCH
========================================================= */

document
    .getElementById(
        'searchConversation'
    )
    .addEventListener(
        'input',
        renderConversations
    );


/* =========================================================
   ENTER TO SEND
========================================================= */

document
    .getElementById(
        'messageInput'
    )
    .addEventListener(
        'keydown',
        function(event) {

            if (
                event.key === 'Enter' &&
                !event.shiftKey
            ) {

                event.preventDefault();

                sendMessage();

            }

        }
    );


/* =========================================================
   UTILITIES
========================================================= */

function escapeHtml(value) {

    return String(value ?? '')
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );

}

function escapeJs(value) {

    return String(value ?? '')
        .replace(
            /\\/g,
            '\\\\'
        )
        .replace(
            /'/g,
            "\\'"
        )
        .replace(
            /\r/g,
            ''
        )
        .replace(
            /\n/g,
            '\\n'
        );

}

function getInitials(name) {

    const parts =
        String(name)
            .trim()
            .split(/\s+/)
            .filter(Boolean);

    if (!parts.length) {
        return 'WA';
    }

    return parts
        .slice(0, 2)
        .map(
            part =>
                part.charAt(0)
                    .toUpperCase()
        )
        .join('');

}

function formatDate(value) {

    const date =
        new Date(
            value.replace(
                ' ',
                'T'
            )
        );

    if (
        Number.isNaN(
            date.getTime()
        )
    ) {
        return value;
    }

    return date.toLocaleString(
        'en-GB',
        {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        }
    );

}


/* =========================================================
   AUTO REFRESH
========================================================= */

async function refreshInbox() {

    await loadConversations();

    if (selectedPhone) {
        await loadMessages();
    }

}

loadConversations();

checkStatus();

setInterval(
    refreshInbox,
    5000
);

setInterval(
    checkStatus,
    15000
);

</script>

</body>
</html>