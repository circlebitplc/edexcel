<?php
declare(strict_types=1);

namespace Edexcel\Services;

use RuntimeException;

/**
 * Validate and store uploads under private directories with random names.
 */
final class SecureUploadService
{
    private const BLOCKED_EXT = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'bat', 'cmd',
        'com', 'scr', 'js', 'jsp', 'asp', 'aspx', 'cgi', 'pl', 'sh', 'htaccess',
    ];

    /** @var array<string,list<string>> mime => extensions */
    private const DEFAULT_ALLOW = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif'],
        'application/pdf' => ['pdf'],
        'text/plain' => ['txt'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ];

    private string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        $root = dirname(__DIR__, 2);
        $this->baseDir = $baseDir !== null && $baseDir !== ''
            ? rtrim($baseDir, '/\\')
            : $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private_uploads';
    }

    /**
     * @param array<string,mixed> $file $_FILES entry
     * @param list<string>|null $allowedExts lowercase extensions without dot; null = default allowlist
     * @return array{relative_path:string,absolute_path:string,original_name:string,mime:string,size:int,ext:string}
     */
    public function store(
        array $file,
        string $subdir = 'general',
        int $maxBytes = 8_388_608,
        ?array $allowedExts = null
    ): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed. Please try again.');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $original = basename((string)($file['name'] ?? 'file'));
        $size = (int)($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid upload.');
        }
        if ($size < 1 || $size > $maxBytes) {
            throw new RuntimeException('File exceeds the allowed size limit.');
        }

        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if ($ext === '' || in_array($ext, self::BLOCKED_EXT, true)) {
            throw new RuntimeException('This file type is not allowed.');
        }

        $mime = $this->detectMime($tmp, (string)($file['type'] ?? ''));
        $allowMap = self::DEFAULT_ALLOW;
        if ($allowedExts !== null) {
            $allowedExts = array_map('strtolower', $allowedExts);
            $filtered = [];
            foreach ($allowMap as $m => $exts) {
                $hit = array_values(array_intersect($exts, $allowedExts));
                if ($hit !== []) {
                    $filtered[$m] = $hit;
                }
            }
            // Also allow listed exts even if mime map is narrower — still require mime match when possible
            $allowMap = $filtered !== [] ? $filtered : $allowMap;
            if (!in_array($ext, $allowedExts, true)) {
                throw new RuntimeException('File extension is not allowed.');
            }
        }

        $mimeOk = false;
        foreach ($allowMap as $allowedMime => $exts) {
            if ($mime === $allowedMime && in_array($ext, $exts, true)) {
                $mimeOk = true;
                break;
            }
        }
        if (!$mimeOk) {
            // Secondary: extension in allowlist and mime starts with image/ or is pdf
            $allExt = [];
            foreach ($allowMap as $exts) {
                foreach ($exts as $e) {
                    $allExt[] = $e;
                }
            }
            if (!in_array($ext, $allExt, true)) {
                throw new RuntimeException('MIME type does not match an allowed upload.');
            }
            if (!str_starts_with($mime, 'image/') && $mime !== 'application/pdf' && $mime !== 'text/plain'
                && !str_contains($mime, 'officedocument') && $mime !== 'application/msword') {
                throw new RuntimeException('MIME type is not allowed.');
            }
        }

        $subdir = preg_replace('/[^a-zA-Z0-9_\\/-]/', '', $subdir) ?? 'general';
        $subdir = trim(str_replace('..', '', $subdir), '/');
        if ($subdir === '') {
            $subdir = 'general';
        }

        $dir = $this->baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create upload directory.');
        }
        $this->ensureDenyHtaccess($this->baseDir);

        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $stored;
        if (!@move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Could not save the uploaded file.');
        }
        @chmod($dest, 0640);

        $relative = 'storage/private_uploads/' . $subdir . '/' . $stored;
        return [
            'relative_path' => $relative,
            'absolute_path' => $dest,
            'original_name' => mb_substr($original, 0, 180),
            'mime' => $mime,
            'size' => $size,
            'ext' => $ext,
        ];
    }

    /**
     * Resolve a stored relative path only when it stays inside the private upload directory.
     */
    public function resolveStoredFile(string $relativePath): ?string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        if ($relativePath === '' || str_contains($relativePath, "\0") || str_contains($relativePath, '..')) {
            return null;
        }
        $prefix = 'storage/private_uploads/';
        if (!str_starts_with($relativePath, $prefix)) {
            return null;
        }
        $tail = substr($relativePath, strlen($prefix));
        if ($tail === '' || str_contains($tail, '..')) {
            return null;
        }

        $candidate = $this->baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $tail);
        $base = realpath($this->baseDir);
        $full = realpath($candidate);
        if ($base === false || $full === false || !is_file($full)) {
            return null;
        }
        $baseNorm = rtrim(strtolower(str_replace('\\', '/', $base)), '/');
        $fullNorm = strtolower(str_replace('\\', '/', $full));
        if (!str_starts_with($fullNorm, $baseNorm . '/')) {
            return null;
        }
        return $full;
    }

    private function detectMime(string $tmp, string $fallback): string
    {
        $mime = '';
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($tmp);
        }
        if ($mime === '' || $mime === 'application/octet-stream') {
            $mime = $fallback !== '' ? $fallback : $mime;
        }
        return strtolower(trim($mime));
    }

    private function ensureDenyHtaccess(string $dir): void
    {
        $ht = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($ht)) {
            @file_put_contents($ht, "Require all denied\nDeny from all\n");
        }
    }
}
