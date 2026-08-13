<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/payment.php';

require_admin();


/* ============================================================
   REVENUE DATA
   Only lessons dated today or earlier are included.
============================================================ */

$sql = "
    SELECT
        t.id,
        t.name,

        COALESCE(
            SUM(tbl.student_count),
            0
        ) AS total_students,

        COALESCE(
            SUM(
                tbl.student_count * (
    CASE
        WHEN (
            CASE
                WHEN TIME_TO_SEC(tbl.end_time) >= TIME_TO_SEC(tbl.start_time)
                THEN (TIME_TO_SEC(tbl.end_time) - TIME_TO_SEC(tbl.start_time)) / 60
                ELSE (TIME_TO_SEC(tbl.end_time) + 86400 - TIME_TO_SEC(tbl.start_time)) / 60
            END
        ) <= 150 THEN 500
        WHEN (
            CASE
                WHEN TIME_TO_SEC(tbl.end_time) >= TIME_TO_SEC(tbl.start_time)
                THEN (TIME_TO_SEC(tbl.end_time) - TIME_TO_SEC(tbl.start_time)) / 60
                ELSE (TIME_TO_SEC(tbl.end_time) + 86400 - TIME_TO_SEC(tbl.start_time)) / 60
            END
        ) <= 210 THEN 700
        WHEN (
            CASE
                WHEN TIME_TO_SEC(tbl.end_time) >= TIME_TO_SEC(tbl.start_time)
                THEN (TIME_TO_SEC(tbl.end_time) - TIME_TO_SEC(tbl.start_time)) / 60
                ELSE (TIME_TO_SEC(tbl.end_time) + 86400 - TIME_TO_SEC(tbl.start_time)) / 60
            END
        ) <= 270 THEN 900
        ELSE 1100
    END
)
            ),
            0
        ) AS total_revenue,

        COALESCE(
            SUM(
                CASE
                    WHEN tbl.payment_status = 'paid'
                    THEN tbl.student_count * (
    CASE
        WHEN (
            CASE
                WHEN TIME_TO_SEC(tbl.end_time) >= TIME_TO_SEC(tbl.start_time)
                THEN (TIME_TO_SEC(tbl.end_time) - TIME_TO_SEC(tbl.start_time)) / 60
                ELSE (TIME_TO_SEC(tbl.end_time) + 86400 - TIME_TO_SEC(tbl.start_time)) / 60
            END
        ) <= 150 THEN 500
        WHEN (
            CASE
                WHEN TIME_TO_SEC(tbl.end_time) >= TIME_TO_SEC(tbl.start_time)
                THEN (TIME_TO_SEC(tbl.end_time) - TIME_TO_SEC(tbl.start_time)) / 60
                ELSE (TIME_TO_SEC(tbl.end_time) + 86400 - TIME_TO_SEC(tbl.start_time)) / 60
            END
        ) <= 210 THEN 700
        WHEN (
            CASE
                WHEN TIME_TO_SEC(tbl.end_time) >= TIME_TO_SEC(tbl.start_time)
                THEN (TIME_TO_SEC(tbl.end_time) - TIME_TO_SEC(tbl.start_time)) / 60
                ELSE (TIME_TO_SEC(tbl.end_time) + 86400 - TIME_TO_SEC(tbl.start_time)) / 60
            END
        ) <= 270 THEN 900
        ELSE 1100
    END
)
                    ELSE 0
                END
            ),
            0
        ) AS paid_revenue,

        COALESCE(
            SUM(
                CASE
                    WHEN tbl.payment_status = 'pending'
                    THEN tbl.student_count * (
    CASE
        WHEN (
            CASE
                WHEN TIME_TO_SEC(tbl.end_time) >= TIME_TO_SEC(tbl.start_time)
                THEN (TIME_TO_SEC(tbl.end_time) - TIME_TO_SEC(tbl.start_time)) / 60
                ELSE (TIME_TO_SEC(tbl.end_time) + 86400 - TIME_TO_SEC(tbl.start_time)) / 60
            END
        ) <= 150 THEN 500
        WHEN (
            CASE
                WHEN TIME_TO_SEC(tbl.end_time) >= TIME_TO_SEC(tbl.start_time)
                THEN (TIME_TO_SEC(tbl.end_time) - TIME_TO_SEC(tbl.start_time)) / 60
                ELSE (TIME_TO_SEC(tbl.end_time) + 86400 - TIME_TO_SEC(tbl.start_time)) / 60
            END
        ) <= 210 THEN 700
        WHEN (
            CASE
                WHEN TIME_TO_SEC(tbl.end_time) >= TIME_TO_SEC(tbl.start_time)
                THEN (TIME_TO_SEC(tbl.end_time) - TIME_TO_SEC(tbl.start_time)) / 60
                ELSE (TIME_TO_SEC(tbl.end_time) + 86400 - TIME_TO_SEC(tbl.start_time)) / 60
            END
        ) <= 270 THEN 900
        ELSE 1100
    END
)
                    ELSE 0
                END
            ),
            0
        ) AS pending_revenue,

        COUNT(tbl.id) AS lessons

    FROM teachers t

    LEFT JOIN timetable tbl
        ON t.id = tbl.teacher_id

        AND tbl.deleted_at IS NULL

        /*
         * IMPORTANT:
         * Do not include future classes.
         */
        AND tbl.date <= CURDATE()

    WHERE
        t.deleted_at IS NULL

    GROUP BY
        t.id

    ORDER BY
        total_revenue DESC,
        t.name ASC
