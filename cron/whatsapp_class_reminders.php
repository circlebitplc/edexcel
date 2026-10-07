<?php

declare(strict_types=1);

/*
 * ============================================================
 * Edexcel College
 * Automatic WhatsApp Class Reminders
 *
 * 18:00 Sri Lanka time:
 *     Remind groups about tomorrow's classes.
 *
 * 06:00 Sri Lanka time:
 *     Remind groups about today's classes.
 *
 * Intended to run from cron.
 * ============================================================
 */

date_default_timezone_set('Asia/Colombo');

require_once __DIR__ . '/../config/cron_http_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/evolution.php';
require_once __DIR__ . '/../config/whatsapp_gateway.php';
require_once __DIR__ . '/../config/campus.php';
require_once __DIR__ . '/../config/classroom.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Edexcel\Services\WhatsAppSender;


/* ============================================================
   LOGGING
   ============================================================ */

function reminder_log(string $message): void
{
    $line =
        '[' .
        date('Y-m-d H:i:s') .
        '] ' .
        $message .
        PHP_EOL;

    file_put_contents(
        __DIR__ . '/whatsapp_class_reminders.log',
        $line,
        FILE_APPEND | LOCK_EX
    );
}


/* ============================================================
   EVOLUTION API SETTINGS
   ============================================================ */

function evolution_settings(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            setting_key,
            setting_value
        FROM settings
        WHERE setting_key IN (
            'whatsapp_enabled',
            'evolution_api_url',
            'evolution_api_key',
            'evolution_instance'
        )
    ");

    $settings = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        $settings[
            $row['setting_key']
        ] =
            $row['setting_value'];
    }


    return $settings;
}


function reminder_evolution(PDO $pdo): WhatsAppSender
{
    static $api = null;
    if ($api instanceof WhatsAppSender) {
        return $api;
    }

    $api = whatsapp_sender($pdo);
    return $api;
}

function ensure_group_jid_column(PDO $pdo): void
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM class_teacher_whatsapp LIKE 'group_jid'");
        if ($stmt && !$stmt->fetch()) {
            $pdo->exec('ALTER TABLE class_teacher_whatsapp ADD COLUMN group_jid VARCHAR(80) NULL');
        }
    } catch (Throwable $e) {
        reminder_log('Could not ensure group_jid column: ' . $e->getMessage());
    }
}


/* ============================================================
   FIND WHATSAPP GROUP LINK
   ============================================================ */

