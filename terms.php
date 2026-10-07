<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/legal_page.php';
$c = legal_page_contact();
legal_page_start(
    'Terms and Conditions',
    'Terms for Edexcel classes, student portal access, and payments at Edexcel College in Sri Lanka.',
    'terms'
);
?>
    <h1>Terms and Conditions</h1>
    <p>These terms govern use of <a href="<?= e(college_public_base()) ?>/"><?= e(preg_replace('#^https?://#', '', college_public_base()) ?? 'edexcel.college') ?></a>, the student, parent and staff portals, live classes, recordings, and payments operated by <?= e($c['name']) ?> (“the College”). By creating an account, paying a class fee, or using College services, you agree to these terms.</p>

    <h2>1. Services</h2>
    <p>The College provides Pearson Edexcel IGCSE and International A Level teaching in Kandy, together with a student portal, parent access, live online classes, lesson recordings, attendance, and fee collection. Timetables, fees, and materials may change. Portal fee records support College operations and do not replace an official invoice or receipt issued by the office.</p>

    <h2>2. Accounts and access</h2>
    <p>Portal accounts are for enrolled students, parents or guardians where permitted, teachers, and staff. You must keep login details private and tell the College if you think an account is misused. The College may suspend access if fees are unpaid, if an account is misused, or if required by law.</p>

    <h2>3. Fees and payment</h2>
    <p>Class fees are charged per lesson (or as otherwise stated by the office) in Sri Lankan Rupees (LKR). You may pay:</p>
    <ul>
        <li>by card on this website (processed by OnePay);</li>
        <li>by bank transfer with a slip uploaded for office confirmation; or</li>
        <li>in person at the College counter.</li>
    </ul>
    <p>Card details are entered on OnePay's payment page. The College does not store full card numbers. A successful card payment or a confirmed slip unlocks the live class and that lesson's recording. Access stays locked until payment is confirmed. Paying a class fee means you accept these terms and the <a href="/refund-policy">Refund Policy</a>.</p>

    <h2>4. Classes, recordings, and content</h2>
    <p>Course materials, recordings, and portal content remain College property unless stated otherwise. Do not copy, share, or resell recordings or papers outside the enrolled student’s household. Live-class links are for the enrolled student only.</p>

    <h2>5. WhatsApp and notices</h2>
    <p>If you message the College on WhatsApp, we may reply with class, attendance, payment, or support information. Message delivery may use Meta WhatsApp Business / Cloud API. Meta’s terms also apply to that channel. You can ask us to stop WhatsApp notices; we will still keep records required for education and fees.</p>

    <h2>6. Acceptable use</h2>
    <p>Do not attempt to break into the site, scrape personal data, send spam through College channels, or impersonate staff or students.</p>

    <h2>7. Liability</h2>
    <p>The website, live classes, recordings, and chatbot support College teaching. We are not liable for outages, delayed messages, or information you rely on without confirming with the office. Nothing in these terms limits liability that Sri Lankan law does not allow us to limit.</p>

    <h2>8. Disputes</h2>
    <p>If you have a billing or class complaint, contact the office first using the details below. We will try to resolve it within a reasonable time. These terms are governed by the law of Sri Lanka. Courts in Kandy have jurisdiction, without limiting any rights you have under consumer law.</p>

    <h2>9. Changes</h2>
    <p>We may update these terms. The date below is the current version. Continued use of the site after a change means you accept the updated terms.</p>
<?php
legal_page_end();
