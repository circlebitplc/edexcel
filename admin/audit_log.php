<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/pagination.php';
require_admin();

$error = '';
$success = '';

// Build filters
$where = [];
$params = [];

if (!empty($_GET['user'])) {
    $where[] = "user_id = ?";
    $params[] = (int)$_GET['user'];
}
if (!empty($_GET['action'])) {
    $where[] = "action = ?";
    $params[] = $_GET['action'];
}
if (!empty($_GET['table'])) {
    $where[] = "table_name = ?";
    $params[] = $_GET['table'];
}
if (!empty($_GET['date_from'])) {
    $where[] = "created_at >= ?";
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to'])) {
    $where[] = "created_at <= ?";
    $params[] = $_GET['date_to'] . ' 23:59:59';
}

// Pagination
$items_per_page = 100;
$page = (int)($_GET['page'] ?? 1);
$page = max(1, $page);
$offset = ($page - 1) * $items_per_page;

// Count total
$count_sql = "SELECT COUNT(*) FROM audit_logs";
if (!empty($where)) {
    $count_sql .= " WHERE " . implode(" AND ", $where);
}
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$pagination = paginate($total_items, $items_per_page, $page);

// Main query
$sql = "SELECT * FROM audit_logs";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
$idx = 1;
foreach ($params as $param) {
    $stmt->bindValue($idx++, $param);
}
$stmt->bindValue($idx++, $items_per_page, PDO::PARAM_INT);
$stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// Get filter dropdown options
$users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll();
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll();
$tables = $pdo->query("SELECT DISTINCT table_name FROM audit_logs ORDER BY table_name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<h1><i class="bi bi-clock-history"></i> Audit Log</h1>

<form method="GET" class="row g-3 mb-4">
    <div class="col-md-2">
        <label for="user" class="form-label">User</label>
        <select id="user" name="user" class="form-select">
            <option value="">All</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= (!empty($_GET['user']) && $_GET['user'] == $u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <label for="action" class="form-label">Action</label>
        <select id="action" name="action" class="form-select">
            <option value="">All</option>
            <?php foreach ($actions as $a): ?>
                <option value="<?= $a['action'] ?>" <?= (!empty($_GET['action']) && $_GET['action'] == $a['action']) ? 'selected' : '' ?>><?= htmlspecialchars($a['action']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <label for="table" class="form-label">Table</label>
        <select id="table" name="table" class="form-select">
            <option value="">All</option>
            <?php foreach ($tables as $t): ?>
                <option value="<?= $t['table_name'] ?>" <?= (!empty($_GET['table']) && $_GET['table'] == $t['table_name']) ? 'selected' : '' ?>><?= htmlspecialchars($t['table_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <label for="date_from" class="form-label">Date From</label>
        <input type="date" id="date_from" name="date_from" class="form-control" value="<?= $_GET['date_from'] ?? '' ?>">
    </div>
    <div class="col-md-2">
        <label for="date_to" class="form-label">Date To</label>
        <input type="date" id="date_to" name="date_to" class="form-control" value="<?= $_GET['date_to'] ?? '' ?>">
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Filter</button>
        <a href="audit_log.php" class="btn btn-secondary w-100 ms-1"><i class="bi bi-arrow-clockwise"></i> Reset</a>
    </div>
</form>

<div class="mb-2">
    <small class="text-muted">Showing <?= count($logs) ?> of <?= number_format($total_items) ?> entries</small>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-hover table-sm">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Action</th>
                <th>Table</th>
                <th>Record ID</th>
                <th>Details</th>
                <th>IP Address</th>
                <th>Date/Time</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($logs) === 0): ?>
                <tr><td colspan="8" class="text-center">No logs found.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $log['id'] ?></td>
                        <td><?= htmlspecialchars($log['username']) ?></td>
                        <td><span class="badge bg-info"><?= htmlspecialchars($log['action']) ?></span></td>
                        <td><?= htmlspecialchars($log['table_name']) ?></td>
                        <td><?= $log['record_id'] ?: '-' ?></td>
                        <td>
                            <?php
                            $details = [];
                            if ($log['old_values']) {
                                $old = json_decode($log['old_values'], true);
                                if ($old) $details[] = 'Old: ' . json_encode($old);
                            }
                            if ($log['new_values']) {
                                $new = json_decode($log['new_values'], true);
                                if ($new) $details[] = 'New: ' . json_encode($new);
                            }
                            echo $details ? implode(' | ', $details) : '-';
                            ?>
                        </td>
                        <td><?= htmlspecialchars($log['ip_address'] ?: '-') ?></td>
                        <td><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= render_pagination($pagination, $_GET) ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>