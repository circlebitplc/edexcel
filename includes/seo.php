<?php
declare(strict_types=1);

/**
 * Shared public SEO helpers (meta, Open Graph, Twitter, JSON-LD).
 * Uses only college_contact / known public facts — do not invent NAP data.
 */

if (!function_exists('college_public_base')) {
    require_once __DIR__ . '/college_contact.php';
}
require_once __DIR__ . '/seo_content.php';

/**
 * Absolute public site origin (no trailing slash).
 */
function seo_base_url(): string
{
    return rtrim(college_public_base(), '/');
}

/**
 * Absolute URL for a site path (leading slash optional).
 */
function seo_absolute_url(string $path = '/'): string
{
    $path = trim($path);
    if ($path === '' || $path === '/') {
        return seo_base_url() . '/';
    }
    return seo_base_url() . '/' . ltrim($path, '/');
}

/**
 * Default social / schema image (PWA icon).
 */
function seo_default_image_url(): string
{
    return seo_absolute_url('assets/icons/icon-512.png');
}

/**
 * @param PDO|null $pdo
 * @return array<string, mixed>
 */
function seo_contact(?PDO $pdo = null): array
{
    return college_contact($pdo);
}

/**
 * Render standard public <head> meta block (caller owns DOCTYPE/html/head open).
 *
 * @param array{
 *   title?:string,
 *   description?:string,
 *   canonical?:string,
 *   robots?:string,
 *   og_type?:string,
 *   og_image?:string,
 *   image_alt?:string,
 *   geo_region?:string,
 *   geo_placename?:string,
 *   og_locale?:string
 * } $opts
 */
function seo_render_meta(array $opts): void
{
    $pdo = ($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null;
    $contact = seo_contact($pdo);
    $siteName = (string)($contact['name'] ?? 'Edexcel College');
    $title = trim((string)($opts['title'] ?? $siteName));
    $description = trim((string)($opts['description'] ?? ''));
    if ($description === '') {
        $description = function_exists('seo_public_positioning_short')
            ? seo_public_positioning_short()
            : $siteName . ' provides live online Pearson Edexcel IGCSE and International A Level classes for students worldwide, with physical classes at the Kandy campus in Sri Lanka.';
    }
    $canonical = trim((string)($opts['canonical'] ?? seo_absolute_url('/')));
    $robots = trim((string)($opts['robots'] ?? 'index, follow'));
    $ogType = trim((string)($opts['og_type'] ?? 'website'));
    $ogImage = trim((string)($opts['og_image'] ?? seo_default_image_url()));
    $imageAlt = trim((string)($opts['image_alt'] ?? $siteName));
    $geoRegion = trim((string)($opts['geo_region'] ?? ''));
    $geoPlace = trim((string)($opts['geo_placename'] ?? ''));
    $ogLocale = trim((string)($opts['og_locale'] ?? 'en_LK'));
    $fullTitle = $title;
    if ($title !== $siteName && !str_contains($title, $siteName)) {
        $fullTitle = $title . ' | ' . $siteName;
    }
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta name="author" content="<?= e($siteName) ?>">
    <?php if ($geoRegion !== ''): ?>
    <meta name="geo.region" content="<?= e($geoRegion) ?>">
    <?php endif; ?>
    <?php if ($geoPlace !== ''): ?>
    <meta name="geo.placename" content="<?= e($geoPlace) ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:locale" content="<?= e($ogLocale) ?>">
    <meta property="og:type" content="<?= e($ogType) ?>">
    <meta property="og:title" content="<?= e($fullTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:image:alt" content="<?= e($imageAlt) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($fullTitle) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">
    <link rel="icon" href="<?= e(seo_absolute_url('favicon.ico')) ?>">
    <link rel="apple-touch-icon" href="<?= e(seo_absolute_url('assets/icons/icon-192.png')) ?>">
    <?php
    $ga = trim((string)(getenv('GA_MEASUREMENT_ID') ?: ''));
    if ($ga !== '' && preg_match('/^G-[A-Z0-9]+$/', $ga)):
        ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', <?= json_encode($ga) ?>, { anonymize_ip: true });
    </script>
        <?php
    endif;
}

/**
 * EducationalOrganization + LocalBusiness-compatible JSON-LD graph node.
 *
 * @param PDO|null $pdo
 * @return array<string, mixed>
 */
function seo_organization_schema(?PDO $pdo = null): array
{
    $c = seo_contact($pdo);
    $base = seo_base_url() . '/';
    $street = implode(', ', $c['address_lines'] ?? [trim((string)($c['address'] ?? ''))]);
    $hoursSpec = seo_opening_hours_spec((string)($c['hours'] ?? ''));

    $org = [
        '@type' => ['EducationalOrganization', 'LocalBusiness'],
        '@id' => $base . '#organization',
        'name' => (string)$c['name'],
        'url' => $base,
        'email' => (string)$c['email'],
        'telephone' => (string)$c['phone_tel'],
        'image' => seo_default_image_url(),
        'logo' => seo_default_image_url(),
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $street,
            'addressLocality' => 'Kandy',
            'addressRegion' => 'Central Province',
            'postalCode' => '20000',
            'addressCountry' => 'LK',
        ],
        'geo' => [
            '@type' => 'GeoCoordinates',
            'latitude' => 7.3050896,
            'longitude' => 80.6346098,
        ],
        'hasMap' => (string)($c['maps_url'] ?? ''),
        'areaServed' => array_merge(
            [
                [
                    '@type' => 'AdministrativeArea',
                    'name' => 'Worldwide',
                    'description' => 'Live online classes. Not a claim of physical campuses outside Sri Lanka.',
                ],
            ],
            array_map(
                static fn(array $m): array => ['@type' => 'Country', 'name' => (string)$m['name']],
                seo_service_markets()
            )
        ),
        'sameAs' => array_values(array_filter([
            (string)($c['maps_url'] ?? ''),
        ])),
        'description' => function_exists('seo_public_positioning') ? seo_public_positioning() : (string)$c['name'],
        'knowsAbout' => [
            'Pearson Edexcel',
            'International GCSE',
            'International A Level',
            'Exam preparation',
        ],
        'currenciesAccepted' => 'LKR',
    ];
    if ($hoursSpec !== []) {
        $org['openingHoursSpecification'] = $hoursSpec;
    }
    return $org;
}

