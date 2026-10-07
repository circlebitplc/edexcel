<?php
declare(strict_types=1);

use Edexcel\Services\LessonResultBuilder;
use Edexcel\Services\OnlineLessonService;

/** @var array<string,mixed> $detail */
/** @var callable $h */
$detailMarks = static function (?float $got, ?float $max): string {
    if ($got === null || $max === null) {
        return '—';
    }
    return LessonResultBuilder::formatMark($got) . ' / ' . LessonResultBuilder::formatMark($max);
};
$outcomeText = match ((string)($detail['outcome'] ?? '')) {
    'pass' => 'Pass',
    'fail' => 'Fail',
    'pending' => 'Pending',
    default => '',
};
?>
<section class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body">
        <p class="text-muted small mb-1">Student result</p>
        <h2 class="h4 mb-1"><?= $h((string)$detail['name']) ?></h2>
        <p class="mb-1">Overall: <strong><?= $h($detailMarks($detail['obtained'], $detail['counted_max'])) ?></strong>
            <?php if ($detail['percent'] !== null): ?>
                · <?= $h(LessonResultBuilder::formatPercent((float)$detail['percent'])) ?>
            <?php endif; ?>
        </p>
        <p class="mb-1">Progress: <?= (int)$detail['progress'] ?>% · <?= (int)$detail['items_done'] ?> / <?= (int)$detail['items_total'] ?> items</p>
        <p class="mb-1">Time spent: <?= $h(OnlineLessonService::formatActiveTime((int)$detail['active_seconds'])) ?></p>
        <p class="mb-0">Last activity: <?= $detail['last_seen_at'] !== '' ? $h(date('d M Y, g:i A', strtotime((string)$detail['last_seen_at']))) : 'Never' ?>
            <?php if ($outcomeText !== ''): ?> · <?= $h($outcomeText) ?><?php endif; ?>
        </p>
        <?php if ((float)$detail['lesson_max'] > 0 && $detail['counted_max'] !== null && abs((float)$detail['counted_max'] - (float)$detail['lesson_max']) > 0.001): ?>
            <p class="small text-muted mt-2 mb-0">Marked so far. Unsubmitted or unmarked work is not included in this total. The full lesson is <?= $h(LessonResultBuilder::formatMark((float)$detail['lesson_max'])) ?> marks.</p>
        <?php endif; ?>
    </div>
</section>
<div class="row g-3 mb-4">
    <?php foreach ($detail['activities'] as $activity): ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= $h((string)$activity['kind']) ?></div>
                    <h3 class="h6"><?= $h((string)$activity['title']) ?></h3>
                    <?php if (!empty($activity['academic'])): ?>
                        <?php if (($activity['status'] ?? '') === 'not_submitted'): ?>
                            <p class="mb-1">Not submitted</p>
                        <?php elseif (($activity['status'] ?? '') === 'pending' && $activity['obtained'] === null): ?>
                            <p class="mb-1">Pending marking</p>
                        <?php else: ?>
                            <p class="mb-1"><?= $h($detailMarks($activity['obtained'], $activity['counted_max'])) ?>
                                <?php if ($activity['percent'] !== null): ?> · <?= $h(LessonResultBuilder::formatPercent((float)$activity['percent'])) ?><?php endif; ?>
                            </p>
                            <?php if (($activity['status'] ?? '') === 'pending'): ?>
                                <p class="mb-1">Part of this activity is still waiting for a teacher mark.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (trim((string)($activity['feedback'] ?? '')) !== ''): ?>
                            <p class="mb-1">Feedback: <?= nl2br($h((string)$activity['feedback'])) ?></p>
                        <?php endif; ?>
                        <?php if ((int)$activity['mcq_questions'] > 0 && ($activity['status'] ?? '') !== 'not_submitted'): ?>
                            <p class="mb-1"><?= (int)$activity['mcq_questions'] ?> questions · <?= (int)$activity['mcq_correct'] ?> correct · <?= (int)$activity['mcq_incorrect'] ?> incorrect</p>
                        <?php endif; ?>
                        <?php if ((int)$activity['attempts'] > 0): ?>
                            <p class="mb-1">Attempts: <?= (int)$activity['attempts'] ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="mb-1"><?= ($activity['status'] ?? '') === 'completed' ? 'Completed' : 'Not completed' ?></p>
                        <?php if ($activity['watch_percent'] !== null): ?>
                            <p class="mb-1">Watched <?= (int)$activity['watch_percent'] ?>%</p>
                        <?php endif; ?>
                        <?php if ((string)$activity['type'] === 'external_link'): ?>
                            <p class="mb-1"><?= (int)$activity['opens'] > 0 ? 'Link opened' : 'Link not opened' ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <p class="mb-0">Time: <?= $h(OnlineLessonService::formatActiveTime((int)$activity['active_seconds'])) ?></p>
                    <?php
                    $ticks = array_values(array_filter($activity['questions'], static fn (array $question): bool => ($question['type'] ?? '') === 'mcq' && $question['correct'] !== null));
                    if ($ticks !== []):
                    ?>
                        <p class="small mt-2 mb-0">
                            <?php foreach ($ticks as $tick): ?>
                                <span class="me-2">Q<?= (int)$tick['n'] ?> <?= !empty($tick['correct']) ? 'correct' : 'incorrect' ?></span>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
