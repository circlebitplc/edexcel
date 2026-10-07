<?php
declare(strict_types=1);

/**
 * Public SEO content: service areas, FAQs, affiliation disclaimer.
 * Keep claims verifiable — campus is Kandy; other markets are online/remote tuition support.
 */

/**
 * Independent-college disclaimer (visible + schema-safe wording).
 */
function seo_affiliation_disclaimer(): string
{
    return 'Edexcel College is an independent tuition college. '
        . 'We are not Pearson Education Ltd, Pearson Edexcel, or an official Pearson examination centre. '
        . 'We teach students preparing for Pearson Edexcel IGCSE and International A Level qualifications. '
        . 'Official exam entries, results, and certificates are managed by Pearson and authorised examination centres.';
}

/**
 * Public positioning used by pages, meta descriptions, and structured data.
 * Around 90% online is the institute's current approximate distribution.
 * The only verified physical campus is Kandy.
 */
function seo_public_positioning(): string
{
    return 'Edexcel College is an education provider specializing in Pearson Edexcel IGCSE and International A Level programmes. '
        . 'Around 90% of its classes are delivered online, enabling students from around the world to attend live classes remotely. '
        . 'Physical classes are also available at the Kandy campus in Sri Lanka, depending on the subject, teacher, schedule, and availability.';
}

function seo_public_positioning_short(): string
{
    return 'Around 90% of Edexcel College classes are live online for students worldwide. '
        . 'Physical classes are available at the Kandy campus in Sri Lanka when the subject, teacher, and timetable offer them.';
}

/**
 * Markets we publicly discuss (online tuition / exam prep support).
 *
 * @return list<array{name:string,code:string,slug?:string}>
 */
function seo_service_markets(): array
{
    return [
        ['name' => 'Sri Lanka', 'code' => 'LK', 'slug' => 'sri-lanka'],
        ['name' => 'Italy', 'code' => 'IT', 'slug' => 'italy'],
        ['name' => 'Qatar', 'code' => 'QA', 'slug' => 'qatar'],
        ['name' => 'Oman', 'code' => 'OM', 'slug' => 'oman'],
        ['name' => 'United Arab Emirates', 'code' => 'AE', 'slug' => 'uae'],
        ['name' => 'Maldives', 'code' => 'MV', 'slug' => 'maldives'],
        ['name' => 'United Kingdom', 'code' => 'GB', 'slug' => 'united-kingdom'],
        ['name' => 'India', 'code' => 'IN', 'slug' => 'india'],
        ['name' => 'Saudi Arabia', 'code' => 'SA', 'slug' => null],
        ['name' => 'Bahrain', 'code' => 'BH', 'slug' => null],
        ['name' => 'Kuwait', 'code' => 'KW', 'slug' => null],
    ];
}

/**
 * Flattened FAQ list for schema and legacy callers.
 *
 * @return list<array{question:string,answer:string}>
 */
function seo_public_faqs(?PDO $pdo = null): array
{
    $out = [];
    foreach (seo_faq_categories($pdo) as $cat) {
        foreach ($cat['items'] as $item) {
            $out[] = $item;
        }
    }
    return $out;
}

/**
 * Categorised FAQ knowledge base.
 *
 * @return list<array{id:string,label:string,items:list<array{question:string,answer:string}>}>
 */
