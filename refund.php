<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/legal_page.php';
$c = legal_page_contact();
legal_page_start(
    'Refund Policy',
    'Refund policy for Edexcel class fees at Edexcel College, including OnePay card payments in Sri Lanka.',
    'refund-policy'
);
?>
    <h1>Refund Policy</h1>
    <p>This policy explains when <?= e($c['name']) ?> will refund a class fee or other payment made through <a href="<?= e(college_public_base()) ?>/"><?= e(preg_replace('#^https?://#', '', college_public_base()) ?? 'edexcel.college') ?></a>, including card payments processed by OnePay. There is no physical product to return. The service you pay for is a scheduled class (and the related live session and recording access).</p>

    <h2>What you are paying for</h2>
    <p>Fees are charged in Sri Lankan Rupees (LKR) for teaching, live class access, and that lesson’s recording. The amount due is shown on the class page before you pay. A successful card payment or a bank slip confirmed by the office unlocks that access.</p>

    <h2>When we will refund</h2>
    <p>We will refund, or arrange a replacement class, when:</p>
    <ul>
        <li>the College cancels a class and does not offer a reasonable replacement;</li>
        <li>you were charged twice for the same class, or charged the wrong amount because of an error;</li>
        <li>a card payment succeeded but the office later confirms the class should not have been billed;</li>
        <li>you paid by card and then paid again in cash or by transfer for the same class, and the office confirms the duplicate.</li>
    </ul>
    <p>Approved card refunds are sent back to the original card through OnePay. OnePay states that refunds usually appear on the card statement within <strong>5–10 business days</strong>.</p>

    <h2>When we will not refund</h2>
    <ul>
        <li>the student attended the class, or watched the recording, unless the office agrees otherwise;</li>
        <li>the student missed the class without a prior arrangement accepted by the College;</li>
        <li>you change your mind after payment for a class that still takes place;</li>
        <li>a bank-transfer slip was uploaded but never confirmed — in that case no card charge exists to refund, and access stays locked until a valid payment is confirmed.</li>
    </ul>
    <p>Complaints about teaching are handled by the office. They are not an automatic card refund.</p>

    <h2>How to request a refund</h2>
    <ol>
        <li>Email <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a>, call <a href="tel:<?= e($c['phone_tel']) ?>"><?= e($c['phone']) ?></a>, or visit the College office during office hours.</li>
        <li>Give the student name, class date, amount paid, and the payment reference or receipt if you have one.</li>
        <li>We will reply within <strong>3 working days</strong> with whether the refund is approved and how it will be paid.</li>
    </ol>
    <p>Staff may also start a OnePay refund from the College office when a paid class is unmarked.</p>

    <h2>Chargebacks</h2>
    <p>If you dispute a card charge with your bank, tell the College first so we can check the record. Unjustified chargebacks may lead to the student account being suspended until the fee is settled.</p>

    <p>Related pages: <a href="/terms">Terms and Conditions</a> · <a href="/privacy-policy">Privacy Policy</a> · <a href="/contact">Contact</a>.</p>
<?php
legal_page_end();
