<?php
declare(strict_types=1);

/**
 * Canonical public brand name. Legacy DB/env values are normalized.
 */
function college_brand_name(string $name = ''): string
{
    $name = trim($name);
    $legacy = [
        'Edexcel College Kandy',
        'Kandy Edexcel College',
        'Edexcel Timetable',
    ];
    if ($name === '' || in_array($name, $legacy, true)) {
        return 'Edexcel College';
    }
    return $name;
}

/**
 * Public college contact details for the homepage, legal pages, and OnePay review.
 *
 * @return array{
 *   name:string,
 *   email:string,
 *   phone:string,
 *   phone_tel:string,
 *   whatsapp:string,
 *   address:string,
 *   address_lines:list<string>,
 *   maps_url:string,
 *   maps_embed_url:string,
 *   hours:string,
 *   city:string
 * }
 */
function college_contact(?PDO $pdo = null): array
{
    static $cache = null;
    if (is_array($cache) && $pdo === null) {
        return $cache;
    }

    $hotline = preg_replace('/\D+/', '', (string)(getenv('HOTLINE_NUMBER') ?: '94785858585')) ?? '';
    if (str_starts_with($hotline, '0') && strlen($hotline) === 10) {
        $hotline = '94' . substr($hotline, 1);
    }
    if ($hotline === '') {
        $hotline = '94785858585';
    }

    $defaultEmail = 'info@edexcel.college';
    $defaultAddress = "No 83 Katugatota Road, Kandy\nSri Lanka";
    $staleEmails = ['info@edexcel.lk'];
    $staleAddresses = [
        "Edexcel College\nKandy, Central Province\nSri Lanka",
        'Edexcel College, Kandy, Central Province, Sri Lanka',
        'Kandy, Sri Lanka',
    ];

    $contact = [
        'name' => college_brand_name(),
        'email' => $defaultEmail,
        'phone' => college_format_phone_display($hotline),
        'phone_tel' => '+' . $hotline,
        'whatsapp' => $hotline,
        'address' => $defaultAddress,
        'maps_url' => 'https://maps.app.goo.gl/1FJE2mQ5eR1HijsbA',
        'maps_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3957.45!2d80.6346098!3d7.3050896!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae367d64dd20f49%3A0x3539c01e3bc33f01!2s83%20Katugastota%20Rd%2C%20Kandy!5e0!3m2!1sen!2slk!4v1720000000000!5m2!1sen!2slk',
        'hours' => 'Monday–Friday 8:00–18:00 · Saturday 8:00–14:00',
        'city' => 'Kandy, Sri Lanka',
    ];

    if ($pdo instanceof PDO) {
        try {
            $keys = [
                'institute_name',
                'contact_email',
                'contact_phone',
                'contact_address',
                'contact_hours',
            ];
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
            $stmt->execute($keys);
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
            $rawName = trim((string)($rows['institute_name'] ?? ''));
            $name = college_brand_name($rawName);
            $contact['name'] = $name;
            if ($rawName !== '' && $rawName !== $name && function_exists('ops_save_setting')) {
                try {
                    ops_save_setting($pdo, 'institute_name', $name);
                } catch (Throwable $e) {
                    // Non-fatal: public pages still show the canonical brand.
                }
            }
            $email = trim((string)($rows['contact_email'] ?? ''));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && !in_array(strtolower($email), $staleEmails, true)) {
                $contact['email'] = $email;
            }
            $phone = trim((string)($rows['contact_phone'] ?? ''));
            if ($phone !== '') {
                $digits = preg_replace('/\D+/', '', $phone) ?? '';
                if (str_starts_with($digits, '0') && strlen($digits) === 10) {
                    $digits = '94' . substr($digits, 1);
                }
                if ($digits !== '') {
                    $contact['phone'] = college_format_phone_display($digits);
                    $contact['phone_tel'] = '+' . $digits;
                    $contact['whatsapp'] = $digits;
                }
            }
            $address = trim((string)($rows['contact_address'] ?? ''));
            if ($address !== '' && !in_array($address, $staleAddresses, true)) {
                $contact['address'] = $address;
            }
            $hours = trim((string)($rows['contact_hours'] ?? ''));
            if ($hours !== '') {
                $contact['hours'] = $hours;
            }
        } catch (Throwable $e) {
            // Public pages still render with defaults if settings are unavailable.
        }
    }

    $contact['address_lines'] = preg_split('/\r\n|\r|\n/', trim($contact['address'])) ?: [$contact['address']];
    $cache = $contact;
    return $contact;
}

function college_format_phone_display(string $digits): string
{
    $digits = preg_replace('/\D+/', '', $digits) ?? '';
    if (str_starts_with($digits, '94') && strlen($digits) === 11) {
        return '+94 ' . substr($digits, 2, 2) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7);
    }
    return $digits !== '' ? '+' . $digits : '';
}

function college_public_base(): string
{
    if (defined('APP_URL')) {
        $base = rtrim((string)APP_URL, '/');
        if ($base !== '' && $base !== '/') {
            return $base;
        }
    }
    return 'https://edexcel.college';
}

/**
 * @return list<array{href:string,label:string}>
 */
function college_legal_links(): array
{
    $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/');
    return [
        ['href' => $base . '/about', 'label' => 'About'],
        ['href' => $base . '/pearson-exams', 'label' => 'Pearson exams'],
        ['href' => $base . '/edexcel-classes', 'label' => 'Edexcel Classes'],
        ['href' => $base . '/faq', 'label' => 'FAQ'],
        ['href' => $base . '/contact', 'label' => 'Contact'],
        ['href' => $base . '/terms', 'label' => 'Terms and Conditions'],
        ['href' => $base . '/privacy-policy', 'label' => 'Privacy Policy'],
        ['href' => $base . '/refund-policy', 'label' => 'Refund Policy'],
    ];
}