/**
 * Parse college hours string into schema OpeningHoursSpecification list when possible.
 *
 * @return list<array<string, mixed>>
 */
function seo_opening_hours_spec(string $hours): array
{
    // Expected default: "Monday–Friday 8:00–18:00 · Saturday 8:00–14:00"
    $specs = [];
    if (preg_match('/Monday[–-]Friday\s+(\d{1,2}:\d{2})[–-](\d{1,2}:\d{2})/u', $hours, $m)) {
        $specs[] = [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'opens' => $m[1],
            'closes' => $m[2],
        ];
    }
    if (preg_match('/Saturday\s+(\d{1,2}:\d{2})[–-](\d{1,2}:\d{2})/u', $hours, $m)) {
        $specs[] = [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => 'Saturday',
            'opens' => $m[1],
            'closes' => $m[2],
        ];
    }
    return $specs;
}

/**
 * @return array<string, mixed>
 */
function seo_website_schema(?PDO $pdo = null): array
{
    $c = seo_contact($pdo);
    $base = seo_base_url() . '/';
    return [
        '@type' => 'WebSite',
        '@id' => $base . '#website',
        'url' => $base,
        'name' => (string)$c['name'],
        'description' => function_exists('seo_public_positioning_short') ? seo_public_positioning_short() : (string)$c['name'],
        'publisher' => ['@id' => $base . '#organization'],
        'inLanguage' => 'en',
    ];
}

/**
 * @param array{name:string,description:string,url:string,area?:string} $service
 * @return array<string, mixed>
 */