";


$stmt = $pdo->prepare($sql);

$stmt->execute([]);


$teachers = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);


/* ============================================================
   GRAND TOTALS
============================================================ */

$grandTotal =
    array_sum(
        array_column(
            $teachers,
            'total_revenue'
        )
    );


$grandPaid =
    array_sum(
        array_column(
            $teachers,
            'paid_revenue'
        )
    );


$grandPending =
    array_sum(
        array_column(
            $teachers,
            'pending_revenue'
        )
    );


$grandStudents =
    array_sum(
        array_column(
            $teachers,
            'total_students'
        )
    );


$grandLessons =
    array_sum(
        array_column(
            $teachers,
            'lessons'
        )
    );


$paidPct =
    $grandTotal > 0
        ? round(
            (
                $grandPaid
                /
                $grandTotal
            ) * 100,
            1
        )
        : 0;


/* ============================================================
   CURRENT REPORT DATE
============================================================ */

$reportDate =
    date(
        'd M Y'
    );


include __DIR__ .
    '/../includes/header.php';

?>

<style>

/* ============================================================
   REVENUE REPORT PAGE
============================================================ */

.revenue-report-page {
    width: 100%;
    max-width: 1500px;
    margin: 0 auto;
    padding: 28px 30px 50px;
}


/* ============================================================
   PAGE HEADER
============================================================ */

.revenue-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 30px;

    margin-bottom: 30px;
}


.revenue-page-title {
    min-width: 0;
}


.revenue-page-title h1 {
    display: flex;
    align-items: center;
    gap: 12px;

    margin: 0 0 8px;

    font-size: 34px;
    line-height: 1.2;
    font-weight: 800;
}


.revenue-page-title h1 i {
    font-size: 34px;
}


.revenue-page-title p {
    margin: 0;

    color: var(--bs-secondary-color, #9ca3af);

    font-size: 15px;
    line-height: 1.6;
}


.revenue-page-title .report-date {
    display: inline-flex;

    margin-top: 10px;
    padding: 6px 11px;

    border-radius: 8px;

    background: rgba(99, 102, 241, 0.10);

    color: #aebcff;

    font-size: 13px;
    font-weight: 600;
}


/* ============================================================
   HEADER ACTIONS
============================================================ */

.revenue-page-actions {
    display: flex;
    align-items: center;
    gap: 10px;

    flex-shrink: 0;
}


.revenue-page-actions .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;

    min-height: 44px;

    padding: 9px 16px;

    border-radius: 10px;

    font-weight: 600;
}


/* ============================================================
   SUMMARY GRID
============================================================ */

.revenue-summary-grid {
    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 18px;

    margin-bottom: 32px;
}


/* ============================================================
   SUMMARY CARD
============================================================ */

.revenue-summary-card {

    position: relative;

    min-height: 155px;

    padding: 22px 22px 20px;

    overflow: hidden;

    border: 1px solid
        rgba(148, 163, 184, 0.16);

    border-radius: 16px;

    background:
        linear-gradient(
            145deg,
            rgba(30, 41, 59, 0.96),
            rgba(17, 24, 39, 0.96)
        );

    box-shadow:
        0 10px 30px
        rgba(0, 0, 0, 0.16);
}


.revenue-summary-card::before {

    content: "";

    position: absolute;

    top: 0;
    left: 0;
    bottom: 0;

    width: 4px;

    background: #6366f1;
}


/* Different accent colours */

.revenue-summary-card.revenue-card::before {
    background: #6366f1;
}


.revenue-summary-card.paid-card::before {
    background: #10b981;
}


.revenue-summary-card.pending-card::before {
    background: #f59e0b;
}


.revenue-summary-card.students-card::before {
    background: #8b5cf6;
}


