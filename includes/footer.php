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

<div class="modal fade app-dialog-modal" id="appDialogModal" tabindex="-1" aria-labelledby="appDialogTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <div class="app-dialog-icon" aria-hidden="true"><i class="bi bi-info-circle"></i></div>
                <h2 class="app-dialog-title" id="appDialogTitle">Notice</h2>
                <p class="app-dialog-text" id="appDialogText"></p>
                <div class="app-dialog-actions">
                    <button type="button" class="btn btn-outline-secondary d-none" id="appDialogCancel">Cancel</button>
                    <button type="button" class="btn btn-primary" id="appDialogOk">OK</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$app_dialog_js = __DIR__ . '/../assets/js/app-dialog.js';
if (is_file($app_dialog_js)):
?>
    <script src="<?= BASE_URL ?>assets/js/app-dialog.js?v=<?= filemtime($app_dialog_js) ?>"></script>
<?php endif; ?>
<?php
require_once __DIR__ . '/ui_feedback.php';
ui_feedback_js();
?>


<!-- ============================================================
     CONSOLIDATED PORTAL JAVASCRIPT
============================================================= -->

<?php if (function_exists('app_theme_js_link')) { app_theme_js_link(); } ?>

<?php if (file_exists($dashboard_js)): ?>

    <script
        src="<?= BASE_URL ?>assets/js/dashboard.js?v=<?= filemtime($dashboard_js) ?>"
        defer
    ></script>

<?php endif; ?>

<?php
$live_filter_js = __DIR__ . '/../assets/js/live-filter.js';
if (is_file($live_filter_js)):
?>
    <script
        src="<?= BASE_URL ?>assets/js/live-filter.js?v=<?= filemtime($live_filter_js) ?>"
        defer
    ></script>
<?php endif; ?>

<?php
$app_rail_js = __DIR__ . '/../assets/js/app-rail.js';
if (is_file($app_rail_js)):
?>
    <script
        src="<?= BASE_URL ?>assets/js/app-rail.js?v=<?= filemtime($app_rail_js) ?>"
        defer
    ></script>
<?php endif; ?>

<?php
if (!function_exists('student_session_guard_script') || !function_exists('student_parent_phone_gate_render')) {
    if (is_file(__DIR__ . '/../student/device_helpers.php')) {
        require_once __DIR__ . '/../student/device_helpers.php';
    }
}
if (function_exists('student_parent_phone_gate_render')) {
    student_parent_phone_gate_render();
}
if (function_exists('student_session_guard_script')) {
    student_session_guard_script();
}
if (function_exists('student_device_emergency_popup_script')) {
    student_device_emergency_popup_script('staff');
}
?>

<?php
$a11y_css = __DIR__ . '/../assets/css/a11y-mobile-v2.css';
if (is_file($a11y_css)):
?>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/a11y-mobile-v2.css?v=<?= filemtime($a11y_css) ?>">
<?php endif; ?>

<?php
if (is_file(__DIR__ . '/visitor_tracking.php')) {
    require_once __DIR__ . '/visitor_tracking.php';
    visitor_tracking_tag();
}
?>

</body>

</html> 