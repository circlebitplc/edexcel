<?php
declare(strict_types=1);

/**
 * Edexcel College - Canonical System Helpers
 * Centralized utility functions for escaping, formatting, and authentication.
 */

if (!function_exists('e')) {
    /**
     * Escape output for HTML context (prevent XSS).
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('student_e')) {
    function student_e(mixed $value): string
    {
        return e($value);
    }
}

if (!function_exists('wa_e')) {
    function wa_e(mixed $value): string
    {
        return e($value);
    }
}

if (!function_exists('normalize_phone')) {
    /**
     * Normalize international/local telephone numbers for WhatsApp and SMS.
     * E.g. "077 123 4567" -> "94771234567"
     */
    function normalize_phone(?string $phone): string
    {
        if ($phone === null) {
            return '';
        }
        $phone = preg_replace('/@.*$/', '', trim($phone)) ?? '';
        $phone = preg_replace('/:\d+$/', '', $phone) ?? '';
        $phone = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '94' . substr($phone, 1);
        }

        return $phone;
    }
}

if (!function_exists('wa_phone')) {
    function wa_phone(?string $phone): string
    {
        return normalize_phone($phone);
    }
}

if (!function_exists('whatsappUrl')) {
    function whatsappUrl(?string $phone): ?string
    {
        $digits = normalize_phone($phone);
        if ($digits === '') {
            return null;
        }
        return "https://wa.me/{$digits}";
    }
}

if (!function_exists('teacherInitials')) {
    /**
     * Extract 2-letter uppercase initials from teacher name.
     */
    function teacherInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $initials = '';

        if (is_array($parts)) {
            foreach ($parts as $part) {
                if ($part !== '') {
                    $initials .= strtoupper(substr($part, 0, 1));
                }
            }
        }

        return substr($initials ?: 'TE', 0, 2);
    }
}

if (!function_exists('teacherAvatarColor')) {
    /**
     * Deterministic, harmonic avatar color based on name hash.
     */
    function teacherAvatarColor(string $name): string
    {
        $colors = [
            '#03bfcb', '#f8b400', '#4a90d9', '#27ae60',
            '#e67e22', '#8e44ad', '#1abc9c', '#e74c3c'
        ];
        return $colors[abs(crc32($name)) % count($colors)];
    }
}

if (!function_exists('teacherPhotoPath')) {
    /**
     * Resolve a relative photo path, an absolute http(s) URL, or null.
     */
    function teacherPhotoPath(int $id, ?string $photo = null): ?string
    {
        $root = dirname(__DIR__);
        $photo = trim((string)$photo);
        if ($photo !== '') {
            if (preg_match('#^https?://#i', $photo)) {
                return $photo;
            }
            $photo = ltrim(str_replace('\\', '/', $photo), '/');
            if (is_file($root . '/' . $photo)) {
                return $photo;
            }
            $filename = basename($photo);
            foreach ([
                'uploads/teachers/' . $filename,
                'uploads/teacher/' . $filename,
                'assets/uploads/teachers/' . $filename,
                'assets/images/teachers/' . $filename,
                'uploads/' . $filename,
            ] as $path) {
                if (is_file($root . '/' . $path)) {
                    return $path;
                }
            }
        }

        foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $ext) {
            foreach ([
                'assets/images/teachers/' . $id . '.' . $ext,
                'uploads/teachers/' . $id . '.' . $ext,
            ] as $relative) {
                if (is_file($root . '/' . $relative)) {
                    return $relative;
                }
            }
        }

        return null;
    }
}

if (!function_exists('teacherPhotoSrc')) {
    function teacherPhotoSrc(?string $path): string
    {
        $path = trim((string)$path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $base = defined('BASE_URL') ? (string)BASE_URL : '/';
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('parseSubjects')) {
    function parseSubjects(mixed $subjects): array
    {
        if (is_array($subjects)) {
            return array_values(array_filter(array_map('trim', $subjects)));
        }
        return array_values(array_filter(array_map('trim', explode(',', (string)$subjects))));
    }
}

if (!function_exists('student_flash')) {
    function student_flash(string $key): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $value = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);
        return is_string($value) ? $value : null;
    }
}

if (!function_exists('student_social_networks')) {
    /**
     * @return array<string, array{label:string,icon:string,placeholder:string}>
     */
    function student_social_networks(): array
    {
        return [
            'website' => [
                'label' => 'Website',
                'icon' => 'bi-globe',
                'placeholder' => 'https://your-site.com',
            ],
            'facebook' => [
                'label' => 'Facebook',
                'icon' => 'bi-facebook',
                'placeholder' => 'https://facebook.com/username',
            ],
            'instagram' => [
                'label' => 'Instagram',
                'icon' => 'bi-instagram',
                'placeholder' => 'https://instagram.com/username',
            ],
            'tiktok' => [
                'label' => 'TikTok',
                'icon' => 'bi-tiktok',
                'placeholder' => 'https://tiktok.com/@username',
            ],
            'youtube' => [
                'label' => 'YouTube',
                'icon' => 'bi-youtube',
                'placeholder' => 'https://youtube.com/@username',
            ],
            'linkedin' => [
                'label' => 'LinkedIn',
                'icon' => 'bi-linkedin',
                'placeholder' => 'https://linkedin.com/in/username',
            ],
        ];
    }
}

if (!function_exists('student_normalize_social_url')) {
    function student_normalize_social_url(string $raw, string $network): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $raw = preg_replace('/\s+/', '', $raw) ?? $raw;
        if (strlen($raw) > 255) {
            throw new RuntimeException('That link is too long.');
        }

        $hosts = [
            'facebook' => ['facebook.com', 'fb.com', 'fb.me'],
            'instagram' => ['instagram.com', 'instagr.am'],
            'tiktok' => ['tiktok.com'],
            'youtube' => ['youtube.com', 'youtu.be'],
            'linkedin' => ['linkedin.com'],
            'website' => [],
        ];
        $prefixes = [
            'facebook' => 'https://www.facebook.com/',
            'instagram' => 'https://www.instagram.com/',
            'tiktok' => 'https://www.tiktok.com/@',
            'youtube' => 'https://www.youtube.com/@',
            'linkedin' => 'https://www.linkedin.com/in/',
            'website' => 'https://',
        ];
        $labels = student_social_networks();
        $label = $labels[$network]['label'] ?? $network;

        if (!preg_match('#^https?://#i', $raw)) {
            $handle = ltrim($raw, '@/');
            if ($network === 'tiktok') {
                $handle = ltrim($handle, '@');
            }
            $raw = ($prefixes[$network] ?? 'https://') . $handle;
        }

        if (filter_var($raw, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Enter a valid ' . $label . ' link or username.');
        }
        $parts = parse_url($raw);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException($label . ' links must start with https://');
        }
        $host = strtolower((string)($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $allowed = $hosts[$network] ?? [];
        if ($allowed !== []) {
            $ok = false;
            foreach ($allowed as $allowedHost) {
                if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                throw new RuntimeException('That does not look like a ' . $label . ' link.');
            }
        }

        return $raw;
    }
}

if (!function_exists('student_social_icon_links')) {
    /**
     * @param array<string, mixed> $profile
     * @return list<array{key:string,url:string,icon:string,label:string}>
     */
    function student_social_icon_links(array $profile): array
    {
        $out = [];
        foreach (student_social_networks() as $key => $meta) {
            $url = trim((string)($profile[$key] ?? ''));
            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                $out[] = [
                    'key' => $key,
                    'url' => $url,
                    'icon' => $meta['icon'],
                    'label' => $meta['label'],
                ];
            }
        }
        return $out;
    }
}
