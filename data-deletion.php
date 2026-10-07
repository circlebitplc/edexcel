<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/legal_page.php';
$c = legal_page_contact();
legal_page_start(
    'User Data Deletion',
    'How to request deletion of your Edexcel College account and WhatsApp data.',
    'data-deletion'
);
?>
    <h1>User data deletion</h1>
    <p>To delete personal data held by <?= e($c['name']) ?> on <a href="<?= e(college_public_base()) ?>/"><?= e(preg_replace('#^https?://#', '', college_public_base()) ?? 'edexcel.college') ?></a> or in the College WhatsApp chatbot:</p>
    <ol>
        <li>Email the College office at <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a> from the same phone number or email on your account.</li>
        <li>Write “Delete my data” and include your full name and student or staff ID if you have one.</li>
        <li>We will remove portal access and associated chatbot history that is not required by law or fee records, usually within 30 days.</li>
    </ol>
    <p>Disconnecting WhatsApp in the admin Connect WhatsApp page stops Cloud API delivery to this website. It does not delete the WhatsApp Business app on the phone.</p>
    <p>Fee and payment records needed for accounting or chargebacks may be kept as required by law. See the <a href="/privacy-policy">Privacy Policy</a>.</p>
<?php
legal_page_end();
