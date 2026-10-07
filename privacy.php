<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/legal_page.php';
$c = legal_page_contact();
legal_page_start(
    'Privacy Policy',
    'How Edexcel College collects, uses, and stores personal data for Edexcel classes, the student portal, and payments in Sri Lanka.',
    'privacy-policy'
);
?>
    <h1>Privacy Policy</h1>
    <p><?= e($c['name']) ?> (“the College”) operates <a href="<?= e(college_public_base()) ?>/"><?= e(preg_replace('#^https?://#', '', college_public_base()) ?? 'edexcel.college') ?></a> and related student, parent, teacher, payment, and WhatsApp services. This policy explains what personal data we collect, why we use it, how it is stored, and how you can ask for access or deletion. It is intended to meet card-network requirements and Sri Lanka’s Personal Data Protection Act.</p>

    <h2>Information we collect</h2>
    <p>We collect:</p>
    <ul>
        <li>account details (name, phone number, email);</li>
        <li>class, timetable, attendance, and exam records;</li>
        <li>fee and payment records, including amounts, method, and OnePay transaction references (not full card numbers);</li>
        <li>bank-slip images you upload for verification;</li>
        <li>messages you send to the College WhatsApp number so we can reply and run support;</li>
        <li>technical logs such as IP address and device information needed to keep the site secure.</li>
    </ul>

    <h2>How we use it</h2>
    <p>We use this information to provide classes, attendance, exams, recordings, parent updates, and payments; to confirm fees; to contact you about College operations; and to meet legal and audit requirements. We do not sell personal data.</p>

    <h2>Payments</h2>
    <p>Card payments are processed by OnePay (Spemai (Pvt) Ltd). When you pay by card, OnePay receives the payer name, email, phone, amount, and payment details needed to complete the transaction. OnePay’s own privacy terms apply to that processing. The College stores the payment result and a transaction reference so we can unlock the class and issue a refund if required.</p>

    <h2>Sharing</h2>
    <p>We share data only with service providers who host this website, process payments (OnePay), send WhatsApp or SMS messages (including Meta when Cloud API is used), or as required by law. We do not share student records for marketing by third parties.</p>

    <h2>Storage and retention</h2>
    <p>Records are stored on the College’s servers and with those providers. We keep education, fee, and payment records for as long as needed for teaching, accounting, chargebacks, and legal requirements. You can ask us to correct inaccurate account details at any time.</p>

    <h2>Cookies and similar technologies</h2>
    <p>We use cookies and similar storage needed for login sessions, security (such as CSRF protection), language preference, and basic site operation. We do not use advertising trackers by default. If a measurement ID is configured by the College for analytics, it is used only to understand site usage and improve services.</p>

    <h2>Your rights</h2>
    <p>To access, correct, or delete personal data we hold, email <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a> or use the <a href="/data-deletion">data deletion</a> page. We will remove portal access and associated chatbot history that is not required by law or fee records, usually within 30 days.</p>

    <p>See also our <a href="/terms">Terms and Conditions</a> and <a href="/refund-policy">Refund Policy</a>.</p>
<?php
legal_page_end('11 September 2026');