function seo_faq_categories(?PDO $pdo = null): array
{
    $c = function_exists('seo_contact') ? seo_contact($pdo) : [
        'name' => 'Edexcel College',
        'address' => 'No 83 Katugatota Road, Kandy, Sri Lanka',
        'hours' => 'Monday–Friday 8:00–18:00 · Saturday 8:00–14:00',
        'email' => 'info@edexcel.college',
        'phone' => '+94 78 585 8585',
    ];
    $name = (string)$c['name'];
    $address = preg_replace('/\s+/', ' ', trim((string)($c['address'] ?? ''))) ?? '';

    return [
        [
            'id' => 'general',
            'label' => 'General',
            'items' => [
                [
                    'question' => 'What is Edexcel College?',
                    'answer' => 'Edexcel College teaches Pearson Edexcel International GCSE (IGCSE) and International A Level (IAL). Around 90% of classes are live online for students worldwide. Physical classes are at the Kandy campus in Sri Lanka when a subject and teacher are scheduled there. The college is not Pearson and is not an examination centre.',
                ],
                [
                    'question' => 'Does Edexcel College offer online classes?',
                    'answer' => 'Yes. Edexcel College provides live online Pearson Edexcel IGCSE and International A Level classes for students worldwide. Around 90% of the institute\'s classes are delivered online.',
                ],
                [
                    'question' => 'Can students outside Sri Lanka join Edexcel College?',
                    'answer' => 'Yes. Edexcel College primarily provides online classes, so students from different countries can join live lessons remotely. Joining depends on timetable availability, time-zone fit with the published class time, course availability, admission, and a reliable internet connection.',
                ],
                [
                    'question' => 'Is Edexcel College only for students in Sri Lanka?',
                    'answer' => 'No. Most classes are delivered online and are available to students worldwide. Physical classes are also available at the Kandy campus in Sri Lanka where a subject and teacher are scheduled there.',
                ],
                [
                    'question' => 'Does Edexcel College have physical classes?',
                    'answer' => 'Yes. Physical classes run at the Kandy campus, No 83 Katugatota Road, Kandy, Sri Lanka, for selected subjects, teachers, and schedules. There is no campus in Colombo, Kurunegala, or other cities. Students in those places join online or travel to Kandy when a class is on site.',
                ],
                [
                    'question' => 'Are the classes live or recorded?',
                    'answer' => 'Teaching is live. Enrolled students join the online classroom for the scheduled lesson. Some lessons also have recordings in the student portal after class. A recording is not a substitute for checking the timetable.',
                ],
                [
                    'question' => 'Can a Sri Lankan student attend online classes?',
                    'answer' => 'Yes. Online classes are the main way students attend, including students who live in Sri Lanka. A Kandy campus seat is separate and depends on the subject, teacher, and timetable.',
                ],
                [
                    'question' => 'Can international students attend physical classes?',
                    'answer' => 'International students normally attend online. A physical seat is only at the Kandy campus, and only when that subject and teacher have an on-site class. Ask admissions before planning travel.',
                ],
                [
                    'question' => 'How do online students join the classroom?',
                    'answer' => 'After admission and any required lesson payment, the student signs in to the portal and joins the live class from the timetable. The classroom checks enrolment, payment, and the student\'s registered browser before opening the lesson.',
                ],
                [
                    'question' => 'How does the online timetable work across time zones?',
                    'answer' => 'Each lesson has a published date and start time on the college timetable (Asia/Colombo). Online students join that scheduled time. The college does not automatically shift a class to every local time zone.',
                ],
                [
                    'question' => 'Are you Pearson or an official Pearson exam centre?',
                    'answer' => 'No. ' . $name . ' is an independent tuition college. We prepare students for Pearson Edexcel exams; Pearson and authorised centres handle official registration and results.',
                ],
                [
                    'question' => 'What Pearson Edexcel programmes do you teach?',
                    'answer' => 'We focus on Pearson Edexcel International GCSE (IGCSE) and International A Level (IAL) tuition, with exam-oriented teaching and past-paper practice.',
                ],
                [
                    'question' => 'Which Edexcel IGCSE subjects are available?',
                    'answer' => 'The course enquiry lists the subjects currently in the college catalogue. Public teacher profiles name ICT, Computer Science, Mathematics, Chemistry, Physics, Economics, and Business. A catalogue subject is not automatically an IGCSE class this term. Admissions confirms the level against the live timetable.',
                ],
                [
                    'question' => 'Which Edexcel IAL subjects are available?',
                    'answer' => 'Use the course enquiry catalogue as the current subject list. Public teacher profiles name ICT, Computer Science, Mathematics, Chemistry, Physics, Economics, and Business. IAL availability follows the live timetable. This site does not add a level that is not scheduled.',
                ],
                [
                    'question' => 'Which qualifications map to O Level and A Level searches?',
                    'answer' => 'Many families search for “Edexcel O Level” when they mean Pearson Edexcel International GCSE (IGCSE). “Edexcel A Level” usually means Pearson Edexcel International A Level (IAL).',
                ],
            ],
        ],
        [
            'id' => 'registration',
            'label' => 'Registration',
            'items' => [
                [
                    'question' => 'How do I register for a Pearson exam?',
                    'answer' => 'Official exam entries are made through Pearson and authorised examination centres (or school centres). We help students prepare academically and can guide you on typical next steps, but final registration is with Pearson/the centre.',
                ],
                [
                    'question' => 'Can you enter me for exams as a private candidate?',
                    'answer' => 'No. We do not act as a Pearson examination centre. Private candidates normally register through centres such as the British Council or other Pearson-authorised centres that accept private entries in their country.',
                ],
                [
                    'question' => 'Where do I find official exam centres?',
                    'answer' => 'Use Pearson’s official find-a-centre tools on qualifications.pearson.com, and check your local British Council school-exams pages for private-candidate windows. Always confirm subjects and deadlines with the centre you choose.',
                ],
            ],
        ],
        [
            'id' => 'exam-day',
            'label' => 'Exam day',
            'items' => [
                [
                    'question' => 'What should I bring on exam day?',
                    'answer' => 'Typically: valid photo ID accepted by the centre, your statement of entry if issued, and only approved stationery. Leave phones and notes outside. Follow your centre’s candidate instructions exactly.',
                ],
                [
                    'question' => 'Do you run the exam hall?',
                    'answer' => 'No. Exam halls are run by authorised centres. Our role is academic preparation before exam day.',
                ],
            ],
        ],
        [
            'id' => 'results',
            'label' => 'Results & certificates',
            'items' => [
                [
                    'question' => 'How do I get Pearson Edexcel results?',
                    'answer' => 'Results are released through your examination centre / Pearson processes. The centre that entered you issues access details. We cannot publish board results or issue certificates.',
                ],
                [
                    'question' => 'Can you reprint my certificate?',
                    'answer' => 'No. Certificates and official documents come from Pearson via your centre. Contact the centre that entered you for certificate queries.',
                ],
            ],
        ],
        [
            'id' => 'enrolment',
            'label' => 'Classes & enrolment',
            'items' => [
                [
                    'question' => 'How can I enrol for classes?',
                    'answer' => 'Submit a course enquiry or the online admission application, or create a student account. Admissions matches the subject to the live timetable before a place is confirmed.',
                ],
                [
                    'question' => 'How can I contact Edexcel College?',
                    'answer' => 'Email ' . (string)($c['email'] ?? 'info@edexcel.college') . ', phone ' . (string)($c['phone'] ?? '') . ', or use the contact page. The same details cover online students worldwide and the Kandy campus. Office hours: ' . (string)($c['hours'] ?? '') . '.',
                ],
                [
                    'question' => 'Do you offer exam preparation and past papers?',
                    'answer' => 'Yes. Teaching is exam-focused, with past-paper practice and timetable support. Enrolled students can use the student portal for class tools and exam-planner features provided by the college.',
                ],
                [
                    'question' => 'Who teaches ICT, Mathematics, Chemistry, Physics, and Economics?',
                    'answer' => 'Specialist Edexcel teachers include Enidu Batuwanthudawe (ICT & Computer Science), Kasun Batuwanthudawa (Mathematics), Mahesh Wijesekara (Chemistry), Dinuka Dissanayake (Physics), and Asanka Illangakoon (Economics & Business). See each teacher’s public profile for subjects and this week’s timetable.',
                ],
                [
                    'question' => 'Where is the campus?',
                    'answer' => 'The physical campus is at ' . $address . '. Most classes are still live online. Office hours: ' . (string)($c['hours'] ?? '') . '.',
                ],
            ],
        ],
        [
            'id' => 'international',
            'label' => 'Countries & online',
            'items' => [
                [
                    'question' => 'Do you support students outside Sri Lanka?',
                    'answer' => 'Yes. Families in markets such as Italy, Qatar, Oman, the UAE, the Maldives, the UK, India, and the wider Gulf can enquire about online or hybrid tuition. Confirm current delivery options with admissions.',
                ],
                [
                    'question' => 'Do you have offices in Dubai, Doha, or Milan?',
                    'answer' => 'No. Our physical campus is in Kandy, Sri Lanka. International support is delivered online/hybrid. We do not invent branch addresses abroad.',
                ],
            ],
        ],
        [
            'id' => 'payments',
            'label' => 'Payments',
            'items' => [
                [
                    'question' => 'How are class fees paid?',
                    'answer' => 'Class fees for enrolled students may be paid by card via OnePay on this website, by bank transfer with slip verification, or as directed by the office. Exam-centre entry fees are paid to your examination centre, not to us as Pearson.',
                ],
                [
                    'question' => 'Where is the refund policy?',
                    'answer' => 'See our Refund Policy page for class-fee refunds. Centre exam-entry fees follow that centre’s rules.',
                ],
            ],
        ],
    ];
}