/* ============================================================
   CARD ICON
============================================================ */

.revenue-card-icon {

    width: 42px;
    height: 42px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 15px;

    border-radius: 11px;

    background:
        rgba(99, 102, 241, 0.13);

    color: #aebcff;

    font-size: 20px;
}


.paid-card .revenue-card-icon {
    background: rgba(16, 185, 129, 0.13);
    color: #34d399;
}


.pending-card .revenue-card-icon {
    background: rgba(245, 158, 11, 0.13);
    color: #fbbf24;
}


.students-card .revenue-card-icon {
    background: rgba(139, 92, 246, 0.13);
    color: #a78bfa;
}


/* ============================================================
   CARD LABEL
============================================================ */

.revenue-card-label {

    display: block;

    margin-bottom: 5px;

    color: #9ca3af;

    font-size: 12px;
    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 0.7px;
}


/* ============================================================
   CARD VALUE
============================================================ */

.revenue-card-value {

    display: block;

    margin-bottom: 8px;

    color: #f8fafc;

    font-size: 25px;
    line-height: 1.25;

    font-weight: 800;
}


.paid-card .revenue-card-value {
    color: #34d399;
}


.pending-card .revenue-card-value {
    color: #fbbf24;
}


/* ============================================================
   CARD META
============================================================ */

.revenue-card-meta {

    display: flex;
    align-items: center;
    gap: 6px;

    color: #94a3b8;

    font-size: 13px;
    line-height: 1.4;
}


/* ============================================================
   MAIN PANEL
============================================================ */

.revenue-main-panel {

    overflow: hidden;

    border: 1px solid
        rgba(148, 163, 184, 0.16);

    border-radius: 17px;

    background:
        rgba(17, 24, 39, 0.72);

    box-shadow:
        0 12px 35px
        rgba(0, 0, 0, 0.15);
}


/* ============================================================
   PANEL HEADER
============================================================ */

.revenue-panel-header {

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;

    padding: 21px 23px;

    border-bottom: 1px solid
        rgba(148, 163, 184, 0.14);
}


.revenue-panel-heading h2 {

    margin: 0 0 5px;

    color: #f8fafc;

    font-size: 21px;
    font-weight: 750;
}


.revenue-panel-heading p {

    margin: 0;

    color: #94a3b8;

    font-size: 13px;
}


.revenue-panel-action {

    display: inline-flex;
    align-items: center;
    gap: 7px;

    white-space: nowrap;
}


/* ============================================================
   TABLE WRAPPER
============================================================ */

.revenue-table-wrapper {

    width: 100%;

    overflow-x: auto;
}


/* ============================================================
   TABLE
============================================================ */

.revenue-table {

    width: 100%;

    margin: 0;

    border-collapse: separate;
    border-spacing: 0;
}


.revenue-table th {

    padding: 14px 18px;

    border-bottom: 1px solid
        rgba(148, 163, 184, 0.15);

    background:
        rgba(15, 23, 42, 0.55);

    color: #94a3b8;

    font-size: 11px;
    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: 0.6px;

    white-space: nowrap;
}


.revenue-table td {

    padding: 17px 18px;

    border-bottom: 1px solid
        rgba(148, 163, 184, 0.09);

    color: #dbe4f0;

    font-size: 14px;

    vertical-align: middle;

    white-space: nowrap;
}


.revenue-table tbody tr {

    transition:
        background 0.15s ease;
}


.revenue-table tbody tr:hover {

    background:
        rgba(99, 102, 241, 0.055);
}


.revenue-table tbody tr:last-child td {

    border-bottom: 0;
}


/* ============================================================
   TEACHER NAME
============================================================ */

.teacher-cell {

    min-width: 220px;
}


.teacher-name {

    display: block;

    color: #f8fafc;

    font-weight: 700;
}


/* ============================================================
   AMOUNTS
============================================================ */

.revenue-amount {

    color: #f8fafc;

    font-weight: 700;
}


.revenue-paid {

    color: #34d399;

    font-weight: 700;
}


.revenue-pending {

    color: #fbbf24;

    font-weight: 700;
}


/* ============================================================
   COLLECTION
============================================================ */

.collection-cell {

    min-width: 150px;
}


.collection-top {

    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 7px;

    color: #cbd5e1;

    font-size: 12px;
    font-weight: 650;
}


.collection-progress {

    width: 100%;
    height: 7px;

    overflow: hidden;

    border-radius: 999px;

    background:
        rgba(148, 163, 184, 0.15);
}


.collection-progress span {

    display: block;

    height: 100%;

    border-radius: inherit;

    background:
        linear-gradient(
            90deg,
            #10b981,
            #34d399
        );
}


