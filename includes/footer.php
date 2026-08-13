<?php
// ============================================================
// includes/footer.php
// ============================================================

$dashboard_js =
    __DIR__ .
    '/../assets/js/dashboard.js';

?>

        </main>

    </div>

</div>


<!-- ============================================================
     BOOTSTRAP JAVASCRIPT
============================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<!-- ============================================================
     CONSOLIDATED PORTAL JAVASCRIPT
============================================================= -->

<?php if (file_exists($dashboard_js)): ?>

    <script
        src="<?= BASE_URL ?>assets/js/dashboard.js?v=<?= filemtime($dashboard_js) ?>"
        defer
    ></script>

<?php endif; ?>


</body>

</html> 