/**
 * Educational glossary terms (concise, verifiable definitions).
 *
 * @return list<array{slug:string,term:string,definition:string,see?:string}>
 */
function seo_glossary_terms(): array
{
    return [
        ['slug' => 'pearson', 'term' => 'Pearson', 'definition' => 'Pearson is a global education company. Pearson Edexcel is its UK awarding organisation for many school qualifications.', 'see' => 'https://qualifications.pearson.com/'],
        ['slug' => 'edexcel', 'term' => 'Edexcel', 'definition' => 'Edexcel is the brand name commonly used for Pearson Edexcel qualifications. It is not the same thing as an independent tuition college that uses “Edexcel” in its trading name.'],
        ['slug' => 'igcse', 'term' => 'International GCSE (IGCSE)', 'definition' => 'An internationally offered secondary qualification. Pearson Edexcel International GCSE is often what families mean when they search for “Edexcel O Level”.', 'see' => '/edexcel-o-level'],
        ['slug' => 'gcse', 'term' => 'GCSE', 'definition' => 'General Certificate of Secondary Education — primarily the UK domestic secondary qualification family. International schools often use International GCSE specifications instead.'],
        ['slug' => 'a-level', 'term' => 'A Level', 'definition' => 'A post-16 qualification pathway. Pearson Edexcel International A Level (IAL) is the international modular route many overseas students take.', 'see' => '/edexcel-a-level'],
        ['slug' => 'ial', 'term' => 'International A Level (IAL)', 'definition' => 'Pearson Edexcel’s international A Level pathway, usually taken as modular units (AS/A2-style).'],
        ['slug' => 'qualification', 'term' => 'Qualification', 'definition' => 'A formally assessed award (for example IGCSE Mathematics or an IAL unit) with a published specification and exams.'],
        ['slug' => 'specification', 'term' => 'Specification', 'definition' => 'The official syllabus document describing topics, assessment objectives, and paper structure for a subject.'],
        ['slug' => 'examination-centre', 'term' => 'Examination centre', 'definition' => 'An organisation authorised to enter candidates and host exams. Tuition colleges are not automatically exam centres.'],
        ['slug' => 'private-candidate', 'term' => 'Private candidate', 'definition' => 'A candidate who is not entered through their day school and instead registers via a centre that accepts private entries (often British Council or approved private centres).', 'see' => '/guides/private-candidates'],
        ['slug' => 'candidate', 'term' => 'Candidate', 'definition' => 'The person entered for an examination.'],
        ['slug' => 'registration', 'term' => 'Registration / entry', 'definition' => 'The process of entering a candidate for a specific exam series and subject through an authorised centre.', 'see' => '/guides/how-to-register-for-pearson-exams'],
        ['slug' => 'exam-series', 'term' => 'Exam series / session', 'definition' => 'A scheduled exam window (for example January, May/June, or October/November). Exact availability depends on the centre and qualification.'],
        ['slug' => 'statement-of-entry', 'term' => 'Statement of entry', 'definition' => 'The document from your centre confirming the exams you have been entered for, including codes and dates.'],
        ['slug' => 'results', 'term' => 'Results', 'definition' => 'Grades released after an exam series, accessed through the centre that entered you.', 'see' => '/guides/results-and-certificates'],
        ['slug' => 'certificate', 'term' => 'Certificate', 'definition' => 'The official document confirming qualification awards. Issued via Pearson/centre processes — not by independent tuition colleges.'],
        ['slug' => 'past-paper', 'term' => 'Past paper', 'definition' => 'A previously used exam paper used for practice. Mark schemes help students understand how marks are awarded.'],
        ['slug' => 'tuition', 'term' => 'Tuition', 'definition' => 'Teaching and coaching outside (or alongside) a full school programme. At Edexcel College this means Pearson Edexcel–focused classes and exam preparation.'],
    ];
}