/* ============================================================
   GRAND TOTAL
============================================================ */

.revenue-grand-total td {

    padding-top: 18px;
    padding-bottom: 18px;

    border-top: 1px solid
        rgba(148, 163, 184, 0.18);

    background:
        rgba(99, 102, 241, 0.06);

    color: #f8fafc;

    font-weight: 800;
}


/* ============================================================
   MOBILE
============================================================ */

@media (max-width: 1100px) {

    .revenue-summary-grid {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

    }

}


@media (max-width: 768px) {

    .revenue-report-page {

        padding:
            20px 15px 40px;

    }


    .revenue-page-header {

        flex-direction: column;

        gap: 18px;

        margin-bottom: 24px;

    }


    .revenue-page-title h1 {

        font-size: 28px;

    }


    .revenue-page-title h1 i {

        font-size: 27px;

    }


    .revenue-page-actions {

        width: 100%;

    }


    .revenue-page-actions .btn {

        flex: 1;

    }


    .revenue-summary-grid {

        grid-template-columns: 1fr;

        gap: 13px;

        margin-bottom: 24px;

    }


    .revenue-summary-card {

        min-height: 140px;

    }


    .revenue-panel-header {

        align-items: flex-start;

        flex-direction: column;

    }


    .revenue-panel-action {

        width: 100%;

        justify-content: center;

    }

}


@media (max-width: 480px) {

    .revenue-page-title h1 {

        font-size: 24px;

    }


    .revenue-card-value {

        font-size: 22px;

    }


    .revenue-summary-card {

        padding: 18px;

    }


    .revenue-table th,
    .revenue-table td {

        padding:
            13px 14px;

    }

}

</style>


