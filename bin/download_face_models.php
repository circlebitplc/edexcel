<?php
declare(strict_types=1);

$vendorDir = __DIR__ . '/../assets/vendor/face-api';
$modelsDir = __DIR__ . '/../assets/models/face';

if (!is_dir($vendorDir)) {
    mkdir($vendorDir, 0755, true);
}
if (!is_dir($modelsDir)) {
    mkdir($modelsDir, 0755, true);
}

echo "=== Downloading face-api.min.js ===\n";
$faceApiJs = file_get_contents('https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js');
if ($faceApiJs !== false) {
    file_put_contents($vendorDir . '/face-api.min.js', $faceApiJs);
    echo "Saved face-api.min.js (" . strlen($faceApiJs) . " bytes)\n";
} else {
    echo "Failed to download face-api.min.js\n";
}

$files = [
    'tiny_face_detector_model-weights_manifest.json',
    'tiny_face_detector_model-shard1',
    'face_landmark_68_model-weights_manifest.json',
    'face_landmark_68_model-shard1',
    'face_recognition_model-weights_manifest.json',
    'face_recognition_model-shard1',
    'face_recognition_model-shard2',
    'ssd_mobilenetv1_model-weights_manifest.json',
    'ssd_mobilenetv1_model-shard1',
    'ssd_mobilenetv1_model-shard2',
];

$baseUrl = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@master/weights/';

foreach ($files as $file) {
    $target = $modelsDir . '/' . $file;
    if (file_exists($target) && filesize($target) > 0) {
        echo "Already exists: $file\n";
        continue;
    }
    echo "Downloading $file ... ";
    $data = file_get_contents($baseUrl . $file);
    if ($data !== false) {
        file_put_contents($target, $data);
        echo "OK (" . strlen($data) . " bytes)\n";
    } else {
        echo "FAILED\n";
    }
}

echo "Done downloading face models.\n";