/**
 * Indexable public content nodes for hub search (title, url, blurb, keywords).
 *
 * @return list<array{title:string,url:string,blurb:string,keywords:string}>
 */
function seo_resource_index(): array
{
    $base = function_exists('seo_base_url') ? seo_base_url() : 'https://edexcel.college';
    $rows = [
        ['title' => 'Pearson exam services hub', 'url' => $base . '/pearson-exams', 'blurb' => 'Overview of tuition and country guides.', 'keywords' => 'pearson exams edexcel services'],
        ['title' => 'Student resources hub', 'url' => $base . '/resources', 'blurb' => 'Guides, FAQ, glossary, and country pages.', 'keywords' => 'resources guides faq'],
        ['title' => 'About the college', 'url' => $base . '/about', 'blurb' => 'Who we are, campus, and services.', 'keywords' => 'about edexcel college'],
        ['title' => 'Edexcel classes online worldwide', 'url' => $base . '/edexcel-classes', 'blurb' => 'Live IGCSE and IAL classes online worldwide. Physical classes only at the Kandy campus.', 'keywords' => 'online edexcel igcse ial worldwide kandy'],
        ['title' => 'IGCSE / O Level pathway', 'url' => $base . '/edexcel-o-level', 'blurb' => 'International GCSE preparation.', 'keywords' => 'igcse o level'],
        ['title' => 'A Level / IAL pathway', 'url' => $base . '/edexcel-a-level', 'blurb' => 'International A Level preparation.', 'keywords' => 'a level ial'],
        ['title' => 'Exam preparation', 'url' => $base . '/exam-preparation', 'blurb' => 'Past papers and exam technique.', 'keywords' => 'exam preparation past papers'],
        ['title' => 'FAQ knowledge base', 'url' => $base . '/faq', 'blurb' => 'Answers by topic.', 'keywords' => 'faq questions'],
        ['title' => 'Glossary', 'url' => $base . '/glossary', 'blurb' => 'Pearson/Edexcel terminology explained.', 'keywords' => 'glossary definitions'],
        ['title' => 'How exams work', 'url' => $base . '/guides/how-pearson-edexcel-exams-work', 'blurb' => 'Syllabus to papers overview.', 'keywords' => 'how exams work'],
        ['title' => 'How to register', 'url' => $base . '/guides/how-to-register-for-pearson-exams', 'blurb' => 'Centre registration checklist.', 'keywords' => 'register private candidate'],
        ['title' => 'Exam day checklist', 'url' => $base . '/guides/what-to-bring-to-a-pearson-exam', 'blurb' => 'What to bring and avoid.', 'keywords' => 'exam day id stationery'],
        ['title' => 'Results and certificates', 'url' => $base . '/guides/results-and-certificates', 'blurb' => 'How results and certificates are issued.', 'keywords' => 'results certificates'],
        ['title' => 'Private candidates', 'url' => $base . '/guides/private-candidates', 'blurb' => 'What private candidacy means.', 'keywords' => 'private candidate british council'],
        ['title' => 'IGCSE vs Edexcel O Level', 'url' => $base . '/guides/igcse-vs-edexcel-o-level', 'blurb' => 'What search phrases usually mean.', 'keywords' => 'igcse o level naming'],
        ['title' => 'Kandy campus', 'url' => $base . '/locations/kandy', 'blurb' => 'The physical campus. Most classes are still live online.', 'keywords' => 'kandy campus edexcel college'],
        ['title' => 'Contact', 'url' => $base . '/contact', 'blurb' => 'Phone, WhatsApp, email, map.', 'keywords' => 'contact enquire'],
        ['title' => 'Course enquiry', 'url' => $base . '/admissions/enquire.php', 'blurb' => 'Send a programme enquiry.', 'keywords' => 'enquire admissions'],
    ];
    foreach (seo_country_pages() as $slug => $page) {
        $rows[] = [
            'title' => (string)$page['title'],
            'url' => $base . '/pearson-exams/' . $slug,
            'blurb' => (string)$page['lead'],
            'keywords' => strtolower((string)$page['name'] . ' pearson edexcel'),
        ];
    }
    return $rows;
}

/**
 * Freshness notice for time-sensitive topics (no invented fees/dates).
 */
function seo_freshness_notice(string $reviewedLabel): string
{
    return 'Information reviewed ' . $reviewedLabel
        . '. Exam dates, registration windows, and centre fees change by session — always confirm with your examination centre and Pearson. '
        . 'We do not publish unverified fee tables or entry deadlines on this page.';
}

/**
 * Country landing definitions — unique copy only; no fake local offices.
 *
 * @return array<string, array<string, mixed>>
 */
