<?php
declare(strict_types=1);
/**
 * Courso AI — personal learning assistant for this student.
 */
$courso = $coursoSnapshot ?? ['profile' => [], 'next_steps' => [], 'classes' => []];
$profile = $courso['profile'] ?? [];
$csrf = function_exists('csrf_token') ? csrf_token() : '';
$api = rtrim((string)BASE_URL, '/') . '/api/courso.php';
?>
<div class="courso" id="coursoApp"
     data-api="<?= student_e($api) ?>"
     data-csrf="<?= student_e($csrf) ?>"
     data-density="<?= student_e((string)($profile['density'] ?? 'comfortable')) ?>">

    <div class="courso-hero">
        <div>
            <div class="sdr-label"><i class="bi bi-stars"></i> Talk with AI</div>
            <h2 class="h3 fw-bold mb-1">Your personal learning assistant</h2>
            <p class="text-muted mb-0">I keep your goals, marks, lessons, and practice in one place — not just one-off answers.</p>
        </div>
        <div class="courso-hero-meta">
            <div><strong id="coursoStreak"><?= (int)($courso['streak'] ?? 0) ?></strong><span>day streak</span></div>
            <div><strong id="coursoLessons"><?= (int)($courso['completed_lessons'] ?? 0) ?></strong><span>lessons (30d)</span></div>
            <div><strong id="coursoQuizzes"><?= (int)($courso['quizzes_done'] ?? 0) ?></strong><span>practice sets</span></div>
        </div>
    </div>

    <nav class="courso-subnav" aria-label="Talk with AI sections">
        <a href="#assistant" class="is-on" data-courso-panel="assistant"><i class="bi bi-chat-heart"></i> Assistant</a>
        <a href="#progress" data-courso-panel="progress"><i class="bi bi-graph-up-arrow"></i> Progress</a>
        <a href="#practice" data-courso-panel="practice"><i class="bi bi-pencil-square"></i> Practice</a>
        <a href="#search" data-courso-panel="search"><i class="bi bi-search"></i> Search</a>
        <a href="#community" data-courso-panel="community"><i class="bi bi-people"></i> Community</a>
        <a href="#prefs" data-courso-panel="prefs"><i class="bi bi-sliders"></i> Preferences</a>
    </nav>

    <section class="courso-panel is-on" id="panel-assistant" data-panel="assistant">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border shadow-sm rounded-4 courso-chat-card">
                    <div class="card-header bg-transparent py-3 fw-bold">Talk with AI</div>
                    <div class="card-body p-0">
                        <p class="small text-muted px-3 pb-0 mb-0 pt-2">Tap thumbs on a reply so the AI learns what a strong answer looks like for you.</p>
                    <div class="courso-log" id="coursoLog" aria-live="polite"></div>
                        <form class="courso-composer" id="coursoChatForm">
                            <label class="visually-hidden" for="coursoMessage">Message</label>
                            <textarea id="coursoMessage" rows="2" maxlength="2000" placeholder="Ask about a topic, a mistake, or what to study next…" required></textarea>
                            <button type="submit" class="btn btn-primary" id="coursoSend">Send</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border shadow-sm rounded-4 mb-3">
                    <div class="card-header bg-transparent py-3 fw-bold">Recommended next steps</div>
                    <div class="list-group list-group-flush" id="coursoSteps">
                        <?php foreach (($courso['next_steps'] ?? []) as $step): ?>
                            <a class="list-group-item list-group-item-action py-3" href="<?= student_e((string)$step['href']) ?>">
                                <div class="fw-semibold"><?= student_e((string)$step['title']) ?></div>
                                <div class="small text-muted"><?= student_e((string)$step['why']) ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="small text-muted mb-0">WhatsApp uses the same memory. Message the college bot about studying and it already knows this snapshot.</p>
            </div>
        </div>
    </section>

    <section class="courso-panel" id="panel-progress" data-panel="progress" hidden>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border shadow-sm rounded-4 h-100">
                    <div class="card-header bg-transparent py-3 fw-bold text-success">Strengths</div>
                    <div class="card-body" id="coursoStrengths">
                        <?php if (!empty($courso['strengths'])): ?>
                            <ul class="mb-0"><?php foreach ($courso['strengths'] as $s): ?><li><?= student_e((string)$s) ?></li><?php endforeach; ?></ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">Marks and quizzes will fill this in. Ask a teacher to add scores, or complete a practice set.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border shadow-sm rounded-4 h-100">
                    <div class="card-header bg-transparent py-3 fw-bold text-warning">Focus areas</div>
                    <div class="card-body" id="coursoWeak">
                        <?php if (!empty($courso['weaknesses'])): ?>
                            <ul class="mb-0"><?php foreach ($courso['weaknesses'] as $s): ?><li><?= student_e((string)$s) ?></li><?php endforeach; ?></ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">No weak spots flagged yet. Practice will start tagging topics you miss.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card border shadow-sm rounded-4">
                    <div class="card-header bg-transparent py-3 fw-bold">Marks by subject</div>
                    <div class="table-responsive">
                        <table class="table mb-0" id="coursoScores">
                            <thead><tr><th>Subject</th><th>Average</th><th>Records</th></tr></thead>
                            <tbody>
                            <?php foreach (($courso['subject_scores'] ?? []) as $row): ?>
                                <tr>
                                    <td><?= student_e((string)$row['subject']) ?></td>
                                    <td><?= (int)$row['percent'] ?>%</td>
                                    <td><?= (int)$row['count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($courso['subject_scores'])): ?>
                                <tr><td colspan="3" class="text-muted">Teachers add marks under Campus → Marks. They appear here automatically.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="courso-panel" id="panel-practice" data-panel="practice" hidden>
        <div class="card border shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap gap-3 align-items-end mb-3">
                    <div class="flex-grow-1">
                        <label class="form-label fw-semibold" for="coursoTopic">Topic (optional)</label>
                        <input class="form-control" id="coursoTopic" maxlength="120" placeholder="e.g. IAL Physics kinematics, quadratic graphs">
                    </div>
                    <button type="button" class="btn btn-primary" id="coursoStartQuiz">Start 5 questions</button>
                </div>
                <p class="small text-muted">Difficulty is <strong id="coursoDiffLabel"><?= student_e((string)($courso['difficulty'] ?? 'core')) ?></strong> from your marks and preference. Each wrong answer gets a why + a real-world example.</p>
                <div id="coursoQuiz" class="courso-quiz"></div>
            </div>
        </div>
    </section>

    <section class="courso-panel" id="panel-search" data-panel="search" hidden>
        <div class="card border shadow-sm rounded-4">
            <div class="card-body p-4">
                <label class="form-label fw-semibold" for="coursoSearch">Search classes, papers, recordings, and teachers</label>
                <input class="form-control form-control-lg" id="coursoSearch" type="search" placeholder="Try ICT, past paper, your teacher’s name…">
                <div class="list-group mt-3" id="coursoSearchHits"></div>
            </div>
        </div>
    </section>

    <section class="courso-panel" id="panel-community" data-panel="community" hidden>
        <div class="card border shadow-sm rounded-4">
            <div class="card-body p-4">
                <label class="form-label fw-semibold" for="coursoClass">Class discussion</label>
                <select class="form-select mb-3" id="coursoClass">
                    <?php foreach (($courso['classes'] ?? []) as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= student_e((string)$c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($courso['classes'])): ?>
                    <p class="text-muted">Join a class first, then you can ask classmates questions here.</p>
                <?php else: ?>
                    <div id="coursoThread" class="courso-thread mb-3"></div>
                    <form id="coursoPostForm" class="d-flex gap-2">
                        <input class="form-control" id="coursoPostBody" maxlength="800" placeholder="Ask a question or share how you solved it…">
                        <button class="btn btn-outline-primary" type="submit">Post</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="courso-panel" id="panel-prefs" data-panel="prefs" hidden>
        <div class="card border shadow-sm rounded-4">
            <div class="card-body p-4">
                <form id="coursoPrefForm" class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="coursoGoals">Exam / learning goals</label>
                        <textarea class="form-control" id="coursoGoals" name="goals" rows="3" maxlength="800" placeholder="e.g. A in IAL Physics in October, then Engineering"><?= student_e((string)($profile['goals'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="coursoInterests">Interests</label>
                        <input class="form-control" id="coursoInterests" name="interests" maxlength="500" value="<?= student_e((string)($profile['interests'] ?? '')) ?>" placeholder="Robotics, accounting, medicine…">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="coursoStyle">AI style</label>
                        <select class="form-select" id="coursoStyle" name="ai_style">
                            <?php foreach (['coach' => 'Coach — next action first', 'tutor' => 'Tutor — worked steps', 'concise' => 'Concise bullets', 'encouraging' => 'Encouraging'] as $k => $label): ?>
                                <option value="<?= $k ?>" <?= (($profile['ai_style'] ?? '') === $k) ? 'selected' : '' ?>><?= student_e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="coursoDifficulty">Practice difficulty</label>
                        <select class="form-select" id="coursoDifficulty" name="difficulty">
                            <?php foreach (['adaptive' => 'Adaptive (from my marks)', 'foundation' => 'Foundation', 'core' => 'Core', 'stretch' => 'Stretch'] as $k => $label): ?>
                                <option value="<?= $k ?>" <?= (($profile['difficulty'] ?? '') === $k) ? 'selected' : '' ?>><?= student_e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="coursoDensity">Layout</label>
                        <select class="form-select" id="coursoDensity" name="density">
                            <option value="comfortable" <?= (($profile['density'] ?? '') === 'comfortable') ? 'selected' : '' ?>>Comfortable</option>
                            <option value="compact" <?= (($profile['density'] ?? '') === 'compact') ? 'selected' : '' ?>>Compact</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="coursoHour">Study reminder hour</label>
                        <input class="form-control" id="coursoHour" name="study_hour" type="number" min="6" max="22" value="<?= (int)($profile['study_hour'] ?? 19) ?>">
                        <div class="form-text">Asia/Colombo. Portal notice (and WhatsApp if reminders are on).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Study days</label>
                        <div class="d-flex flex-wrap gap-2" id="coursoDays">
                            <?php
                            $dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                            $selectedDays = array_map('intval', explode(',', (string)($profile['study_days'] ?? '1,2,3,4,5')));
                            foreach ($dayNames as $i => $name):
                            ?>
                                <label class="btn btn-outline-secondary btn-sm mb-0">
                                    <input type="checkbox" name="study_days" value="<?= $i ?>" class="form-check-input me-1" <?= in_array($i, $selectedDays, true) ? 'checked' : '' ?>>
                                    <?= $name ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="nStudy" <?= !empty($profile['notify_study']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="nStudy">Study-session nudges</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="nStreak" <?= !empty($profile['notify_streak']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="nStreak">Streak reminders</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="nComm" <?= !empty($profile['notify_community']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="nComm">Class discussion notices</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save preferences</button>
                        <span class="small text-muted ms-2" id="coursoPrefStatus"></span>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>
<script src="<?= student_e(rtrim((string)BASE_URL, '/') . '/assets/js/courso.js') ?>?v=<?= is_file(dirname(__DIR__, 3) . '/assets/js/courso.js') ? filemtime(dirname(__DIR__, 3) . '/assets/js/courso.js') : '1' ?>" defer></script>
