<?php
// includes/notifications.php
// Functions to get dashboard notifications

/* ============================================================
 * UPCOMING LESSONS
 * ============================================================ */

function get_upcoming_lessons($pdo, $teacher_id = null)
{
    $now = date('Y-m-d H:i:s');
    $tomorrow = date(
        'Y-m-d H:i:s',
        strtotime('+24 hours')
    );

    $params = [
        $now,
        $tomorrow
    ];

    $sql = "
        SELECT
            t.*,
            tc.name AS teacher_name,
            s.name AS subject_name,
            c.name AS class_name,
            r.name AS room_name

        FROM timetable t

        JOIN teachers tc
            ON t.teacher_id = tc.id

        JOIN subjects s
            ON t.subject_id = s.id

        JOIN student_classes c
            ON t.class_id = c.id

        LEFT JOIN rooms r
            ON t.room_id = r.id

        WHERE
            CONCAT(t.date, ' ', t.start_time) >= ?
            AND CONCAT(t.date, ' ', t.start_time) < ?
    ";

    if ($teacher_id) {

        $sql .= "
            AND t.teacher_id = ?
        ";

        $params[] = $teacher_id;
    }

    $sql .= "
        AND t.deleted_at IS NULL

        ORDER BY
            t.date ASC,
            t.start_time ASC

        LIMIT 10
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/* ============================================================
 * TIMETABLE CONFLICTS
 * ============================================================ */

function get_conflicts_next_week($pdo)
{
    // Check for overlapping entries in the next 7 days

    $start = date('Y-m-d');

    $end = date(
        'Y-m-d',
        strtotime('+7 days')
    );

    $stmt = $pdo->prepare("
        SELECT
            t1.*,

            t2.id AS conflict_id,

            tc1.name AS teacher1,
            tc2.name AS teacher2,

            r1.name AS room1,
            r2.name AS room2,

            c1.name AS class1,
            c2.name AS class2

        FROM timetable t1

        JOIN timetable t2
            ON t1.id < t2.id

        JOIN teachers tc1
            ON t1.teacher_id = tc1.id

        JOIN teachers tc2
            ON t2.teacher_id = tc2.id

        JOIN rooms r1
            ON t1.room_id = r1.id

        JOIN rooms r2
            ON t2.room_id = r2.id

        JOIN student_classes c1
            ON t1.class_id = c1.id

        JOIN student_classes c2
            ON t2.class_id = c2.id

        WHERE
            t1.date BETWEEN ? AND ?
            AND t2.date BETWEEN ? AND ?

            AND (
                t1.teacher_id = t2.teacher_id
                OR t1.room_id = t2.room_id
                OR t1.class_id = t2.class_id
            )

            AND (
                t1.start_time < t2.end_time
                AND t1.end_time > t2.start_time
            )

            AND t1.deleted_at IS NULL
            AND t2.deleted_at IS NULL

        LIMIT 20
    ");

    $stmt->execute([
        $start,
        $end,
        $start,
        $end
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/* ============================================================
 * TEACHER NOTIFICATIONS
 *
 * Source table:
 *     teacher_notifications
 *
 * Notifications are private to the teacher specified by
 * teacher_id.
 *
 * Global announcements can optionally use teacher_id = NULL.
 * ============================================================ */


/**
 * Get teacher notifications.
 *
 * @param PDO      $pdo
 * @param int      $teacher_id
 * @param int      $limit
 * @param bool     $include_read
 *
 * @return array
 */
function get_teacher_notifications(
    $pdo,
    $teacher_id,
    $limit = 10,
    $include_read = true
) {
    $teacher_id = (int)$teacher_id;

    if ($teacher_id <= 0) {
        return [];
    }

    /*
     * Keep LIMIT as an integer rather than a bound parameter.
     */
    $limit = max(
        1,
        min(50, (int)$limit)
    );

    if ($include_read) {

        $sql = "
            SELECT
                id,
                teacher_id,
                title,
                message,
                type,
                link,
                is_read,
                created_at

            FROM teacher_notifications

            WHERE
                teacher_id = ?
                OR teacher_id IS NULL

            ORDER BY
                is_read ASC,
                created_at DESC

            LIMIT {$limit}
        ";

    } else {

        $sql = "
            SELECT
                id,
                teacher_id,
                title,
                message,
                type,
                link,
                is_read,
                created_at

            FROM teacher_notifications

            WHERE
                (
                    teacher_id = ?
                    OR teacher_id IS NULL
                )

                AND is_read = 0

            ORDER BY
                created_at DESC

            LIMIT {$limit}
        ";
    }

    try {

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $teacher_id
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {

        error_log(
            'Failed to load teacher notifications: ' .
            $e->getMessage()
        );

        return [];
    }
}


/**
 * Get unread teacher notification count.
 *
 * @param PDO $pdo
 * @param int $teacher_id
 *
 * @return int
 */
function get_teacher_notification_count(
    $pdo,
    $teacher_id
) {
    $teacher_id = (int)$teacher_id;

    if ($teacher_id <= 0) {
        return 0;
    }

    try {

        $stmt = $pdo->prepare("
            SELECT COUNT(*)

            FROM teacher_notifications

            WHERE
                (
                    teacher_id = ?
                    OR teacher_id IS NULL
                )

                AND is_read = 0
        ");

        $stmt->execute([
            $teacher_id
        ]);

        return (int)$stmt->fetchColumn();

    } catch (Throwable $e) {

        error_log(
            'Failed to count teacher notifications: ' .
            $e->getMessage()
        );

        return 0;
    }
}


/**
 * Mark one teacher notification as read.
 *
 * IMPORTANT:
 * The teacher_id condition prevents one teacher from
 * modifying another teacher's notification.
 *
 * @param PDO $pdo
 * @param int $notification_id
 * @param int $teacher_id
 *
 * @return bool
 */
function mark_teacher_notification_read(
    $pdo,
    $notification_id,
    $teacher_id
) {
    $notification_id = (int)$notification_id;
    $teacher_id = (int)$teacher_id;

    if (
        $notification_id <= 0 ||
        $teacher_id <= 0
    ) {
        return false;
    }

    try {

        $stmt = $pdo->prepare("
            UPDATE teacher_notifications

            SET
                is_read = 1

            WHERE
                id = ?
                AND (
                    teacher_id = ?
                    OR teacher_id IS NULL
                )
        ");

        $stmt->execute([
            $notification_id,
            $teacher_id
        ]);

        return true;

    } catch (Throwable $e) {

        error_log(
            'Failed to mark teacher notification as read: ' .
            $e->getMessage()
        );

        return false;
    }
}


/**
 * Mark all teacher notifications as read.
 *
 * @param PDO $pdo
 * @param int $teacher_id
 *
 * @return bool
 */
function mark_all_teacher_notifications_read(
    $pdo,
    $teacher_id
) {
    $teacher_id = (int)$teacher_id;

    if ($teacher_id <= 0) {
        return false;
    }

    try {

        $stmt = $pdo->prepare("
            UPDATE teacher_notifications

            SET
                is_read = 1

            WHERE
                (
                    teacher_id = ?
                    OR teacher_id IS NULL
                )

                AND is_read = 0
        ");

        $stmt->execute([
            $teacher_id
        ]);

        return true;

    } catch (Throwable $e) {

        error_log(
            'Failed to mark all teacher notifications as read: ' .
            $e->getMessage()
        );

        return false;
    }
}


/**
 * Create an in-dashboard notification for a teacher.
 *
 * This is intentionally separate from WhatsApp notifications.
 *
 * @param PDO         $pdo
 * @param int         $teacher_id
 * @param string      $title
 * @param string|null $message
 * @param string      $type
 * @param string|null $link
 *
 * @return bool
 */
function create_teacher_dashboard_notification(
    $pdo,
    $teacher_id,
    $title,
    $message = null,
    $type = 'info',
    $link = null
) {
    $teacher_id = (int)$teacher_id;

    $title = trim(
        (string)$title
    );

    $message = $message !== null
        ? trim((string)$message)
        : null;

    $type = trim(
        (string)$type
    );

    $link = $link !== null
        ? trim((string)$link)
        : null;

    if (
        $teacher_id <= 0 ||
        $title === ''
    ) {
        return false;
    }

    /*
     * Only allow known notification types.
     */
    $allowedTypes = [
        'info',
        'success',
        'warning',
        'danger',
        'announcement',
        'payment',
        'class'
    ];

    if (!in_array($type, $allowedTypes, true)) {
        $type = 'info';
    }

    try {

        /*
         * Avoid creating an identical notification repeatedly
         * within a short period.
         */
        $duplicate = $pdo->prepare("
            SELECT id

            FROM teacher_notifications

            WHERE
                teacher_id = ?
                AND title = ?
                AND COALESCE(message, '') = COALESCE(?, '')
                AND created_at >= DATE_SUB(
                    NOW(),
                    INTERVAL 5 MINUTE
                )

            LIMIT 1
        ");

        $duplicate->execute([
            $teacher_id,
            $title,
            $message
        ]);

        if ($duplicate->fetchColumn()) {
            return true;
        }

        $stmt = $pdo->prepare("
            INSERT INTO teacher_notifications
            (
                teacher_id,
                title,
                message,
                type,
                link
            )

            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmt->execute([
            $teacher_id,
            $title,
            $message,
            $type,
            $link
        ]);

        return true;

    } catch (Throwable $e) {

        error_log(
            'Failed to create teacher dashboard notification: ' .
            $e->getMessage()
        );

        return false;
    }
}