function seo_country_pages(): array
{
    $pages = [
        'sri-lanka' => [
            'name' => 'Sri Lanka',
            'title' => 'Pearson Edexcel Classes for Students in Sri Lanka',
            'description' => 'Pearson Edexcel IGCSE and International A Level classes for students in Sri Lanka. Most classes are live online. Physical classes are at the Kandy campus when scheduled.',
            'h1' => 'Pearson Edexcel classes for students in Sri Lanka',
            'lead' => 'Students in Sri Lanka usually join live online classes, the same way students outside the country do. A physical class is at the Kandy campus only when that subject and teacher are on the campus timetable.',
            'geo_region' => 'LK',
            'geo_placename' => 'Kandy',
            'mode' => 'campus',
            'sections' => [
                [
                    'h' => 'Online classes, and the Kandy campus',
                    'p' => 'Around 90% of classes are live online, including for students who live in Sri Lanka. The physical campus is in Kandy (Central Province). An on-site seat exists only when that subject and teacher are scheduled there. Enrolled students use the portal for the timetable and the live class.',
                ],
                [
                    'h' => 'How Pearson exams work for Sri Lankan families',
                    'p' => 'Pearson Edexcel international qualifications are taken through authorised examination centres and school centres. Tuition at our college prepares candidates for those papers; official entries and results remain with Pearson and the centre you register with.',
                ],
                [
                    'h' => 'Who we help in Sri Lanka',
                    'p' => 'Secondary students aiming for IGCSE, post-16 learners on IAL units, and parents who want structured weekly teaching plus visibility of progress. Subject availability follows the live college timetable.',
                ],
            ],
        ],
        'italy' => [
            'name' => 'Italy',
            'title' => 'Pearson Edexcel Tuition for Students in Italy',
            'description' => 'Online Pearson Edexcel IGCSE and International A Level tuition for students and families in Italy. Independent exam preparation from Edexcel College.',
            'h1' => 'Pearson Edexcel exam preparation for students in Italy',
            'lead' => 'Families in Italy who need English-medium Pearson Edexcel preparation can enquire about online or hybrid tuition aligned to IGCSE and International A Level syllabuses.',
            'geo_region' => 'IT',
            'geo_placename' => 'Italy',
            'mode' => 'online',
            'sections' => [
                [
                    'h' => 'Why Italian families look for Pearson Edexcel support',
                    'p' => 'Some students in Italy pursue Pearson international qualifications for university pathways or English-medium study. Others combine local schooling with additional Edexcel subject tuition. We provide academic preparation — not Italian state exam administration.',
                ],
                [
                    'h' => 'How online tuition works',
                    'p' => 'After admissions confirms a suitable timetable, teaching can be delivered online with the same exam-focused approach used on campus: syllabus coverage, homework, and past-paper practice. Confirm current online slots when you enquire.',
                ],
                [
                    'h' => 'Exam registration in Italy',
                    'p' => 'Official Pearson entries must be made through Pearson and an authorised examination centre that accepts private or school candidates in your area. We can outline typical preparation milestones; we do not replace the centre’s registration process.',
                ],
            ],
        ],
        'qatar' => [
            'name' => 'Qatar',
            'title' => 'Pearson Edexcel Tuition for Students in Qatar',
            'description' => 'Online Pearson Edexcel IGCSE and IAL exam preparation for students in Qatar. Independent tuition support from Edexcel College.',
            'h1' => 'Pearson Edexcel exam services for students in Qatar',
            'lead' => 'Students in Qatar can join live online Pearson Edexcel classes. Class times are published in Asia/Colombo. The only physical campus is in Kandy, Sri Lanka.',
            'geo_region' => 'QA',
            'geo_placename' => 'Qatar',
            'mode' => 'online',
            'sections' => [
                [
                    'h' => 'Gulf time-zone friendly preparation',
                    'p' => 'Qatar’s school calendar and evening study patterns often differ from Sri Lanka. When you enquire, ask admissions about lesson times that fit Qatar (AST) so live sessions remain sustainable during exam season.',
                ],
                [
                    'h' => 'IGCSE and IAL focus',
                    'p' => 'We concentrate on Pearson Edexcel International GCSE and International A Level content — topic teaching, exam command words, and past-paper timing — rather than generic English tutoring.',
                ],
                [
                    'h' => 'Local exam centres',
                    'p' => 'Pearson qualifications in Qatar are sat through authorised centres. Choose your centre early, note entry deadlines, and use our tuition to prepare for the papers you enter.',
                ],
            ],
        ],
        'oman' => [
            'name' => 'Oman',
            'title' => 'Pearson Edexcel Tuition for Students in Oman',
            'description' => 'Online Pearson Edexcel examination preparation for students in Oman. IGCSE and International A Level tuition with Edexcel College.',
            'h1' => 'Pearson Edexcel exam preparation for students in Oman',
            'lead' => 'Families in Oman seeking additional Pearson Edexcel subject support can enquire about online classes and exam-focused coaching.',
            'geo_region' => 'OM',
            'geo_placename' => 'Oman',
            'mode' => 'online',
            'sections' => [
                [
                    'h' => 'Support beyond the school timetable',
                    'p' => 'Many students in Oman already attend international or bilingual schools. Our role is targeted Pearson Edexcel syllabus teaching and paper practice where extra depth or catch-up is needed.',
                ],
                [
                    'h' => 'Planning around GST',
                    'p' => 'Lesson scheduling should respect Oman’s Gulf Standard Time. Mention preferred evenings or weekends when you contact admissions so we can check teacher availability.',
                ],
                [
                    'h' => 'Registration remains with Pearson centres',
                    'p' => 'We do not operate an examination hall in Oman. Candidates enter through Pearson-authorised centres; we prepare you academically for those assessments.',
                ],
            ],
        ],
        'uae' => [
            'name' => 'United Arab Emirates',
            'title' => 'Pearson Edexcel Tuition for Students in the UAE',
            'description' => 'Online Pearson Edexcel IGCSE and International A Level tuition for students in the UAE (Dubai, Abu Dhabi, Sharjah, and other emirates).',
            'h1' => 'Pearson Edexcel exam services for students in the UAE',
            'lead' => 'Students across the UAE can enquire about online Pearson Edexcel preparation with teachers experienced in IGCSE and International A Level assessment.',
            'geo_region' => 'AE',
            'geo_placename' => 'United Arab Emirates',
            'mode' => 'online',
            'sections' => [
                [
                    'h' => 'Serving a mobile, international student community',
                    'p' => 'The UAE has a large expatriate student population. Families often need consistent Edexcel subject teaching when school options change between emirates or when a learner needs intensive revision before a series.',
                ],
                [
                    'h' => 'Dubai, Abu Dhabi, and beyond',
                    'p' => 'Delivery is online, so the same tuition model can serve Dubai, Abu Dhabi, Sharjah, and other emirates. Physical campus services remain in Kandy, Sri Lanka.',
                ],
                [
                    'h' => 'Align tuition with your centre’s series',
                    'p' => 'Match your revision plan to the Pearson series your UAE centre offers. Share your subject list and target series when you enquire so we can propose a realistic weekly load.',
                ],
            ],
        ],
        'maldives' => [
            'name' => 'Maldives',
            'title' => 'Pearson Edexcel Tuition for Students in the Maldives',
            'description' => 'Online Pearson Edexcel IGCSE and IAL preparation for students in the Maldives, with a nearby Sri Lanka campus option in Kandy.',
            'h1' => 'Pearson Edexcel classes for students in the Maldives',
            'lead' => 'Maldivian students and families can combine online Pearson Edexcel tuition with, where practical, intensive on-campus sessions in Kandy, Sri Lanka.',
            'geo_region' => 'MV',
            'geo_placename' => 'Maldives',
            'mode' => 'online',
            'sections' => [
                [
                    'h' => 'Regional convenience',
                    'p' => 'Travel between Malé and Sri Lanka is comparatively straightforward for many families. That makes hybrid plans — online weekly lessons plus occasional Kandy intensives — worth discussing with admissions.',
                ],
                [
                    'h' => 'English-medium international pathways',
                    'p' => 'Pearson Edexcel IGCSE and IAL remain popular English-medium routes. We teach the syllabus and exam craft; your chosen centre handles official entry.',
                ],
                [
                    'h' => 'Connectivity and live class quality',
                    'p' => 'Stable internet is essential for live online lessons. If bandwidth is limited, ask about recording access for enrolled students after class.',
                ],
            ],
        ],
        'united-kingdom' => [
            'name' => 'United Kingdom',
            'title' => 'Pearson Edexcel International Tuition for UK-Based Students',
            'description' => 'Online Pearson Edexcel International GCSE and International A Level tuition for students based in the United Kingdom.',
            'h1' => 'Pearson Edexcel international exam prep for students in the UK',
            'lead' => 'UK-based learners who need Pearson Edexcel International (IGCSE/IAL) support — for example alongside other curricula or for overseas university plans — can enquire about online tuition.',
            'geo_region' => 'GB',
            'geo_placename' => 'United Kingdom',
            'mode' => 'online',
            'sections' => [
                [
                    'h' => 'International vs UK domestic routes',
                    'p' => 'Pearson offers both UK domestic and international qualification families. Our teaching focus is Pearson Edexcel International GCSE and International A Level. Confirm which specification your centre enters before you start.',
                ],
                [
                    'h' => 'Time zones and revision blocks',
                    'p' => 'UK time (GMT/BST) differs from Sri Lanka (IST, UTC+5:30). Evening UK slots may map to early morning in Kandy — share constraints when you enquire.',
                ],
                [
                    'h' => 'Independent support, not a UK exam board office',
                    'p' => 'We are a Sri Lankan tuition college. We do not process UCAS applications or act as a Pearson UK customer-service desk; we teach the academic content of the papers you sit.',
                ],
            ],
        ],
        'india' => [
            'name' => 'India',
            'title' => 'Pearson Edexcel Tuition for Students in India',
            'description' => 'Online Pearson Edexcel IGCSE and International A Level preparation for students in India seeking international qualification pathways.',
            'h1' => 'Pearson Edexcel exam preparation for students in India',
            'lead' => 'Students in India exploring Pearson Edexcel international qualifications can enquire about online subject tuition and structured exam practice.',
            'geo_region' => 'IN',
            'geo_placename' => 'India',
            'mode' => 'online',
            'sections' => [
                [
                    'h' => 'Complementing other Indian boards',
                    'p' => 'Some families combine CBSE/ISC/state-board schooling with international subject goals, or move between curricula. Tell us your current board and target Pearson subjects so teaching stays complementary, not conflicting.',
                ],
                [
                    'h' => 'Shared time zone advantage',
                    'p' => 'India and Sri Lanka share IST (UTC+5:30), which simplifies live online scheduling compared with Europe or the Gulf.',
                ],
                [
                    'h' => 'Centre selection',
                    'p' => 'Sit exams only through Pearson-authorised centres that accept your candidature type. We prepare you for the assessments; the centre completes formal registration.',
                ],
            ],
        ],
        'gulf' => [
            'name' => 'Gulf (Saudi Arabia, Bahrain, Kuwait)',
            'title' => 'Pearson Edexcel Tuition for Gulf Students',
            'description' => 'Online Pearson Edexcel IGCSE and IAL tuition for students in Saudi Arabia, Bahrain, and Kuwait. Independent preparation from Edexcel College — no local branch offices.',
            'h1' => 'Pearson Edexcel exam preparation for Saudi Arabia, Bahrain, and Kuwait',
            'lead' => 'Families in Saudi Arabia, Bahrain, and Kuwait can enquire about online Pearson Edexcel tuition aligned to IGCSE and International A Level syllabuses.',
            'geo_region' => 'SA',
            'geo_placename' => 'Gulf Cooperation Council',
            'mode' => 'online',
            'sections' => [
                [
                    'h' => 'Saudi Arabia',
                    'p' => 'Students often need evening or weekend slots around local school hours. Mention Riyadh, Jeddah, Dammam, or other city time preferences when you enquire so we can check teacher availability against AST.',
                ],
                [
                    'h' => 'Bahrain',
                    'p' => 'Bahrain’s compact geography and international school community mean many learners already sit international papers. We provide additional subject depth and paper practice online — not a Manama campus.',
                ],
                [
                    'h' => 'Kuwait',
                    'p' => 'Share your subject list and target Pearson series early. We help with academic preparation; authorised centres in Kuwait handle formal entries.',
                ],
                [
                    'h' => 'Shared Gulf notes',
                    'p' => 'We do not operate offices in the Gulf. Delivery is online/hybrid from our Sri Lanka team. Related pages also cover Qatar, Oman, and the UAE in more detail.',
                ],
            ],
        ],
    ];

    return seo_apply_country_hub_fields($pages);
}