<div class="revenue-report-page">


    <!-- ========================================================
         PAGE HEADER
    ========================================================= -->

    <div class="revenue-page-header">

        <div class="revenue-page-title">

            <h1>
                <i class="bi bi-graph-up-arrow"></i>
                Revenue Report
            </h1>

            <p>
                Teacher earnings calculated from recorded
                lesson student counts.
            </p>

            <span class="report-date">
                <i class="bi bi-calendar-check"></i>
                Report updated through <?= htmlspecialchars($reportDate) ?>
            </span>

        </div>


        <div class="revenue-page-actions">

            <a
                class="btn btn-outline-primary"
                href="monthly.php"
            >
                <i class="bi bi-calendar-month"></i>
                Monthly
            </a>


            <a
                class="btn btn-primary"
                href="yearly.php"
            >
                <i class="bi bi-calendar3"></i>
                Yearly
            </a>

        </div>

    </div>



    <!-- ========================================================
         SUMMARY CARDS
    ========================================================= -->

    <div class="revenue-summary-grid">


        <!-- TOTAL REVENUE -->

        <article class="revenue-summary-card revenue-card">

            <div class="revenue-card-icon">
                <i class="bi bi-cash-stack"></i>
            </div>

            <span class="revenue-card-label">
                Total Revenue
            </span>

            <span class="revenue-card-value">
                <?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?>
                <?= number_format($grandTotal) ?>
            </span>

            <span class="revenue-card-meta">
                <i class="bi bi-journal-check"></i>
                <?= number_format($grandLessons) ?>
                lessons completed
            </span>

        </article>



        <!-- PAID -->

        <article class="revenue-summary-card paid-card">

            <div class="revenue-card-icon">
                <i class="bi bi-check-circle"></i>
            </div>

            <span class="revenue-card-label">
                Paid
            </span>

            <span class="revenue-card-value">
                <?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?>
                <?= number_format($grandPaid) ?>
            </span>

            <span class="revenue-card-meta">
                <i class="bi bi-percent"></i>
                <?= $paidPct ?>% collected
            </span>

        </article>



        <!-- PENDING -->

        <article class="revenue-summary-card pending-card">

            <div class="revenue-card-icon">
                <i class="bi bi-hourglass-split"></i>
            </div>

            <span class="revenue-card-label">
                Pending
            </span>

            <span class="revenue-card-value">
                <?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?>
                <?= number_format($grandPending) ?>
            </span>

            <span class="revenue-card-meta">
                <i class="bi bi-clock"></i>
                Outstanding amount
            </span>

        </article>



        <!-- STUDENTS -->

        <article class="revenue-summary-card students-card">

            <div class="revenue-card-icon">
                <i class="bi bi-people"></i>
            </div>

            <span class="revenue-card-label">
                Student Places
            </span>

            <span class="revenue-card-value">
                <?= number_format($grandStudents) ?>
            </span>

            <span class="revenue-card-meta">
                <i class="bi bi-person-check"></i>
                <?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?>
                <?= number_format($FEE_PER_STUDENT_LIVE) ?>
                per student / lesson
            </span>

        </article>

    </div>



    <!-- ========================================================
         REVENUE BY TEACHER
    ========================================================= -->

    <section class="revenue-main-panel">


        <!-- PANEL HEADER -->

        <div class="revenue-panel-header">

            <div class="revenue-panel-heading">

                <h2>
                    Revenue by Teacher
                </h2>

                <p>
                    All active teachers, including teachers
                    with no revenue yet.
                </p>

            </div>


            <a
                class="btn btn-sm btn-outline-secondary revenue-panel-action"
                href="../timetable/payment_ledger.php"
            >
                <i class="bi bi-journal-text"></i>
                Open Payment Ledger
            </a>

        </div>



        <!-- TABLE -->

        <div class="revenue-table-wrapper">

            <table class="table revenue-table">

                <thead>

                    <tr>

                        <th>
                            Teacher
                        </th>

                        <th>
                            Lessons
                        </th>

                        <th>
                            Students
                        </th>

                        <th>
                            Revenue
                        </th>

                        <th>
                            Paid
                        </th>

                        <th>
                            Pending
                        </th>

                        <th>
                            Collection
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php foreach ($teachers as $t): ?>

                        <?php

                        $teacherTotal =
                            (float)$t['total_revenue'];

                        $teacherPaid =
                            (float)$t['paid_revenue'];

                        $pct =
                            $teacherTotal > 0
                                ? round(
                                    (
                                        $teacherPaid
                                        /
                                        $teacherTotal
                                    ) * 100
                                )
                                : 0;

                        ?>


                        <tr>


                            <!-- TEACHER -->

                            <td class="teacher-cell">

                                <span class="teacher-name">
                                    <?= htmlspecialchars(
                                        $t['name']
                                    ) ?>
                                </span>

                            </td>


                            <!-- LESSONS -->

                            <td>

                                <?= number_format(
                                    $t['lessons']
                                ) ?>

                            </td>


                            <!-- STUDENTS -->

                            <td>

                                <?= number_format(
                                    $t['total_students']
                                ) ?>

                            </td>


                            <!-- REVENUE -->

                            <td class="revenue-amount">

                                <?= htmlspecialchars(
                                    $CURRENCY_SYMBOL_LIVE
                                ) ?>

                                <?= number_format(
                                    $teacherTotal
                                ) ?>

                            </td>


                            <!-- PAID -->

                            <td class="revenue-paid">

                                <?= htmlspecialchars(
                                    $CURRENCY_SYMBOL_LIVE
                                ) ?>

                                <?= number_format(
                                    $teacherPaid
                                ) ?>

                            </td>


                            <!-- PENDING -->

                            <td class="revenue-pending">

                                <?= htmlspecialchars(
                                    $CURRENCY_SYMBOL_LIVE
                                ) ?>

                                <?= number_format(
                                    $t['pending_revenue']
                                ) ?>

                            </td>


                            <!-- COLLECTION -->

                            <td class="collection-cell">

                                <div class="collection-top">

                                    <span>
                                        Collection
                                    </span>

                                    <strong>
                                        <?= $pct ?>%
                                    </strong>

                                </div>


                                <div class="collection-progress">

                                    <span
                                        style="width: <?= min(
                                            100,
                                            max(
                                                0,
                                                $pct
                                            )
                                        ) ?>%;"
                                    ></span>

                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>



                    <!-- ==================================================
                         GRAND TOTAL
                    =================================================== -->

                    <tr class="revenue-grand-total">

                        <td>
                            Grand Total
                        </td>

                        <td>
                            <?= number_format(
                                $grandLessons
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                $grandStudents
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $CURRENCY_SYMBOL_LIVE
                            ) ?>

                            <?= number_format(
                                $grandTotal
                            ) ?>
                        </td>

                        <td class="revenue-paid">

                            <?= htmlspecialchars(
                                $CURRENCY_SYMBOL_LIVE
                            ) ?>

                            <?= number_format(
                                $grandPaid
                            ) ?>

                        </td>

                        <td class="revenue-pending">

                            <?= htmlspecialchars(
                                $CURRENCY_SYMBOL_LIVE
                            ) ?>

                            <?= number_format(
                                $grandPending
                            ) ?>

                        </td>

                        <td>

                            <?= $paidPct ?>%

                        </td>

                    </tr>


                </tbody>

            </table>

        </div>

    </section>

</div>


<?php

include __DIR__ .
    '/../includes/footer.php';

?>