function seo_service_schema(array $service, ?PDO $pdo = null): array
{
    $base = seo_base_url() . '/';
    $node = [
        '@type' => 'Service',
        'name' => (string)$service['name'],
        'description' => (string)$service['description'],
        'url' => (string)$service['url'],
        'provider' => ['@id' => $base . '#organization'],
        'serviceType' => 'Education',
        'areaServed' => (string)($service['area'] ?? 'International'),
    ];
    return $node;
}

/**
 * @param list<array{name:string,url:string}> $items
 * @return array<string, mixed>
 */
function seo_breadcrumb_schema(array $items): array
{
    $list = [];
    $pos = 1;
    foreach ($items as $item) {
        $list[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'name' => (string)$item['name'],
            'item' => (string)$item['url'],
        ];
    }
    return [
        '@type' => 'BreadcrumbList',
        'itemListElement' => $list,
    ];
}

/**
 * @param array{
 *   name:string,
 *   description:string,
 *   url:string,
 *   educationalLevel?:string,
 *   about?:string
 * } $course
 * @return array<string, mixed>
 */
function seo_course_schema(array $course, ?PDO $pdo = null): array
{
    $base = seo_base_url() . '/';
    $node = [
        '@type' => 'Course',
        'name' => (string)$course['name'],
        'description' => (string)$course['description'],
        'url' => (string)$course['url'],
        'provider' => ['@id' => $base . '#organization'],
        'inLanguage' => 'en',
        'courseMode' => ['Online', 'Onsite'],
    ];
    if (!empty($course['educationalLevel'])) {
        $node['educationalLevel'] = (string)$course['educationalLevel'];
    }
    if (!empty($course['about'])) {
        $node['about'] = (string)$course['about'];
    }
    return $node;
}

/**
 * @param list<array{question:string,answer:string}> $faqs
 * @return array<string, mixed>|null
 */
function seo_faq_schema(array $faqs): ?array
{
    if ($faqs === []) {
        return null;
    }
    $entities = [];
    foreach ($faqs as $faq) {
        $q = trim((string)($faq['question'] ?? ''));
        $a = trim((string)($faq['answer'] ?? ''));
        if ($q === '' || $a === '') {
            continue;
        }
        $entities[] = [
            '@type' => 'Question',
            'name' => $q,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $a,
            ],
        ];
    }
    if ($entities === []) {
        return null;
    }
    return [
        '@type' => 'FAQPage',
        'mainEntity' => $entities,
    ];
}

/**
 * Priority teachers for public SEO / E-E-A-T internal linking.
 * IDs match the production `teachers` table.
 *
 * @return list<array{id:int,name:string,subjects:list<string>,focus:string}>
 */
function seo_featured_teachers(): array
{
    return [
        [
            'id' => 14,
            'name' => 'Enidu Batuwanthudawe',
            'subjects' => ['ICT', 'Computer Science'],
            'focus' => 'ICT and Computer Science',
        ],
        [
            'id' => 1,
            'name' => 'Kasun Batuwanthudawa',
            'subjects' => ['Mathematics'],
            'focus' => 'Mathematics',
        ],
        [
            'id' => 7,
            'name' => 'Mahesh Wijesekara',
            'subjects' => ['Chemistry'],
            'focus' => 'Chemistry',
        ],
        [
            'id' => 5,
            'name' => 'Dinuka Dissanayake',
            'subjects' => ['Physics'],
            'focus' => 'Physics',
        ],
        [
            'id' => 9,
            'name' => 'Asanka Illangakoon',
            'subjects' => ['Economics', 'Business'],
            'focus' => 'Economics and Business',
        ],
    ];
}

/**
 * Public profile URL for a teacher id.
 */
function seo_teacher_profile_url(int $teacherId): string
{
    return seo_absolute_url('teachers/teacher_profile.php?id=' . max(0, $teacherId));
}

/**
 * Join subject names for natural copy (Oxford-style "and").
 *
 * @param list<string> $subjectNames
 */
