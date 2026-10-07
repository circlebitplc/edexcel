<?php
declare(strict_types=1);

namespace Edexcel\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Official Pearson/Edexcel exam timetable + per-student planner.
 * Countdown is always computed in Asia/Colombo (Sri Lanka Standard Time).
 */
final class OfficialExamService
{
    public const DISPLAY_TZ = 'Asia/Colombo';

    public function __construct(private PDO $pdo)
    {
    }

    public function ensureSchema(): void
    {
        if ($this->pdo->inTransaction()) {
            return;
        }
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS exam_series (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                year SMALLINT NOT NULL,
                session VARCHAR(40) NOT NULL,
                qualification_type VARCHAR(20) NOT NULL,
                timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Colombo',
                morning_start TIME NOT NULL DEFAULT '09:00:00',
                afternoon_start TIME NOT NULL DEFAULT '13:30:00',
                source VARCHAR(255) NULL,
                source_url VARCHAR(500) NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_exam_series (year, session, qualification_type),
                KEY idx_exam_series_year (year, session)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS exams (
                id INT PRIMARY KEY AUTO_INCREMENT,
                exam_series_id INT NOT NULL,
                subject VARCHAR(150) NOT NULL,
                subject_code VARCHAR(40) NULL,
                unit_code VARCHAR(40) NOT NULL,
                paper_code VARCHAR(20) NOT NULL DEFAULT '',
                unit_title VARCHAR(255) NOT NULL,
                exam_date DATE NOT NULL,
                session VARCHAR(20) NULL,
                start_time TIME NOT NULL,
                end_time TIME NULL,
                duration_minutes INT NULL,
                timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Colombo',
                source VARCHAR(255) NULL,
                notes TEXT NULL,
                extra_json JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_exam_paper (exam_series_id, unit_code, paper_code, exam_date),
                KEY idx_exams_series_date (exam_series_id, exam_date, start_time),
                KEY idx_exams_subject (subject),
                KEY idx_exams_date (exam_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS student_exam_selections (
                id INT PRIMARY KEY AUTO_INCREMENT,
                student_id INT NOT NULL,
                exam_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_student_exam_selection (student_id, exam_id),
                KEY idx_student_exam_sel_student (student_id),
                KEY idx_student_exam_sel_exam (exam_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * True for one hour after the exam catalogue was confirmed non-empty.
     * Skips CREATE TABLE and COUNT(*) on ordinary student page views.
     */
    public function catalogIsReady(): bool
    {
        $path = $this->catalogFlagPath();
        if (!is_file($path)) {
            return false;
        }
        $age = time() - (int)@filemtime($path);
        return $age >= 0 && $age < 3600;
    }

    public function seedIfEmpty(): int
    {
        if ($this->catalogIsReady()) {
            return 0;
        }
        try {
            $count = (int)$this->pdo->query('SELECT COUNT(*) FROM exam_series')->fetchColumn();
        } catch (Throwable $e) {
            $this->ensureSchema();
            $count = 0;
        }
        if ($count > 0) {
            $examCount = (int)$this->pdo->query('SELECT COUNT(*) FROM exams')->fetchColumn();
            if ($examCount > 0) {
                $this->markCatalogReady();
                return 0;
            }
        }
        $inserted = $this->importFromSeed();
        if ($inserted > 0) {
            $this->markCatalogReady();
        }
        return $inserted;
    }

    private function catalogFlagPath(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'exam_catalog_ready';
    }

    private function markCatalogReady(): void
    {
        $path = $this->catalogFlagPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @touch($path);
    }

    public function seedPath(): string
    {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeds' . DIRECTORY_SEPARATOR;
        $php = $dir . 'official_exams.php';
        if (is_file($php)) {
            return $php;
        }
        return $dir . 'official_exams.json';
    }

    public function importFromSeed(): int
    {
        $path = $this->seedPath();
        if (!is_file($path)) {
            throw new RuntimeException('Official exam seed file is missing.');
        }
        if (str_ends_with(strtolower($path), '.php')) {
            if (!defined('EDX_LOAD_EXAM_SEED')) {
                define('EDX_LOAD_EXAM_SEED', true);
            }
            $payload = require $path;
        } else {
            $json = file_get_contents($path);
            if ($json === false || trim($json) === '') {
                throw new RuntimeException('Official exam seed file could not be read.');
            }
            $payload = json_decode($json, true);
        }
        if (!is_array($payload)) {
            throw new RuntimeException('Official exam seed file is not valid JSON.');
        }
        return $this->importSeriesPayload($payload);
    }

    /**
     * @param list<array<string,mixed>> $payload
     */
    public function importSeriesPayload(array $payload): int
    {
        $this->ensureSchema();
        $inserted = 0;
        $started = !$this->pdo->inTransaction();
        if ($started) {
            $this->pdo->beginTransaction();
        }
        try {
        $seriesSql = $this->pdo->prepare("
            INSERT INTO exam_series
                (name, year, session, qualification_type, timezone, morning_start, afternoon_start, source, source_url, notes)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                timezone = VALUES(timezone),
                morning_start = VALUES(morning_start),
                afternoon_start = VALUES(afternoon_start),
                source = VALUES(source),
                source_url = VALUES(source_url),
                notes = VALUES(notes)
        ");
        $examSql = $this->pdo->prepare("
            INSERT INTO exams
                (exam_series_id, subject, subject_code, unit_code, paper_code, unit_title,
                 exam_date, session, start_time, end_time, duration_minutes, timezone, source, notes)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                subject = VALUES(subject),
                subject_code = VALUES(subject_code),
                unit_title = VALUES(unit_title),
                session = VALUES(session),
                start_time = VALUES(start_time),
                end_time = VALUES(end_time),
                duration_minutes = VALUES(duration_minutes),
                timezone = VALUES(timezone),
                source = VALUES(source),
                notes = VALUES(notes)
        ");

        foreach ($payload as $series) {
            if (!is_array($series)) {
                continue;
            }
            $name = trim((string)($series['series_name'] ?? $series['name'] ?? ''));
            $year = (int)($series['year'] ?? 0);
            $session = trim((string)($series['session'] ?? ''));
            $qual = strtoupper(trim((string)($series['qualification_type'] ?? '')));
            if ($name === '' || $year < 2000 || $session === '' || $qual === '') {
                continue;
            }
            $timezone = trim((string)($series['timezone'] ?? self::DISPLAY_TZ)) ?: self::DISPLAY_TZ;
            $morning = $this->normalizeTime((string)($series['morning_start'] ?? '09:00:00'), '09:00:00');
            $afternoon = $this->normalizeTime((string)($series['afternoon_start'] ?? '13:30:00'), '13:30:00');
            $source = trim((string)($series['source'] ?? '')) ?: null;
            $sourceUrl = trim((string)($series['source_url'] ?? '')) ?: null;
            $seriesSql->execute([
                $name,
                $year,
                $session,
                $qual,
                $timezone,
                $morning,
                $afternoon,
                $source,
                $sourceUrl,
                null,
            ]);
            $seriesId = $this->findSeriesId($year, $session, $qual);
            if ($seriesId < 1) {
                continue;
            }
            foreach (($series['exams'] ?? []) as $exam) {
                if (!is_array($exam)) {
                    continue;
                }
                $unitCode = trim((string)($exam['unit_code'] ?? ''));
                $examDate = trim((string)($exam['exam_date'] ?? ''));
                if ($unitCode === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $examDate)) {
                    continue;
                }
                $paper = trim((string)($exam['paper_code'] ?? ''));
                $start = $this->normalizeTime((string)($exam['start_time'] ?? ''), $morning);
                $duration = isset($exam['duration_minutes']) ? (int)$exam['duration_minutes'] : null;
                $end = trim((string)($exam['end_time'] ?? ''));
                if ($end === '' && $duration !== null && $duration > 0) {
                    $end = $this->addMinutes($start, $duration);
                }
                $end = $end !== '' ? $this->normalizeTime($end, $end) : null;
                $examSql->execute([
                    $seriesId,
                    trim((string)($exam['subject'] ?? 'Subject')) ?: 'Subject',
                    trim((string)($exam['subject_code'] ?? '')) ?: null,
                    $unitCode,
                    $paper,
                    trim((string)($exam['unit_title'] ?? $unitCode)) ?: $unitCode,
                    $examDate,
                    trim((string)($exam['session'] ?? '')) ?: null,
                    $start,
                    $end,
                    $duration,
                    trim((string)($exam['timezone'] ?? $timezone)) ?: $timezone,
                    $source,
                    trim((string)($exam['notes'] ?? '')) ?: null,
                ]);
                $inserted++;
            }
        }

        if ($started) {
            $this->pdo->commit();
        }
        return $inserted;
        } catch (Throwable $e) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function findSeriesId(int $year, string $session, string $qualificationType): int
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM exam_series
            WHERE year = ? AND session = ? AND qualification_type = ?
            LIMIT 1
        ");
        $stmt->execute([$year, $session, strtoupper($qualificationType)]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listSeries(): array
    {
        $sql = "
            SELECT s.*,
                   (SELECT COUNT(*) FROM exams e WHERE e.exam_series_id = s.id) AS exam_count,
                   (SELECT MIN(e.exam_date) FROM exams e WHERE e.exam_series_id = s.id) AS first_exam,
                   (SELECT MAX(e.exam_date) FROM exams e WHERE e.exam_series_id = s.id) AS last_exam
            FROM exam_series s
            ORDER BY s.year ASC, FIELD(s.session, 'January', 'May/June', 'October', 'November') ASC, s.qualification_type ASC
        ";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function defaultSeriesId(?array $series = null): int
    {
        $series = $series ?? $this->listSeries();
        $today = (new DateTimeImmutable('now', new DateTimeZone(self::DISPLAY_TZ)))->format('Y-m-d');
        foreach ($series as $row) {
            $last = (string)($row['last_exam'] ?? '');
            if ($last !== '' && $last >= $today) {
                return (int)$row['id'];
            }
        }
        return (int)($series[0]['id'] ?? 0);
    }

    /**
     * @return list<string>
     */
    public function listSubjects(int $seriesId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT subject FROM exams
            WHERE exam_series_id = ?
            ORDER BY subject
        ");
        $stmt->execute([$seriesId]);
        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listExams(int $seriesId, string $search = '', string $subject = ''): array
    {
        $sql = "
            SELECT e.*, s.name AS series_name, s.qualification_type, s.session AS series_session, s.year AS series_year
            FROM exams e
            INNER JOIN exam_series s ON s.id = e.exam_series_id
            WHERE e.exam_series_id = ?
        ";
        $params = [$seriesId];
        if ($subject !== '') {
            $sql .= " AND e.subject = ?";
            $params[] = $subject;
        }
        if ($search !== '') {
            $sql .= " AND (
                e.subject LIKE ? OR e.subject_code LIKE ? OR e.unit_code LIKE ?
                OR e.paper_code LIKE ? OR e.unit_title LIKE ?
            )";
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $sql .= " ORDER BY e.subject, e.exam_date, e.start_time, e.unit_code";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map([$this, 'decorateExam'], $rows);
    }

    /**
     * @return list<int>
     */
    public function selectedExamIds(int $studentId): array
    {
        $stmt = $this->pdo->prepare("SELECT exam_id FROM student_exam_selections WHERE student_id = ?");
        $stmt->execute([$studentId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function studentTimetable(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, s.name AS series_name, s.qualification_type, s.session AS series_session,
                   s.year AS series_year, sel.created_at AS selected_at
            FROM student_exam_selections sel
            INNER JOIN exams e ON e.id = sel.exam_id
            INNER JOIN exam_series s ON s.id = e.exam_series_id
            WHERE sel.student_id = ?
            ORDER BY e.exam_date ASC, e.start_time ASC, e.subject ASC
        ");
        $stmt->execute([$studentId]);
        $rows = array_map([$this, 'decorateExam'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        return $this->attachClashes($this->attachStudyDays($rows));
    }

    public function addSelection(int $studentId, int $examId): void
    {
        if ($studentId < 1 || $examId < 1) {
            throw new RuntimeException('Invalid exam selection.');
        }
        $exists = $this->pdo->prepare("SELECT id FROM exams WHERE id = ? LIMIT 1");
        $exists->execute([$examId]);
        if (!$exists->fetchColumn()) {
            throw new RuntimeException('That exam is not in the official timetable.');
        }
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO student_exam_selections (student_id, exam_id) VALUES (?, ?)
        ");
        $stmt->execute([$studentId, $examId]);
    }

    /**
     * @param list<int> $examIds
     */
    public function addSelections(int $studentId, array $examIds): int
    {
        $added = 0;
        foreach (array_unique(array_map('intval', $examIds)) as $examId) {
            if ($examId < 1) {
                continue;
            }
            $before = $this->pdo->prepare("SELECT COUNT(*) FROM student_exam_selections WHERE student_id = ? AND exam_id = ?");
            $before->execute([$studentId, $examId]);
            $had = (int)$before->fetchColumn() > 0;
            $this->addSelection($studentId, $examId);
            if (!$had) {
                $added++;
            }
        }
        return $added;
    }

    public function removeSelection(int $studentId, int $examId): void
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM student_exam_selections WHERE student_id = ? AND exam_id = ?
        ");
        $stmt->execute([$studentId, $examId]);
    }

    public function createSeries(array $data): int
    {
        $name = trim((string)($data['name'] ?? ''));
        $year = (int)($data['year'] ?? 0);
        $session = trim((string)($data['session'] ?? ''));
        $qual = strtoupper(trim((string)($data['qualification_type'] ?? '')));
        if ($name === '' || $year < 2000 || $session === '' || !in_array($qual, ['IGCSE', 'IAL'], true)) {
            throw new RuntimeException('Series name, year, session and qualification are required.');
        }
        $stmt = $this->pdo->prepare("
            INSERT INTO exam_series
                (name, year, session, qualification_type, timezone, morning_start, afternoon_start, source, source_url, notes)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name,
            $year,
            $session,
            $qual,
            trim((string)($data['timezone'] ?? self::DISPLAY_TZ)) ?: self::DISPLAY_TZ,
            $this->normalizeTime((string)($data['morning_start'] ?? '09:00:00'), '09:00:00'),
            $this->normalizeTime((string)($data['afternoon_start'] ?? '13:30:00'), '13:30:00'),
            trim((string)($data['source'] ?? '')) ?: null,
            trim((string)($data['source_url'] ?? '')) ?: null,
            trim((string)($data['notes'] ?? '')) ?: null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function saveExam(array $data, int $id = 0): int
    {
        $seriesId = (int)($data['exam_series_id'] ?? 0);
        $subject = trim((string)($data['subject'] ?? ''));
        $unitCode = trim((string)($data['unit_code'] ?? ''));
        $unitTitle = trim((string)($data['unit_title'] ?? ''));
        $examDate = trim((string)($data['exam_date'] ?? ''));
        if ($seriesId < 1 || $subject === '' || $unitCode === '' || $unitTitle === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $examDate)) {
            throw new RuntimeException('Series, subject, unit code, title and date are required.');
        }
        $series = $this->pdo->prepare("SELECT * FROM exam_series WHERE id = ? LIMIT 1");
        $series->execute([$seriesId]);
        $seriesRow = $series->fetch(PDO::FETCH_ASSOC);
        if (!$seriesRow) {
            throw new RuntimeException('Exam series not found.');
        }
        $session = trim((string)($data['session'] ?? ''));
        $start = trim((string)($data['start_time'] ?? ''));
        if ($start === '') {
            $start = strcasecmp($session, 'Afternoon') === 0
                ? (string)$seriesRow['afternoon_start']
                : (string)$seriesRow['morning_start'];
        }
        $start = $this->normalizeTime($start, '09:00:00');
        $duration = (int)($data['duration_minutes'] ?? 0);
        $end = trim((string)($data['end_time'] ?? ''));
        if ($end === '' && $duration > 0) {
            $end = $this->addMinutes($start, $duration);
        }
        $fields = [
            $seriesId,
            $subject,
            trim((string)($data['subject_code'] ?? '')) ?: null,
            $unitCode,
            trim((string)($data['paper_code'] ?? '')),
            $unitTitle,
            $examDate,
            $session !== '' ? $session : null,
            $start,
            $end !== '' ? $this->normalizeTime($end, $end) : null,
            $duration > 0 ? $duration : null,
            trim((string)($data['timezone'] ?? $seriesRow['timezone'])) ?: self::DISPLAY_TZ,
            trim((string)($data['source'] ?? $seriesRow['source'] ?? '')) ?: null,
            trim((string)($data['notes'] ?? '')) ?: null,
        ];
        if ($id > 0) {
            $sql = "
                UPDATE exams SET
                    exam_series_id=?, subject=?, subject_code=?, unit_code=?, paper_code=?, unit_title=?,
                    exam_date=?, session=?, start_time=?, end_time=?, duration_minutes=?, timezone=?, source=?, notes=?
                WHERE id=?
            ";
            $this->pdo->prepare($sql)->execute([...$fields, $id]);
            return $id;
        }
        $sql = "
            INSERT INTO exams
                (exam_series_id, subject, subject_code, unit_code, paper_code, unit_title,
                 exam_date, session, start_time, end_time, duration_minutes, timezone, source, notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ";
        $this->pdo->prepare($sql)->execute($fields);
        return (int)$this->pdo->lastInsertId();
    }

    public function deleteExam(int $id): void
    {
        $this->pdo->prepare("DELETE FROM student_exam_selections WHERE exam_id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$id]);
    }

    public function findExam(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, s.name AS series_name, s.qualification_type
            FROM exams e
            INNER JOIN exam_series s ON s.id = e.exam_series_id
            WHERE e.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decorateExam($row) : null;
    }

    /**
     * @param array<string,mixed> $exam
     * @return array<string,mixed>
     */
    public function decorateExam(array $exam): array
    {
        $exam['paper_label'] = self::paperLabel($exam);
        $exam['display_title'] = trim((string)$exam['subject'] . ' — ' . (string)$exam['unit_title'], ' —');
        $when = $this->examDateTime($exam);
        $exam['starts_at'] = $when?->format('c');
        $exam['starts_at_sl'] = $when?->setTimezone(new DateTimeZone(self::DISPLAY_TZ))->format('Y-m-d H:i:s');
        $cd = $this->countdown($exam);
        $exam['countdown'] = $cd;
        $exam['countdown_label'] = $cd['label'];
        $exam['is_past'] = $cd['past'];
        $exam['date_label'] = $this->formatDate((string)$exam['exam_date']);
        $exam['weekday_label'] = $this->formatWeekday((string)$exam['exam_date']);
        $exam['time_label'] = $this->formatTime((string)$exam['start_time']);
        $exam['end_time_label'] = !empty($exam['end_time']) ? $this->formatTime((string)$exam['end_time']) : '';
        return $exam;
    }

    public static function paperLabel(array $exam): string
    {
        $unit = trim((string)($exam['unit_code'] ?? ''));
        $paper = trim((string)($exam['paper_code'] ?? ''));
        return $paper !== '' ? $unit . ' ' . $paper : $unit;
    }

    public static function fullStudyDays(string $fromDate, string $toDate): int
    {
        try {
            $from = new DateTimeImmutable($fromDate);
            $to = new DateTimeImmutable($toDate);
        } catch (Throwable $e) {
            return 0;
        }
        $days = (int)$from->diff($to)->days;
        if ($to < $from) {
            return 0;
        }
        return max(0, $days - 1);
    }

    /**
     * @param list<array<string,mixed>> $exams
     * @return list<array<string,mixed>>
     */
    public function attachStudyDays(array $exams): array
    {
        $count = count($exams);
        for ($i = 0; $i < $count; $i++) {
            $exams[$i]['study_days_after'] = null;
            if ($i < $count - 1) {
                $exams[$i]['study_days_after'] = self::fullStudyDays(
                    (string)$exams[$i]['exam_date'],
                    (string)$exams[$i + 1]['exam_date']
                );
            }
        }
        return $exams;
    }

    /**
     * @param list<array<string,mixed>> $exams
     * @return list<array<string,mixed>>
     */
    public function attachClashes(array $exams): array
    {
        foreach ($exams as $i => $_) {
            $exams[$i]['has_clash'] = false;
            $exams[$i]['clash_with'] = [];
        }
        $count = count($exams);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ((string)$exams[$i]['exam_date'] !== (string)$exams[$j]['exam_date']) {
                    continue;
                }
                if (!$this->windowsOverlap($exams[$i], $exams[$j])) {
                    continue;
                }
                $exams[$i]['has_clash'] = true;
                $exams[$j]['has_clash'] = true;
                $exams[$i]['clash_with'][] = (string)$exams[$j]['paper_label'];
                $exams[$j]['clash_with'][] = (string)$exams[$i]['paper_label'];
            }
        }
        return $exams;
    }

    /**
     * @param list<array<string,mixed>> $enrolledClasses
     * @param list<string> $officialSubjects
     * @return list<string>
     */
    public static function matchingSubjects(array $enrolledClasses, array $officialSubjects): array
    {
        $blob = ' ';
        foreach ($enrolledClasses as $class) {
            $blob .= strtolower((string)($class['class_name'] ?? '') . ' ' . (string)($class['subject_name'] ?? '') . ' ');
        }
        $blob = ' ' . trim((string)preg_replace('/[^a-z0-9]+/i', ' ', $blob)) . ' ';
        $aliases = [
            'maths' => 'mathematics',
            'math' => 'mathematics',
            'chem' => 'chemistry',
            'phy' => 'physics',
            'bio' => 'biology',
            'econ' => 'economics',
            'accounting' => 'accounting',
            'business' => 'business',
            'ict' => 'information',
        ];
        $matched = [];
        foreach ($officialSubjects as $subject) {
            $norm = strtolower((string)preg_replace('/[^a-z0-9]+/i', ' ', $subject) ?? $subject);
            $hit = $norm !== '' && str_contains($blob, ' ' . trim($norm) . ' ');
            foreach (explode(' ', $norm) as $token) {
                if (strlen($token) >= 5 && str_contains($blob, ' ' . $token . ' ')) {
                    $hit = true;
                }
            }
            foreach ($aliases as $alias => $canon) {
                if (str_contains($blob, ' ' . $alias . ' ') && (str_contains($norm, $canon) || str_contains($norm, $alias))) {
                    $hit = true;
                }
            }
            if ($hit) {
                $matched[] = $subject;
            }
        }
        return $matched;
    }

    public function addSubjectPapers(int $studentId, int $seriesId, string $subject): int
    {
        $subject = trim($subject);
        if ($studentId < 1 || $seriesId < 1 || $subject === '') {
            throw new RuntimeException('Subject and series are required.');
        }
        $stmt = $this->pdo->prepare("SELECT id FROM exams WHERE exam_series_id = ? AND subject = ?");
        $stmt->execute([$seriesId, $subject]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        if ($ids === []) {
            throw new RuntimeException('No papers found for that subject in this series.');
        }
        return $this->addSelections($studentId, $ids);
    }

    /**
     * @param list<array<string,mixed>> $exams
     */
    public function icsCalendar(array $exams): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Edexcel College//Exam Planner//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Edexcel exam timetable',
            'X-WR-TIMEZONE:Asia/Colombo',
        ];
        foreach ($exams as $exam) {
            $start = $this->examDateTime($exam);
            if ($start === null) {
                continue;
            }
            $startUtc = $start->setTimezone(new DateTimeZone('UTC'));
            $endUtc = $startUtc;
            if (!empty($exam['end_time'])) {
                try {
                    $endLocal = new DateTimeImmutable(
                        (string)$exam['exam_date'] . ' ' . (string)$exam['end_time'],
                        new DateTimeZone((string)($exam['timezone'] ?? self::DISPLAY_TZ))
                    );
                    $endUtc = $endLocal->setTimezone(new DateTimeZone('UTC'));
                } catch (Throwable $e) {
                    $endUtc = $startUtc->modify('+90 minutes');
                }
            } elseif (!empty($exam['duration_minutes'])) {
                $endUtc = $startUtc->modify('+' . (int)$exam['duration_minutes'] . ' minutes');
            } else {
                $endUtc = $startUtc->modify('+90 minutes');
            }
            $summary = $this->icsText((string)($exam['display_title'] ?? $exam['subject'] ?? 'Exam'));
            $desc = $this->icsText(trim(
                (string)($exam['paper_label'] ?? '') . ' · ' .
                (string)($exam['series_name'] ?? '') . ' · Sri Lanka time'
            ));
            $uidHost = 'edexcel.college';
            if (function_exists('edexcel_public_app_url')) {
                $parsedHost = parse_url(rtrim(edexcel_public_app_url(), '/') . '/', PHP_URL_HOST);
                if (is_string($parsedHost) && $parsedHost !== '') {
                    $uidHost = strtolower($parsedHost);
                }
            }
            $uid = 'exam-' . (int)$exam['id'] . '@' . $uidHost;
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . $startUtc->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $endUtc->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:' . $summary;
            $lines[] = 'DESCRIPTION:' . $desc;
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines) . "\r\n";
    }

    public function whatsappTimetable(int $studentId, int $limit = 8): string
    {
        $rows = array_values(array_filter(
            $this->studentTimetable($studentId),
            static fn(array $row): bool => empty($row['is_past'])
        ));
        if ($rows === []) {
            return '';
        }
        $out = "📝 *Your official exam timetable*\n_(Sri Lanka time)_\n\n";
        foreach (array_slice($rows, 0, $limit) as $row) {
            $out .= '*' . (string)$row['subject'] . '* — ' . (string)$row['unit_title'] . "\n";
            $out .= (string)$row['paper_label'] . "\n";
            $out .= (string)$row['date_label'] . ' — ' . (string)$row['time_label'] . "\n";
            $out .= (string)$row['countdown_label'] . "\n";
            if (!empty($row['has_clash'])) {
                $out .= "⚠ Clash with " . implode(', ', $row['clash_with']) . "\n";
            }
            $out .= "\n";
        }
        return trim($out);
    }

    /**
     * @return array{past:bool,total_seconds:int,days:int,hours:int,minutes:int,label:string}
     */
    public function countdown(array $exam): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone(self::DISPLAY_TZ));
        $when = $this->examDateTime($exam);
        if ($when === null) {
            return [
                'past' => false,
                'total_seconds' => 0,
                'days' => 0,
                'hours' => 0,
                'minutes' => 0,
                'label' => 'Time to be confirmed',
            ];
        }
        $when = $when->setTimezone(new DateTimeZone(self::DISPLAY_TZ));
        $seconds = $when->getTimestamp() - $now->getTimestamp();
        if ($seconds <= 0) {
            return [
                'past' => true,
                'total_seconds' => $seconds,
                'days' => 0,
                'hours' => 0,
                'minutes' => 0,
                'label' => 'Exam has started',
            ];
        }
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $parts = [];
        if ($days > 0) {
            $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
        }
        if ($hours > 0 || $days > 0) {
            $parts[] = $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
        }
        $parts[] = $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes');
        return [
            'past' => false,
            'total_seconds' => $seconds,
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
            'label' => implode(', ', $parts) . ' remaining',
        ];
    }

    public function examDateTime(array $exam): ?DateTimeImmutable
    {
        $date = trim((string)($exam['exam_date'] ?? ''));
        $time = trim((string)($exam['start_time'] ?? '00:00:00'));
        if ($date === '') {
            return null;
        }
        if (strlen($time) === 5) {
            $time .= ':00';
        }
        $tzName = trim((string)($exam['timezone'] ?? self::DISPLAY_TZ)) ?: self::DISPLAY_TZ;
        try {
            $tz = new DateTimeZone($tzName);
        } catch (Throwable $e) {
            $tz = new DateTimeZone(self::DISPLAY_TZ);
        }
        try {
            return new DateTimeImmutable($date . ' ' . $time, $tz);
        } catch (Throwable $e) {
            return null;
        }
    }

    public function formatDate(string $date): string
    {
        try {
            return (new DateTimeImmutable($date))->format('l, j F Y');
        } catch (Throwable $e) {
            return $date;
        }
    }

    public function formatTime(string $time): string
    {
        try {
            return (new DateTimeImmutable('2000-01-01 ' . $time))->format('g:i A');
        } catch (Throwable $e) {
            return $time;
        }
    }

    public function formatWeekday(string $date): string
    {
        try {
            return (new DateTimeImmutable($date))->format('l');
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * @param array<string,mixed> $a
     * @param array<string,mixed> $b
     */
    private function windowsOverlap(array $a, array $b): bool
    {
        $aStart = $this->minutesOfDay((string)($a['start_time'] ?? '00:00:00'));
        $bStart = $this->minutesOfDay((string)($b['start_time'] ?? '00:00:00'));
        $aEnd = !empty($a['end_time'])
            ? $this->minutesOfDay((string)$a['end_time'])
            : $aStart + max(1, (int)($a['duration_minutes'] ?? 90));
        $bEnd = !empty($b['end_time'])
            ? $this->minutesOfDay((string)$b['end_time'])
            : $bStart + max(1, (int)($b['duration_minutes'] ?? 90));
        return $aStart < $bEnd && $bStart < $aEnd;
    }

    private function minutesOfDay(string $time): int
    {
        $parts = explode(':', $time . ':00:00');
        return ((int)$parts[0] * 60) + (int)$parts[1];
    }

    private function icsText(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(["\r\n", "\n", "\r"], '\\n', $value);
        return str_replace([';', ','], ['\\;', '\\,'], $value);
    }

    private function normalizeTime(string $value, string $fallback): string
    {
        $value = trim($value);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m)) {
            return sprintf('%02d:%02d:%02d', (int)$m[1], (int)$m[2], (int)($m[3] ?? 0));
        }
        return $fallback;
    }

    private function addMinutes(string $time, int $minutes): string
    {
        try {
            return (new DateTimeImmutable('2000-01-01 ' . $time))
                ->modify('+' . $minutes . ' minutes')
                ->format('H:i:s');
        } catch (Throwable $e) {
            return $time;
        }
    }
}
