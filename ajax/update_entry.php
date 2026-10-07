<?php
declare(strict_types=1);

/*
 * ajax/update_entry.php
 *
 * AJAX endpoint for editing a timetable lesson.
 *
 * Supports:
 * - Student count
 * - Class fee per student / session
 * - Teacher / subject / class / room
 * - Date and time
 * - Payment status for admins
 * - Weekly repetition
 *
 * Teachers can only edit their own lessons.
 */

require_once __DIR__ . '/../config/bootstrap.php';

require_staff();

header('Content-Type: application/json; charset=utf-8');

try {

    /* ============================================================
       REQUEST METHOD
       ============================================================ */

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('POST required.');
    }


    /* ============================================================
       CSRF
       ============================================================ */

    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? ''
        )
    ) {
        throw new RuntimeException(
            'Invalid security token.'
        );
    }


    /* ============================================================
       LESSON ID
       ============================================================ */

    $id = (int)(
        $_POST['id'] ?? 0
    );

    if ($id <= 0) {
        throw new RuntimeException(
            'Invalid lesson ID.'
        );
    }


    /* ============================================================
       USER / ROLE
       ============================================================ */

    $isAdmin =
        is_admin();

    $sessionTeacher =
        (int)(
            $_SESSION['teacher_id'] ?? 0
        );


    if (
        !$isAdmin &&
        $sessionTeacher <= 0
    ) {
        throw new RuntimeException(
            'Your teacher account is not linked to a teacher profile.'
        );
    }


    /* ============================================================
       LOAD CURRENT LESSON
       ============================================================ */

    $services =
        TimetableServiceFactory::services($pdo);

    $current =
        $services['repository']->find($id);


    if (
        !$current ||
        !empty($current['deleted_at'])
    ) {
        throw new RuntimeException(
            'Lesson not found.'
        );
    }


    /* ============================================================
       PERMISSION CHECK
       ============================================================ */

    if (
        !$isAdmin &&
        (int)($current['teacher_id'] ?? 0)
            !== $sessionTeacher
    ) {
        throw new RuntimeException(
            'You can only edit your own lessons.'
        );
    }


    /* ============================================================
       TEACHER
       ============================================================ */

    if ($isAdmin) {

        $teacherId =
            (int)(
                $_POST['teacher_id'] ?? 0
            );

    } else {

        /*
         * Never trust teacher_id sent by a teacher.
         */
        $teacherId =
            $sessionTeacher;
    }


    if ($teacherId <= 0) {
        throw new RuntimeException(
            'Please select a valid teacher.'
        );
    }


    /* ============================================================
       SUBJECT / CLASS / ROOM
       ============================================================ */

    $subjectId =
        (int)(
            $_POST['subject_id'] ?? 0
        );

    $classId =
        (int)(
            $_POST['class_id'] ?? 0
        );

    $roomId =
        (int)(
            $_POST['room_id'] ?? 0
        );


    if ($subjectId <= 0) {
        throw new RuntimeException(
            'Please select a subject.'
        );
    }

    if ($classId <= 0) {
        throw new RuntimeException(
            'Please select a class.'
        );
    }

    if ($roomId <= 0) {
        throw new RuntimeException(
            'Please select a room.'
        );
    }


    /* ============================================================
       STUDENT COUNT
       ============================================================ */

    $studentCount =
        (int)(
            $_POST['student_count'] ?? 0
        );


    if ($studentCount < 0) {
        throw new RuntimeException(
            'Student count cannot be negative.'
        );
    }


    /* ============================================================
       CLASS FEE
       ============================================================ */

    $classFee =
        (float)(
            $_POST['class_fee_per_student'] ?? 0
        );


    if ($classFee < 0) {
        throw new RuntimeException(
            'Class fee per student cannot be negative.'
        );
    }


    /*
     * Store the fee to two decimal places.
     */
    $classFee =
        round($classFee, 2);


    /* ============================================================
       DATE
       ============================================================ */

    $date =
        substr(
            trim(
                (string)(
                    $_POST['date'] ?? ''
                )
            ),
            0,
            10
        );


    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $date
        )
    ) {
        throw new RuntimeException(
            'Invalid lesson date.'
        );
    }


    /* ============================================================
       TIME
       ============================================================ */

    $startTime =
        substr(
            trim(
                (string)(
                    $_POST['start_time'] ?? ''
                )
            ),
            0,
            5
        );

    $endTime =
        substr(
            trim(
                (string)(
                    $_POST['end_time'] ?? ''
                )
            ),
            0,
            5
        );


    if (
        !preg_match(
            '/^\d{2}:\d{2}$/',
            $startTime
        )
    ) {
        throw new RuntimeException(
            'Invalid start time.'
        );
    }

    if (
        !preg_match(
            '/^\d{2}:\d{2}$/',
            $endTime
        )
    ) {
        throw new RuntimeException(
            'Invalid end time.'
        );
    }


    if ($startTime >= $endTime) {
        throw new RuntimeException(
            'Start time must be before end time.'
        );
    }


    /* ============================================================
       PAYMENT STATUS
       ============================================================ */

    if ($isAdmin) {

        $paymentStatus =
            (
                ($_POST['payment_status'] ?? 'pending')
                === 'paid'
            )
                ? 'paid'
                : 'pending';

    } else {

        /*
         * Teachers cannot change payment status
         * through the AJAX editor.
         */
        $paymentStatus =
            $current['payment_status'] ?? 'pending';
    }


    /* ============================================================
       PAYMENT DATE
       ============================================================ */

    if ($paymentStatus === 'paid') {

        if (
            ($current['payment_status'] ?? '')
                === 'paid'
            &&
            !empty($current['payment_date'])
        ) {

            $paymentDate =
                $current['payment_date'];

        } else {

            $paymentDate =
                date('Y-m-d');
        }

    } else {

        $paymentDate =
            null;
    }


    /* ============================================================
       REPEAT WEEKLY
       ============================================================ */

    $repeat =
        !empty(
            $_POST['repeat_weekly']
        );

    $repeatUntil =
        trim(
            (string)(
                $_POST['repeat_until'] ?? ''
            )
        );


    if ($repeat) {

        if ($repeatUntil === '') {
            throw new RuntimeException(
                'Please specify the repeat end date.'
            );
        }


        if (
            !preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $repeatUntil
            )
        ) {
            throw new RuntimeException(
                'Invalid repeat end date.'
            );
        }


        if ($repeatUntil < $date) {
            throw new RuntimeException(
                'Repeat until date must be on or after the lesson date.'
            );
        }
    }


    /* ============================================================
       DATA TO UPDATE
       ============================================================ */

    $data = [

        'teacher_id' =>
            $teacherId,

        'subject_id' =>
            $subjectId,

        'class_id' =>
            $classId,

        'room_id' =>
            $roomId,

        'student_count' =>
            $studentCount,

        /*
         * IMPORTANT:
         * This is the amount the teacher charges
         * each student for this particular session.
         */
        'class_fee_per_student' =>
            $classFee,

        'date' =>
            $date,

        'start_time' =>
            $startTime,

        'end_time' =>
            $endTime,

        'payment_status' =>
            $paymentStatus,

        'payment_date' =>
            $paymentDate,

        'delivery_mode' =>
            classroom_normalize_delivery_mode((string)($_POST['delivery_mode'] ?? $current['delivery_mode'] ?? 'physical')),
    ];


    /* ============================================================
       UPDATE
       ============================================================ */

    $updatedRecurringFeeRows = $services['update']->update(

        $id,

        $data,

        static function (
            array $entry
        ) use (
            $isAdmin,
            $sessionTeacher
        ): void {

            /*
             * Final server-side ownership check.
             */
            if (
                !$isAdmin &&
                (int)(
                    $entry['teacher_id'] ?? 0
                ) !== $sessionTeacher
            ) {
                throw new RuntimeException(
                    'You can only edit your own lessons.'
                );
            }
        },

        $repeat,

        $repeatUntil
    );


    /* ============================================================
       SUCCESS
       ============================================================ */

    $smsNotice = null;
    if (
        $isAdmin
        && (($current['payment_status'] ?? '') !== 'paid')
        && $paymentStatus === 'paid'
    ) {
        try {
            $sms = \Edexcel\Services\TeacherPaymentSmsService::notifyTimetablePaid(
                $pdo,
                $id,
                (int)($_SESSION['user_id'] ?? 0),
                false
            );
            $smsNotice = 'Payment marked as PAID. ' . (string)($sms['sms_notice'] ?? 'Payment SMS could not be sent.');
        } catch (Throwable $smsError) {
            error_log('Lesson edit payment SMS failed: ' . $smsError->getMessage());
            $smsNotice = 'Payment marked as PAID. Payment SMS could not be sent.';
        }
    }

    echo json_encode(
        [
            'success' => true,
            'id' => $id,
            'student_count' => $studentCount,
            'class_fee_per_student' => $classFee,
            'payment_status' => $paymentStatus,
            'updated_recurring_fee_rows' => $updatedRecurringFeeRows,
            'sms_notice' => $smsNotice,
        ],
        JSON_UNESCAPED_UNICODE
    );


} catch (Throwable $e) {

    error_log(
        'AJAX timetable update error: ' .
        $e->getMessage()
    );

    http_response_code(400);

    echo json_encode(
        [
            'success' => false,
            'error' => $e->getMessage(),
        ],
        JSON_UNESCAPED_UNICODE
    );
}