function seo_teacher_subject_label(array $subjectNames): string
{
    $names = [];
    foreach ($subjectNames as $name) {
        $name = trim((string)$name);
        if ($name !== '' && !in_array($name, $names, true)) {
            $names[] = $name;
        }
    }
    $count = count($names);
    if ($count === 0) {
        return '';
    }
    if ($count === 1) {
        return $names[0];
    }
    if ($count === 2) {
        return $names[0] . ' and ' . $names[1];
    }
    $last = array_pop($names);
    return implode(', ', $names) . ', and ' . $last;
}

/**
 * Subject-aware public title (site name appended by callers / header as needed).
 *
 * @param list<string> $subjectNames
 */
function seo_teacher_page_title(string $name, array $subjectNames): string
{
    $name = trim($name);
    $label = seo_teacher_subject_label($subjectNames);
    if ($label !== '') {
        return $name . ' — ' . $label . ' Teacher | Edexcel College';
    }
    return $name . ' | Edexcel Teacher | Edexcel College';
}

/**
 * Natural meta description for a teacher profile (no keyword stuffing).
 *
 * @param list<string> $subjectNames
 */
function seo_teacher_meta_description(string $name, array $subjectNames, ?int $experienceYears = null): string
{
    $name = trim($name);
    $label = seo_teacher_subject_label($subjectNames);
    $parts = [];
    if ($label !== '') {
        $parts[] = $name . ' teaches Edexcel ' . $label . ' online for students worldwide, and on the Kandy campus when that class is scheduled in Sri Lanka.';
    } else {
        $parts[] = $name . ' teaches live online Edexcel classes for students worldwide, with physical classes at the Kandy campus when scheduled.';
    }
    $parts[] = 'Pearson Edexcel IGCSE and International A Level (IAL).';
    if ($experienceYears !== null && $experienceYears > 0) {
        $parts[] = $experienceYears . '+ years of teaching experience.';
    }
    $description = implode(' ', $parts);
    if (strlen($description) > 160) {
        $description = rtrim(substr($description, 0, 157)) . '…';
    }
    return $description;
}

/**
 * Accessible, SEO-friendly alt text for teacher photographs.
 *
 * @param list<string> $subjectNames
 */
function seo_teacher_photo_alt(string $name, array $subjectNames): string
{
    $name = trim($name);
    $label = seo_teacher_subject_label($subjectNames);
    if ($label !== '') {
        return $name . ', ' . $label . ' teacher at Edexcel College';
    }
    return $name . ', teacher at Edexcel College';
}

/**
 * Fallback intro when a teacher has no stored bio (visible, factual, non-duplicative).
 *
 * @param list<string> $subjectNames
 */
function seo_teacher_fallback_intro(string $name, array $subjectNames): string
{
    $name = trim($name);
    $label = seo_teacher_subject_label($subjectNames);
    if ($label !== '') {
        return $name . ' teaches ' . $label . ' for Pearson Edexcel IGCSE and International A Level (IAL). Online classes are available worldwide. Physical classes are at the Kandy campus in Sri Lanka only when this teacher is scheduled there. View this week’s classes on the timetable below.';
    }
    return $name . ' teaches at Edexcel College, supporting Pearson Edexcel IGCSE and International A Level (IAL) students. View this week’s classes on the timetable below.';
}

/**
 * Person JSON-LD for a public teacher profile.
 *
 * @param array{
 *   id:int|string,
 *   name:string,
 *   url:string,
 *   description?:string,
 *   image?:string,
 *   email?:string,
 *   jobTitle?:string,
 *   subjects?:list<string>,
 *   sameAs?:list<string>,
 *   experienceYears?:int|null
 * } $person
 * @return array<string, mixed>
 */
