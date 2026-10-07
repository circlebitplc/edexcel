<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/legal_page.php';
$c = legal_page_contact();
$base = seo_base_url();
legal_page_start(
    'Contact Edexcel College — Online Classes Worldwide',
    'Contact Edexcel College about live online IGCSE and IAL classes for students worldwide, or about a physical class at the Kandy campus. Phone, email, and WhatsApp.',
    'contact'
);
?>
    <h1>Contact <?= e($c['name']) ?></h1>
    <p>Around 90% of classes are live online, so students outside Sri Lanka use these same contact details. The address below is the Kandy campus, the only physical site. Card payments on this website are processed by OnePay. A browser return page does not by itself mark a lesson paid.</p>

    <h2>Business address (campus)</h2>
    <address><a href="<?= e($c['maps_url'] ?? 'https://maps.app.goo.gl/1FJE2mQ5eR1HijsbA') ?>" target="_blank" rel="noopener noreferrer"><?= e($c['address']) ?></a></address>
    <div class="legal-map-grid" style="display:grid;grid-template-columns:1fr;gap:1rem;margin:1rem 0 1.5rem;">
        <iframe
            title="Edexcel College on Google Maps"
            src="<?= e($c['maps_embed_url'] ?? 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3957.45!2d80.6346098!3d7.3050896!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae367d64dd20f49%3A0x3539c01e3bc33f01!2s83%20Katugastota%20Rd%2C%20Kandy!5e0!3m2!1sen!2slk!4v1720000000000!5m2!1sen!2slk') ?>"
            width="800"
            height="350"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            allowfullscreen
            style="width:100%;border:0;border-radius:12px;"
        ></iframe>
    </div>

    <h2>Email</h2>
    <p><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></p>

    <h2>Telephone</h2>
    <p><a href="tel:<?= e($c['phone_tel']) ?>"><?= e($c['phone']) ?></a></p>
    <p>WhatsApp: <a href="https://wa.me/<?= e($c['whatsapp']) ?>" rel="noopener">+<?= e($c['whatsapp']) ?></a></p>

    <h2>Office hours (Sri Lanka time, IST UTC+5:30)</h2>
    <p><?= e($c['hours']) ?></p>

    <h2>International students</h2>
    <p>We do not operate branch offices outside Sri Lanka. Students in Italy, Qatar, Oman, the UAE, the Maldives, the UK, India, and nearby Gulf markets can enquire about online tuition. Include your country, subjects, and preferred time zone.</p>
    <p>
        <a href="<?= e($base) ?>/admissions/enquire.php">Course enquiry form</a> ·
        <a href="<?= e($base) ?>/pearson-exams">Country guides</a> ·
        <a href="<?= e($base) ?>/about">About the college</a>
    </p>

    <h2>Programmes</h2>
    <p>
        <a href="<?= e($base) ?>/edexcel-classes">Edexcel classes</a> ·
        <a href="<?= e($base) ?>/edexcel-o-level">IGCSE / O Level</a> ·
        <a href="<?= e($base) ?>/edexcel-a-level">A Level / IAL</a> ·
        <a href="<?= e($base) ?>/exam-preparation">Exam preparation</a> ·
        <a href="<?= e($base) ?>/faq">FAQ</a>
    </p>

    <h2>Policies</h2>
    <p>
        <a href="<?= e($base) ?>/terms">Terms and Conditions</a> ·
        <a href="<?= e($base) ?>/privacy-policy">Privacy Policy</a> ·
        <a href="<?= e($base) ?>/refund-policy">Refund Policy</a>
    </p>
<?php
legal_page_end('11 September 2026', false);
