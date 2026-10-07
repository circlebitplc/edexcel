<?php
declare(strict_types=1);
/**
 * views/home/timetable_search.php
 * Public Homepage Timetable Search & Schedule Matrix
 */
?>
<section id="timetable-section" class="mb-5 pt-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="bi bi-calendar-week me-2 text-primary"></i>Live Class Schedule</h2>
            <p class="text-muted mb-0">Search and filter active classes scheduled at Edexcel College.</p>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
        <form method="GET" action="#timetable-section" class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Keyword Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control bg-light border-start-0" placeholder="Subject, teacher, room..." value="<?= e($searchQuery ?? '') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Filter by Teacher</label>
                <select name="teacher_id" class="form-select bg-light">
                    <option value="">All Teachers</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= ($filterTeacherId === (int)$t['id']) ? 'selected' : '' ?>>
                            <?= e($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Filter by Day</label>
                <select name="day" class="form-select bg-light">
                    <option value="">All Days</option>
                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $d): ?>
                        <option value="<?= $d ?>" <?= ($filterDay === $d) ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3 shadow-xs">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Timetable Schedule Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Day & Time</th>
                        <th>Subject & Class</th>
                        <th>Instructor</th>
                        <th>Room</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($timetableEntries): ?>
                        <?php foreach ($timetableEntries as $entry): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= e(date('l', strtotime($entry['date']))) ?></div>
                                    <div class="small text-muted font-monospace">
                                        <?= e(date('g:i A', strtotime($entry['start_time']))) ?> - <?= e(date('g:i A', strtotime($entry['end_time']))) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($entry['subject_name']) ?></div>
                                    <span class="badge bg-primary-subtle text-primary small"><?= e($entry['class_name']) ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle d-grid place-items-center text-white fw-bold small" style="width:32px;height:32px;background:<?= e(teacherAvatarColor($entry['teacher_name'])) ?>;">
                                            <?= e(teacherInitials($entry['teacher_name'])) ?>
                                        </div>
                                        <span class="text-dark fw-medium"><?= e($entry['teacher_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <i class="bi bi-door-open me-1"></i><?= e($entry['room_name']) ?>
                                    </span>
                                </td>
                                <td class="pe-4 text-end">
                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#studentLoginModal">
                                        Join Class
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2 text-muted"></i>
                                No scheduled classes match your criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
