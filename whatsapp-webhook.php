<?php
// whatsapp-webhook.php
$hub_challenge = $_GET['hub_challenge'] ?? null;
$hub_verify_token = $_GET['hub_verify_token'] ?? null;

$verify_token = 'edexcel_webhook_2026'; // Must match what you entered in the dashboard

if ($hub_verify_token === $verify_token) {
    echo $hub_challenge;
    exit;
}
// For other requests, just respond with 200
http_response_code(200);
echo 'OK';