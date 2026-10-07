<?php
declare(strict_types=1);

/**
 * Admin Phone Contacts Import History
 *
 * Displays historical records of all CSV contact imports with detailed metrics
 * and error CSV reports.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\PhoneContactService;

require_admin();

PhoneContactService::ensureSchema($pdo);

$page_title = 'Phone Contact Import History';
$current_page = 'phone_import_history.php';

// Fetch import history
$stmt = $pdo->query(
    "SELECT pih.*, u.username as uploader_username 
     FROM phone_import_history pih
     LEFT JOIN users u ON u.id = pih.uploaded_by
     ORDER BY pih.id DESC
     LIMIT 200"
);
$imports = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4 phone-import-history-shell">
    <!-- Breadcrumb & Top Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/settings.php">Admin</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/phone_contacts.php">Phone Contacts</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Import History</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Contact Import History</h1>
            <p class="text-muted small mb-0">Audit log of all CSV uploads, new contacts created, duplicates skipped, and validation reports.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>admin/phone_contacts.php" class="btn btn-outline-secondary btn-sm">
                ← Back to Phone Contacts
            </a>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="window.location.href='<?= BASE_URL ?>admin/phone_contacts.php#import'">
                <i class="bi bi-file-earmark-arrow-up me-1"></i> New CSV Import
            </button>
        </div>
    </div>

    <!-- History Table Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="card-title mb-0 fw-bold"><i class="bi bi-list-columns me-2 text-primary"></i>All Recorded Imports</h6>
            <span class="badge bg-light text-dark border"><?= count($imports) ?> recorded imports</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Import Date</th>
                            <th>File Name</th>
                            <th>WhatsApp Group / Source</th>
                            <th>Uploaded By</th>
                            <th class="text-end">Total Rows</th>
                            <th class="text-end">New Contacts</th>
                            <th class="text-end">New Records</th>
                            <th class="text-end">Duplicates Skipped</th>
                            <th class="text-end">Errors</th>
                            <th class="text-center">Error Report</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($imports)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                                No CSV contact imports recorded yet.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($imports as $imp): ?>
                            <tr>
                                <td class="small text-muted">
                                    <i class="bi bi-calendar3 me-1"></i><?= date('d M Y, H:i', strtotime((string)$imp['created_at'])) ?>
                                </td>
                                <td>
                                    <strong class="text-dark font-monospace small"><i class="bi bi-file-earmark-text text-primary me-1"></i><?= e($imp['filename']) ?></strong>
                                </td>
                                <td>
                                    <?php if (!empty($imp['source_group'])): ?>
                                        <span class="badge bg-light text-dark border"><?= e($imp['source_group']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?= e($imp['uploader_username'] ?: 'Admin') ?>
                                </td>
                                <td class="text-end fw-semibold">
                                    <?= number_format((int)$imp['total_rows']) ?>
                                </td>
                                <td class="text-end text-primary fw-bold">
                                    +<?= number_format((int)$imp['new_contacts']) ?>
                                </td>
                                <td class="text-end text-success fw-bold">
                                    +<?= number_format((int)$imp['new_records']) ?>
                                </td>
                                <td class="text-end text-warning fw-semibold">
                                    <?= number_format((int)$imp['duplicate_records']) ?>
                                </td>
                                <td class="text-end <?= (int)$imp['invalid_rows'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                                    <?= number_format((int)$imp['invalid_rows']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($imp['error_csv_path']) && (int)$imp['invalid_rows'] > 0): ?>
                                        <a href="<?= BASE_URL ?><?= e($imp['error_csv_path']) ?>" class="btn btn-outline-danger btn-sm py-0 px-2" download title="Download CSV of skipped rows with reasons">
                                            <i class="bi bi-download me-1"></i> Error CSV
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-success bg-opacity-25 text-success border border-success small">None</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