function seo_person_schema(array $person, ?PDO $pdo = null): array
{
    $base = seo_base_url() . '/';
    $id = (int)($person['id'] ?? 0);
    $name = trim((string)($person['name'] ?? ''));
    $url = trim((string)($person['url'] ?? ''));
    $subjects = [];
    foreach (($person['subjects'] ?? []) as $subject) {
        $subject = trim((string)$subject);
        if ($subject !== '') {
            $subjects[] = $subject;
        }
    }
    $jobTitle = trim((string)($person['jobTitle'] ?? ''));
    if ($jobTitle === '') {
        $label = seo_teacher_subject_label($subjects);
        $jobTitle = $label !== ''
            ? $label . ' Teacher'
            : 'Edexcel Teacher';
    }

    $node = [
        '@type' => 'Person',
        '@id' => $url !== '' ? ($url . '#person') : ($base . 'teachers/#person-' . $id),
        'name' => $name,
        'url' => $url,
        'jobTitle' => $jobTitle,
        'worksFor' => ['@id' => $base . '#organization'],
        'affiliation' => ['@id' => $base . '#organization'],
    ];

    $description = trim((string)($person['description'] ?? ''));
    if ($description !== '') {
        $node['description'] = $description;
    }
    $image = trim((string)($person['image'] ?? ''));
    if ($image !== '') {
        $node['image'] = $image;
    }
    $email = trim((string)($person['email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $node['email'] = $email;
    }
    if ($subjects !== []) {
        $node['knowsAbout'] = array_values(array_unique($subjects));
    }
    $sameAs = array_values(array_filter(array_map(
        static fn($u): string => trim((string)$u),
        $person['sameAs'] ?? []
    )));
    if ($sameAs !== []) {
        $node['sameAs'] = $sameAs;
    }

    return $node;
}

/**
 * ProfilePage wrapper pointing at a Person node.
 *
 * @return array<string, mixed>
 */
function seo_profile_page_schema(string $url, string $personId, string $name, string $description = ''): array
{
    $node = [
        '@type' => 'ProfilePage',
        '@id' => $url . '#profilepage',
        'url' => $url,
        'name' => $name,
        'mainEntity' => ['@id' => $personId],
        'isPartOf' => ['@id' => seo_base_url() . '/#website'],
        'about' => ['@id' => $personId],
    ];
    $description = trim($description);
    if ($description !== '') {
        $node['description'] = $description;
    }
    return $node;
}

/**
 * ItemList of teachers for the public directory.
 *
 * @param list<array{name:string,url:string}> $items
 * @return array<string, mixed>
 */
function seo_teacher_itemlist_schema(array $items): array
{
    $list = [];
    $pos = 1;
    foreach ($items as $item) {
        $name = trim((string)($item['name'] ?? ''));
        $url = trim((string)($item['url'] ?? ''));
        if ($name === '' || $url === '') {
            continue;
        }
        $list[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'name' => $name,
            'url' => $url,
            'item' => [
                '@type' => 'Person',
                'name' => $name,
                'url' => $url,
            ],
        ];
    }
    return [
        '@type' => 'ItemList',
        'name' => 'Edexcel College Teachers',
        'itemListElement' => $list,
    ];
}

/**
 * Visible featured-teacher links for marketing / pathway pages.
 */
function seo_render_featured_teachers_section(?string $heading = null): void
{
    $teachers = seo_featured_teachers();
    if ($teachers === []) {
        return;
    }
    $heading = trim((string)($heading ?? 'Meet specialist Edexcel teachers'));
    ?>
    <h2><?= e($heading) ?></h2>
    <p>Families often search for these Edexcel College teachers by name and subject. Open a profile for teaching areas, qualifications where published, and this week’s timetable.</p>
    <ul>
        <?php foreach ($teachers as $teacher): ?>
            <li>
                <a href="<?= e(seo_teacher_profile_url((int)$teacher['id'])) ?>">
                    <?= e((string)$teacher['name']) ?>
                </a>
                — <?= e((string)$teacher['focus']) ?> (Pearson Edexcel IGCSE / IAL)
            </li>
        <?php endforeach; ?>
    </ul>
    <p>Browse the full directory on the <a href="<?= e(seo_absolute_url('teachers/')) ?>">Edexcel teachers</a> page.</p>
    <?php
}

/**
 * Emit a JSON-LD script for a @graph or single node.
 *
 * @param list<array<string, mixed>>|array<string, mixed> $data
 */
function seo_print_jsonld($data): void
{
    if ($data === [] || $data === null) {
        return;
    }
    if (isset($data[0]) && is_array($data[0])) {
        $payload = [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter($data)),
        ];
    } elseif (isset($data['@context'])) {
        $payload = $data;
    } else {
        $payload = ['@context' => 'https://schema.org'] + $data;
    }
    echo '<script type="application/ld+json">'
        . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
        . '</script>' . "\n";
}

/**
 * Homepage FAQ content grounded in documented college offerings.
 *
 * @return list<array{question:string,answer:string}>
 */
function seo_homepage_faqs(?PDO $pdo = null): array
{
    $keep = [
        'Does Edexcel College offer online classes?',
        'Can students outside Sri Lanka join Edexcel College?',
        'Is Edexcel College only for students in Sri Lanka?',
        'Does Edexcel College have physical classes?',
        'Are the classes live or recorded?',
        'Can a Sri Lankan student attend online classes?',
        'How do online students join the classroom?',
        'How does the online timetable work across time zones?',
    ];
    $out = [];
    foreach (seo_public_faqs($pdo) as $item) {
        if (in_array((string)($item['question'] ?? ''), $keep, true)) {
            $out[] = $item;
        }
    }
    return $out;
}

/**
 * Public subject pages built only from teachers already named for the public site.
 *
 * @return array<string, array{slug:string,name:string,teachers:list<array{id:int,name:string}>}>
 */
function seo_public_subject_pages(): array
{
    $pages = [];
    foreach (seo_featured_teachers() as $teacher) {
        foreach ($teacher['subjects'] as $name) {
            $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', (string)$name), '-'));
            if ($slug === '') {
                continue;
            }
            if (!isset($pages[$slug])) {
                $pages[$slug] = ['slug' => $slug, 'name' => (string)$name, 'teachers' => []];
            }
            $pages[$slug]['teachers'][] = [
                'id' => (int)$teacher['id'],
                'name' => (string)$teacher['name'],
            ];
        }
    }
    return $pages;
}

/**
 * @param array{
 *   headline:string,
 *   description:string,
 *   url:string,
 *   datePublished:string,
 *   dateModified?:string,
 *   authorName?:string
 * } $article
 * @return array<string, mixed>
 */
function seo_article_schema(array $article, ?PDO $pdo = null): array
{
    $base = seo_base_url() . '/';
    $published = (string)$article['datePublished'];
    $modified = (string)($article['dateModified'] ?? $published);
    $author = trim((string)($article['authorName'] ?? 'Edexcel College Admissions Team'));
    return [
        '@type' => 'Article',
        'headline' => (string)$article['headline'],
        'description' => (string)$article['description'],
        'url' => (string)$article['url'],
        'mainEntityOfPage' => (string)$article['url'],
        'datePublished' => $published,
        'dateModified' => $modified,
        'inLanguage' => 'en',
        'author' => [
            '@type' => 'Organization',
            'name' => $author,
            'url' => $base,
        ],
        'publisher' => ['@id' => $base . '#organization'],
        'image' => seo_default_image_url(),
    ];
}

/**
 * Visible byline for guides (publication + review freshness).
 */
function seo_render_byline(string $published, string $updated, string $author = 'Edexcel College Admissions Team'): void
{
    ?>
    <p class="seo-byline">
        <span>Written by <?= e($author) ?></span>
        · <span>Published <?= e($published) ?></span>
        · <span>Last reviewed <?= e($updated) ?></span>
    </p>
    <?php
}

/**
 * Short answer block for AI/search extraction (visible content only).
 */
function seo_render_answer(string $label, string $text): void
{
    ?>
    <div class="seo-answer">
        <p><strong><?= e($label) ?></strong> <?= e($text) ?></p>
    </div>
    <?php
}

/**
 * Render visible affiliation disclaimer box.
 */
function seo_render_disclaimer_box(): void
{
    ?>
    <aside class="seo-note" role="note">
        <strong>Independent college.</strong>
        <?= e(seo_affiliation_disclaimer()) ?>
        Official Pearson information:
        <a href="https://qualifications.pearson.com/" rel="noopener noreferrer" target="_blank">qualifications.pearson.com</a>.
    </aside>
    <?php
}