function find_group_link(
    PDO $pdo,
    int $classId,
    int $teacherId,
    int $subjectId
): ?string {

    /*
     * Exact:
     *
     * class + teacher + subject
     */
    $stmt =
        $pdo->prepare("
            SELECT whatsapp_link
            FROM class_teacher_whatsapp
            WHERE class_id = ?
              AND teacher_id = ?
              AND subject_id = ?
              AND whatsapp_link IS NOT NULL
              AND whatsapp_link <> ''
            ORDER BY id DESC
            LIMIT 1
        ");

    $stmt->execute([
        $classId,
        $teacherId,
        $subjectId
    ]);


    $link =
        $stmt->fetchColumn();


    if (
        is_string($link) &&
        trim($link) !== ''
    ) {

        return trim($link);
    }


    /*
     * Fallback:
     *
     * class + teacher
     */
    $stmt =
        $pdo->prepare("
            SELECT whatsapp_link
            FROM class_teacher_whatsapp
            WHERE class_id = ?
              AND teacher_id = ?
              AND (
                  subject_id IS NULL
                  OR subject_id = 0
              )
              AND whatsapp_link IS NOT NULL
              AND whatsapp_link <> ''
            ORDER BY id DESC
            LIMIT 1
        ");

    $stmt->execute([
        $classId,
        $teacherId
    ]);


    $link =
        $stmt->fetchColumn();


    if (
        is_string($link) &&
        trim($link) !== ''
    ) {

        return trim($link);
    }


    $stmt = $pdo->prepare("
        SELECT whatsapp_link
        FROM student_classes
        WHERE id = ?
          AND deleted_at IS NULL
          AND whatsapp_link IS NOT NULL
          AND whatsapp_link <> ''
        LIMIT 1
    ");
    $stmt->execute([$classId]);
    $link = $stmt->fetchColumn();

    if (is_string($link) && trim($link) !== '') {
        return trim($link);
    }

    return null;
}

function cached_group_jid(PDO $pdo, int $classId, int $teacherId, int $subjectId): ?string
{
    try {
        $stmt = $pdo->prepare("
            SELECT group_jid
            FROM class_teacher_whatsapp
            WHERE class_id = ?
              AND teacher_id = ?
              AND (subject_id = ? OR subject_id IS NULL OR subject_id = 0)
              AND group_jid IS NOT NULL
              AND group_jid <> ''
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$classId, $teacherId, $subjectId]);
        $jid = $stmt->fetchColumn();
        if (is_string($jid) && str_ends_with(trim($jid), '@g.us')) {
            return trim($jid);
        }
    } catch (Throwable $e) {
        // group_jid column may not exist yet
    }

    return null;
}

function store_group_jid(PDO $pdo, int $classId, int $teacherId, string $jid): void
{
    try {
        $stmt = $pdo->prepare("
            UPDATE class_teacher_whatsapp
            SET group_jid = ?
            WHERE class_id = ?
              AND teacher_id = ?
        ");
        $stmt->execute([$jid, $classId, $teacherId]);
    } catch (Throwable $e) {
        // ignore cache failures
    }
}


/* ============================================================
   BUILD MESSAGE
   ============================================================ */

function build_class_message(
    array $entry,
    string $reminderType
): string {

    $date =
        date(
            'l, d F Y',
            strtotime($entry['date'])
        );


    $start =
        date(
            'g:i A',
            strtotime($entry['start_time'])
        );


    $end =
        date(
            'g:i A',
            strtotime($entry['end_time'])
        );

    $place = function_exists('classroom_lesson_place_line')
        ? classroom_lesson_place_line($entry)
        : (string)($entry['room_name'] ?? 'Room');
    $joinLine = '';
    $mode = function_exists('classroom_normalize_delivery_mode')
        ? classroom_normalize_delivery_mode((string)($entry['delivery_mode'] ?? 'physical'))
        : 'physical';
    if (
        in_array($mode, ['online', 'hybrid'], true)
        && function_exists('classroom_public_join_url')
    ) {
        $joinLine = "\n🔗 *Join:* " . classroom_public_join_url(
            (int)$entry['id'],
            (string)($entry['public_id'] ?? '')
        );
    }


    if ($reminderType === 'day_before_6pm') {

        return
            "📚 *Class Reminder*\n\n" .

            "Dear Students,\n\n" .

            "Your *" .
            $entry['subject_name'] .
            "* class is scheduled for *tomorrow*.\n\n" .

            "📅 *Date:* " .
            $date .
            "\n" .

            "🕒 *Time:* " .
            $start .
            " – " .
            $end .
            "\n" .

            "🏫 *Class:* " .
            $entry['class_name'] .
            "\n" .

            "👨‍🏫 *Teacher:* " .
            $entry['teacher_name'] .
            "\n" .

            "🚪 *Place:* " .
            $place .
            $joinLine .

            "\n\nPlease be ready and join the class on time. 📖\n\n" .

            "— *Edexcel College*";

    }


    return
        "🌅 *Good Morning!*\n\n" .

        "Reminder: your *" .
        $entry['subject_name'] .
        "* class is *today*.\n\n" .

        "📅 *Date:* " .
        $date .
        "\n" .

        "🕒 *Time:* " .
        $start .
        " – " .
        $end .
        "\n" .

        "🏫 *Class:* " .
        $entry['class_name'] .
        "\n" .

        "👨‍🏫 *Teacher:* " .
        $entry['teacher_name'] .
        "\n" .

        "🚪 *Place:* " .
        $place .
        $joinLine .

        "\n\nSee you in class! 📚\n\n" .

        "— *Edexcel College*";
}


/* ============================================================
   PROCESS ONE REMINDER
   ============================================================ */

function process_reminder(
    PDO $pdo,
    array $entry,
    string $reminderType,
    string $groupJid,
    string $message,
    string $dryRun
): void {

    /*
     * Duplicate protection.
     */
    $check =
        $pdo->prepare("
            SELECT id
            FROM whatsapp_class_reminders
            WHERE timetable_id = ?
              AND reminder_type = ?
              AND status = 'sent'
            LIMIT 1
        ");

    $check->execute([
        (int)$entry['id'],
        $reminderType
    ]);


    if ($check->fetchColumn()) {

        reminder_log(
            'SKIP duplicate timetable=' .
            $entry['id'] .
            ' type=' .
            $reminderType
        );

        return;
    }


    if ($dryRun === '1') {

        reminder_log(
            'DRY RUN timetable=' .
            $entry['id'] .
            ' group=' .
            $groupJid .
            ' type=' .
            $reminderType
        );

        echo
            "[DRY RUN] " .
            $entry['date'] .
            ' ' .
            $entry['start_time'] .
            ' | ' .
            $entry['class_name'] .
            ' | ' .
            $entry['subject_name'] .
            PHP_EOL;

        return;
    }


    if (whatsapp_provider($pdo) === 'meta' && str_contains($groupJid, '@g.us')) {
        reminder_log(
            'SKIP Meta Cloud API cannot send to WhatsApp groups timetable=' .
            $entry['id'] .
            ' group=' .
            $groupJid
        );
        return;
    }

    try {
        $claim = $pdo->prepare("
            INSERT IGNORE INTO whatsapp_class_reminders
                (timetable_id, reminder_type, class_date, group_jid, message, status)
            VALUES (?, ?, ?, ?, ?, 'sending')
        ");
        $claim->execute([
            (int)$entry['id'],
            $reminderType,
            $entry['date'],
            $groupJid,
            $message,
        ]);
        if ($claim->rowCount() < 1) {
            reminder_log(
                'SKIP claim race timetable=' .
                $entry['id'] .
                ' type=' .
                $reminderType
            );
            return;
        }
    } catch (Throwable $e) {
        reminder_log('SKIP claim: ' . $e->getMessage());
        return;
    }

    try {

        reminder_evolution($pdo)->sendText($groupJid, $message);


        /*
         * Record successful delivery.
         *
         * A previous failed attempt may already have a row
         * with the same timetable_id + reminder_type.
         *
         * Therefore use ON DUPLICATE KEY UPDATE rather than
         * attempting a second INSERT.
         */
        $insert =
            $pdo->prepare("
                INSERT INTO whatsapp_class_reminders
                (
                    timetable_id,
                    reminder_type,
                    class_date,
                    group_jid,
                    message,
                    status,
                    error,
                    sent_at
                )
                VALUES (?, ?, ?, ?, ?, 'sent', NULL, NOW())

                ON DUPLICATE KEY UPDATE
                    class_date = VALUES(class_date),
                    group_jid = VALUES(group_jid),
                    message = VALUES(message),
                    status = 'sent',
                    error = NULL,
                    sent_at = NOW()
            ");

        $insert->execute([
            (int)$entry['id'],
            $reminderType,
            $entry['date'],
            $groupJid,
            $message
        ]);


        reminder_log(
            'SENT timetable=' .
            $entry['id'] .
            ' type=' .
            $reminderType .
            ' group=' .
            $groupJid
        );


        } catch (Throwable $e) {
            try {
                $insert =
                    $pdo->prepare("
                INSERT INTO whatsapp_class_reminders
                (
                    timetable_id,
                    reminder_type,
                    class_date,
                    group_jid,
                    message,
                    status,
                    error,
                    sent_at
                )
                VALUES (?, ?, ?, ?, ?, 'failed', ?, NULL)

                ON DUPLICATE KEY UPDATE
                    class_date = VALUES(class_date),
                    group_jid = VALUES(group_jid),
                    message = VALUES(message),
                    status = 'failed',
                    error = VALUES(error),
                    sent_at = NULL
            ");

                $insert->execute([
                    (int)$entry['id'],
                    $reminderType,
                    $entry['date'],
                    $groupJid,
                    $message,
                    $e->getMessage()
                ]);
            } catch (Throwable $ignore) {
            }

            reminder_log(
                'FAILED timetable=' .
                $entry['id'] .
                ' type=' .
                $reminderType .
                ' error=' .
                $e->getMessage()
            );
        }
}


/* ============================================================
   MAIN
   ============================================================ */

try {

    $settings =
        evolution_settings(
            $pdo
        );


    $enabled =
        filter_var(
            $settings['whatsapp_enabled'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        )
        || evolution_bot_enabled($pdo);


    if (!$enabled) {

        reminder_log(
            'WhatsApp reminders disabled.'
        );

        exit(0);
    }


    ensure_campus_schema($pdo);
    ensure_group_jid_column($pdo);
    if (function_exists('ensure_classroom_schema')) {
        ensure_classroom_schema($pdo);
    }
    reminder_evolution($pdo);


    $now =
        new DateTimeImmutable(
            'now',
            new DateTimeZone(
                'Asia/Colombo'
            )
        );


    $hour =
        (int)$now->format('H');

    $minute =
        (int)$now->format('i');

/*
 * Manual live testing:
 *
 * --evening
 *     Behaves like the 18:00 run and sends tomorrow's
 *     class reminders immediately.
 *
 * --morning
 *     Behaves like the 06:00 run and sends today's
 *     class reminders immediately.
 */
$manualEvening =
    in_array('--evening', $argv ?? [], true);

$manualMorning =
    in_array('--morning', $argv ?? [], true);


    /*
     * Cron normally executes exactly at 06:00 and 18:00.
     * Keep a small safety window so a delayed cron execution
     * still works.
     */
    $isMorning =
        $manualMorning ||
        (
            $hour === 6 &&
            $minute <= 10
        );

    $isEvening =
        $manualEvening ||
        (
            $hour === 18 &&
            $minute <= 10
        );


    if (
        !$isMorning &&
        !$isEvening
    ) {

        reminder_log(
            'Worker executed outside reminder window: ' .
            $now->format('Y-m-d H:i:s')
        );

        exit(0);
    }


    $reminderType =
        $isEvening
            ? 'day_before_6pm'
            : 'class_day_6am';


    $classDate =
        $isEvening
            ? $now
                ->modify('+1 day')
                ->format('Y-m-d')
            : $now->format('Y-m-d');


    /*
     * Do not send reminders for holidays.
     */
    $holiday =
        $pdo->prepare("
            SELECT id
            FROM holidays
            WHERE date = ?
            LIMIT 1
        ");

    $holiday->execute([
        $classDate
    ]);


    if ($holiday->fetchColumn()) {

        reminder_log(
            'Holiday - no reminders for ' .
            $classDate
        );

        exit(0);
    }


    /*
     * Get all active classes for the target date.
     */
    $stmt =
        $pdo->prepare("
            SELECT
                tt.id,
                tt.date,
                tt.start_time,
                tt.end_time,
                tt.class_id,
                tt.teacher_id,
                tt.subject_id,
                tt.room_id,

                COALESCE(
                    sc.name,
                    'Class'
                ) AS class_name,

                COALESCE(
                    s.name,
                    'Subject'
                ) AS subject_name,

                COALESCE(
                    t.name,
                    'Teacher'
                ) AS teacher_name,

                COALESCE(
                    r.name,
                    'Room'
                ) AS room_name,

                tt.delivery_mode,
                om.public_id

            FROM timetable tt

            LEFT JOIN student_classes sc
                ON sc.id = tt.class_id
               AND sc.deleted_at IS NULL

            LEFT JOIN subjects s
                ON s.id = tt.subject_id
               AND s.deleted_at IS NULL

            LEFT JOIN teachers t
                ON t.id = tt.teacher_id
               AND t.deleted_at IS NULL

            LEFT JOIN rooms r
                ON r.id = tt.room_id
               AND r.deleted_at IS NULL

            LEFT JOIN online_meetings om
                ON om.timetable_id = tt.id

            WHERE tt.deleted_at IS NULL
              AND tt.date = ?
              AND COALESCE(tt.lesson_status, 'scheduled') <> 'cancelled'

            ORDER BY
                tt.start_time ASC,
                tt.id ASC
        ");


    $stmt->execute([
        $classDate
    ]);


    $entries =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    reminder_log(
        'Processing ' .
        count($entries) .
        ' classes for ' .
        $classDate .
        ' (' .
        $reminderType .
        ')'
    );


    foreach ($entries as $entry) {

        /*
         * Find existing class WhatsApp group.
         */
        $groupLink =
            find_group_link(
                $pdo,
                (int)$entry['class_id'],
                (int)$entry['teacher_id'],
                (int)$entry['subject_id']
            );


        if (!$groupLink) {

            reminder_log(
                'SKIP no WhatsApp group timetable=' .
                $entry['id']
            );

            continue;
        }


        try {
            $groupJid = cached_group_jid(
                $pdo,
                (int)$entry['class_id'],
                (int)$entry['teacher_id'],
                (int)$entry['subject_id']
            );

            if ($groupJid === null) {
                $groupJid = reminder_evolution($pdo)->resolveGroupJid($groupLink);
                store_group_jid(
                    $pdo,
                    (int)$entry['class_id'],
                    (int)$entry['teacher_id'],
                    $groupJid
                );
            }

            $message = build_class_message($entry, $reminderType);

            process_reminder(
                $pdo,
                $entry,
                $reminderType,
                $groupJid,
                $message,
                '0'
            );
        } catch (Throwable $e) {
            reminder_log(
                'GROUP RESOLUTION FAILED timetable=' .
                $entry['id'] .
                ' error=' .
                $e->getMessage()
            );
        }
    }


    reminder_log(
        'Worker completed.'
    );

    if (!empty($isEvening)) {
        if (!in_array('--evening', $argv ?? [], true)) {
            $argv[] = '--evening';
        }
        reminder_log('Starting parent evening digest.');
        require __DIR__ . '/parent_digest.php';
        reminder_log('Parent evening digest finished.');
    }


} catch (Throwable $e) {

    reminder_log(
        'FATAL: ' .
        $e->getMessage()
    );

    fwrite(
        STDERR,
        $e->getMessage() .
        PHP_EOL
    );

    exit(1);
}
