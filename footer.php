</main>

<!-- Floating WhatsApp -->
<a href="https://wa.me/<?= $admin_phone ?>" target="_blank" class="floating-whatsapp" aria-label="Chat on WhatsApp">
    <i class="fab fa-whatsapp"></i>
    <span class="pulse-ring"></span>
</a>

<!-- Back to Top -->
<button id="backToTop" class="back-to-top" aria-label="Back to top">
    <i class="fas fa-chevron-up"></i>
</button>

<!-- Footer -->
<footer class="footer-new" role="contentinfo">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="footer-brand">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Edexcel College</span>
                </div>
                <p class="footer-desc">International academic pathways, modern classrooms and steady guidance for learners preparing for global next steps.</p>
                <div class="footer-social">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
                <div class="quick-links mt-3">
                    <a href="#home">Home</a> · <a href="#teachers">Teachers</a> · <a href="#timetable">Timetable</a> · <a href="#news">News</a>
                </div>
            </div>
            <div class="col-lg-2 col-md-6">
                <h5>Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="#teachers">Teachers</a></li>
                    <li><a href="#timetable">Timetable</a></li>
                    <li><a href="#features">Why Us</a></li>
                    <li><a href="#cta">Contact</a></li>
                    <li><a href="/contact">Contact</a></li>
                    <li><a href="/privacy-policy">Privacy policy</a></li>
                    <li><a href="/terms">Terms and Conditions</a></li>
                    <li><a href="/refund-policy">Refund policy</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h5>Contact</h5>
                <ul class="footer-contact">
                    <li><i class="fas fa-map-marker-alt"></i> No 83 Katugatota Road, Kandy</li>
                    <li><i class="fas fa-phone"></i> <a href="tel:+94785858585">+94 78 585 8585</a></li>
                    <li><i class="fas fa-envelope"></i> <a href="mailto:info@edexcel.college">info@edexcel.college</a></li>
                    <li><i class="fab fa-whatsapp"></i> <a href="https://wa.me/94785858585" target="_blank">WhatsApp</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h5>Office Hours</h5>
                <ul class="footer-hours">
                    <li><span>Mon–Fri:</span> 8:00 – 18:00</li>
                    <li><span>Sat:</span> 8:00 – 14:00</li>
                    <li><span>Sun:</span> Closed</li>
                </ul>
                <div class="footer-cta mt-3">
                    <a href="#" class="btn btn-outline-light btn-sm"><i class="fas fa-calendar-check"></i> Book a Visit</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Edexcel College. All rights reserved.
                <a href="/privacy-policy">Privacy</a> · <a href="/terms">Terms</a>
            </p>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js" defer></script>
<script src="assets/js/home.js" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof AOS !== 'undefined') {
            AOS.init({ duration: 600, easing: 'ease-in-out', once: true, offset: 50 });
        }
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js')
                .then(function(reg) { console.log('SW registered:', reg); })
                .catch(function(err) { console.log('SW registration failed:', err); });
        }
    });
</script>
<?php
if (is_file(__DIR__ . '/includes/visitor_tracking.php')) {
    require_once __DIR__ . '/includes/visitor_tracking.php';
    visitor_tracking_tag();
}
?>
</body>
</html>