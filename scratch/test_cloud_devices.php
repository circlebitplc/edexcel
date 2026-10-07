<?php
$user = '30F66R';
$pass = 'aaasssddfffggghhh';

$endpoints = [
    'https://api.sms-gate.app/3rdparty/v1/devices',
    'https://api.sms-gate.app/3rdparty/v1/message',
    'https://api.sms-gate.app/3rdparty/v1/messages',
    'https://api.sms-gate.app/mobile/v1/device',
    'https://dashboard.sms-gate.app/api/auth',
];

foreach ($endpoints as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $user . ':' . $pass,
        CURLOPT_TIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "$url -> HTTP $code | $res\n";
}