/**
 * Country-hub authority fields (FAQs, process, official sources) — unique per market.
 *
 * @param array<string, array<string, mixed>> $pages
 * @return array<string, array<string, mixed>>
 */
function seo_apply_country_hub_fields(array $pages): array
{
    $extras = [
        'sri-lanka' => [
            'title' => 'Pearson Edexcel Tuition in Sri Lanka | Online and Kandy',
            'description' => 'Pearson Edexcel IGCSE and International A Level classes. Around 90% are live online worldwide. Physical classes are at the Kandy campus only. Not a Pearson exam centre.',
            'answer' => 'Edexcel College teaches Pearson Edexcel IGCSE and International A Level. Around 90% of classes are live online for students worldwide, including students in Sri Lanka. Physical classes are at the Kandy campus when scheduled. Official exam registration is through authorised centres, not through the college.',
            'services' => [
                ['Live online IGCSE / IAL classes', 'Yes', 'Main delivery mode, worldwide'],
                ['Campus IGCSE / IAL classes (Kandy only)', 'Selected subjects', 'Depends on teacher and timetable'],
                ['Official Pearson exam entry', 'No — use a centre', 'e.g. British Council or other authorised centres'],
                ['Results / certificates', 'No — via your centre', 'Not issued by the college'],
            ],
            'process' => [
                'Choose IGCSE and/or IAL subjects and confirm the exact Pearson specifications.',
                'Enquire for a live online class, or ask whether that subject has a Kandy campus seat.',
                'Register for exams with an authorised centre in Sri Lanka (school centre or private-candidate route such as British Council).',
                'Share your statement of entry subjects with teachers so lessons match your papers.',
                'Sit exams at the centre; receive results and certificates through that centre.',
            ],
            'who' => [
                'Students in Sri Lanka who will attend live online',
                'Students near Kandy who need a physical class when it is on the timetable',
                'Families in Kurunegala or Colombo joining online, or travelling to Kandy when a class is on site',
                'Private candidates who need structured teaching alongside centre registration',
                'IAL students needing unit-by-unit coaching before a series',
            ],
            'faqs' => [
                [
                    'question' => 'Can I register for Pearson Edexcel exams through Edexcel College?',
                    'answer' => 'No. We are an independent tuition college, not a Pearson examination centre. Register through an authorised centre. Many private candidates in Sri Lanka use British Council school-exam registration — always confirm current windows and fees with them.',
                ],
                [
                    'question' => 'Do you have campuses in Colombo or Kurunegala?',
                    'answer' => 'No. Our physical campus is in Kandy. Colombo and Kurunegala students typically join live online classes or travel to Kandy for selected sessions.',
                ],
                [
                    'question' => 'Where do private candidates sit exams in Sri Lanka?',
                    'answer' => 'Through Pearson-authorised centres that accept private entries. British Council Sri Lanka is a common route; other centres may also accept candidates. Confirm subjects and venues with the centre you choose.',
                ],
            ],
            'official' => [
                ['British Council Sri Lanka — private Edexcel registration', 'https://www.britishcouncil.lk/exam/school-exams/register/private-edexcel'],
                ['British Council School Exams portal', 'https://schoolexams.britishcouncil.org/'],
                ['Pearson qualifications (official)', 'https://qualifications.pearson.com/'],
                ['Private candidates (Pearson)', 'https://qualifications.pearson.com/en/support/support-topics/registrations-and-entries/academic-registrations-and-entries/private-candidates.html'],
                ['How to register (our guide)', '/guides/how-to-register-for-pearson-exams'],
                ['Kandy campus', '/locations/kandy'],
            ],
        ],
        'italy' => [
            'title' => 'Online Pearson Edexcel Tuition for Students in Italy',
            'description' => 'Online Pearson Edexcel IGCSE and International A Level tuition for families in Italy. Academic preparation from Edexcel College — register exams via authorised centres.',
            'answer' => 'Students in Italy can enquire about online Pearson Edexcel IGCSE and IAL tuition. Exam entries must be made through an authorised centre that accepts candidates in Italy; we provide teaching, not centre registration.',
            'faqs' => [
                [
                    'question' => 'Do you have a campus in Italy?',
                    'answer' => 'No. Delivery is online/hybrid from our Sri Lanka team. We do not operate an office in Milan, Rome, or elsewhere in Italy.',
                ],
                [
                    'question' => 'Can you enter me for Pearson exams in Italy?',
                    'answer' => 'No. Find a Pearson-authorised centre that accepts your candidature type, then align our tuition with those papers.',
                ],
            ],
            'official' => [
                ['Pearson qualifications (official)', 'https://qualifications.pearson.com/'],
                ['Private candidates (Pearson)', 'https://qualifications.pearson.com/en/support/support-topics/registrations-and-entries/academic-registrations-and-entries/private-candidates.html'],
                ['Private candidates guide', '/guides/private-candidates'],
            ],
        ],
        'qatar' => [
            'title' => 'Online Pearson Edexcel Tuition for Students in Qatar',
            'description' => 'Online Pearson Edexcel IGCSE and IAL exam preparation for students in Qatar. Independent tuition from Edexcel College — not a Doha exam centre.',
            'answer' => 'Qatar-based students can request online Pearson Edexcel preparation timed around AST. Sit exams through authorised centres in Qatar; use our classes for syllabus teaching and past-paper practice.',
            'faqs' => [
                [
                    'question' => 'Do you have a branch in Doha?',
                    'answer' => 'No. We provide online tuition only for Qatar. Physical campus services remain in Kandy, Sri Lanka.',
                ],
                [
                    'question' => 'What should I send when I enquire from Qatar?',
                    'answer' => 'Subjects, target exam series, preferred evenings (AST), and whether you already have a centre place.',
                ],
            ],
        ],
        'oman' => [
            'title' => 'Online Pearson Edexcel Tuition for Students in Oman',
            'description' => 'Online Pearson Edexcel IGCSE and International A Level coaching for students in Oman. Independent preparation — register via authorised centres.',
            'answer' => 'Families in Oman can enquire about online Pearson Edexcel subject tuition. We do not host exams in Oman; choose an authorised centre for entry and results.',
            'faqs' => [
                [
                    'question' => 'Is this a Muscat exam centre?',
                    'answer' => 'No. We are a tuition provider. Examination halls and entries are managed by Pearson-authorised centres.',
                ],
            ],
        ],
        'uae' => [
            'title' => 'Online Pearson Edexcel Tuition for Students in the UAE',
            'description' => 'Online Pearson Edexcel IGCSE and IAL tuition for Dubai, Abu Dhabi, Sharjah, and other emirates. Independent college based in Kandy — no UAE branch office.',
            'answer' => 'UAE students can join online Pearson Edexcel tuition. Mention your emirate, subjects, and centre series when you enquire. We do not operate campuses in Dubai or Abu Dhabi.',
            'faqs' => [
                [
                    'question' => 'Do you teach from Dubai?',
                    'answer' => 'No. Teaching is online from our Sri Lanka team. Dubai, Abu Dhabi, and other emirates can use the same online model.',
                ],
                [
                    'question' => 'Can you guarantee a UAE exam centre place?',
                    'answer' => 'No. Centre places and fees are controlled by authorised centres. Secure entry first or in parallel, then match tuition to those papers.',
                ],
            ],
        ],
        'maldives' => [
            'title' => 'Pearson Edexcel Tuition for Students in the Maldives',
            'description' => 'Online Pearson Edexcel IGCSE and IAL tuition for Maldivian students, with optional Kandy campus intensives when travel is practical.',
            'answer' => 'Maldives families can combine online weekly tuition with occasional Kandy intensives when travel works. Exam registration remains with an authorised centre.',
            'faqs' => [
                [
                    'question' => 'Can we study partly in Kandy?',
                    'answer' => 'Sometimes. Ask admissions about intensive blocks if travel from Malé is practical. Weekly teaching is usually online.',
                ],
            ],
        ],
        'united-kingdom' => [
            'title' => 'Online Pearson Edexcel Tuition for UK Students',
            'description' => 'Online tuition for Pearson Edexcel International GCSE and IAL for UK-based learners. We teach international specs — confirm your centre\'s entry route.',
            'answer' => 'UK-based students needing Pearson Edexcel International (IGCSE/IAL) support can enquire about online tuition. Confirm whether your centre enters international or UK domestic specifications before starting.',
            'faqs' => [
                [
                    'question' => 'Do you teach UK domestic GCSE/A Level?',
                    'answer' => 'Our focus is Pearson Edexcel International GCSE and International A Level. If your centre enters a different specification, tell us before enrolment.',
                ],
            ],
        ],
        'india' => [
            'title' => 'Online Pearson Edexcel Tuition for Students in India',
            'description' => 'Online Pearson Edexcel IGCSE and IAL preparation for students in India. Shared IST time zone with our Kandy teaching team.',
            'answer' => 'Students in India can enquire about online Pearson Edexcel tuition on IST. We prepare you for international papers; authorised centres handle formal entry.',
            'faqs' => [
                [
                    'question' => 'Will this conflict with CBSE or ISC?',
                    'answer' => 'It can, if timetables overlap. Tell us your current board and target Pearson subjects so teaching stays complementary.',
                ],
            ],
        ],
        'gulf' => [
            'title' => 'Online Pearson Edexcel Tuition — Saudi Arabia, Bahrain, Kuwait',
            'description' => 'Online Pearson Edexcel IGCSE and IAL tuition for Saudi Arabia, Bahrain, and Kuwait. No local branch offices — independent preparation from Edexcel College, Kandy.',
            'answer' => 'Gulf families in Saudi Arabia, Bahrain, and Kuwait can enquire about online Pearson Edexcel tuition. Related pages cover Qatar, Oman, and the UAE in more detail. We do not operate Gulf offices.',
            'faqs' => [
                [
                    'question' => 'Do you have offices in Riyadh, Manama, or Kuwait City?',
                    'answer' => 'No. Delivery is online/hybrid. See our Qatar, Oman, and UAE pages if those markets fit better.',
                ],
            ],
            'official' => [
                ['Pearson qualifications (official)', 'https://qualifications.pearson.com/'],
                ['Qatar guide', '/pearson-exams/qatar'],
                ['Oman guide', '/pearson-exams/oman'],
                ['UAE guide', '/pearson-exams/uae'],
            ],
        ],
    ];

    foreach ($extras as $slug => $extra) {
        if (!isset($pages[$slug])) {
            continue;
        }
        $pages[$slug] = array_merge($pages[$slug], $extra);
    }
    return $pages;
}
