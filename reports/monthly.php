<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/payment.php';

require_admin();


/* ============================================================
   REPORT PERIOD
   Future classes are never included.
============================================================ */

$currentYear  = (int)date('Y');
$currentMonth = (int)date('m');
$today        = date('Y-m-d');

$year = (int)($_GET['year'] ?? $currentYear);
$month = max(
    1,
    min(
        12,
        (int)($_GET['month'] ?? $currentMonth)
    )
);

$monthStart = sprintf(
    '%04d-%02d-01',
    $year,
    $month
);

$calendarMonthEnd = date(
    'Y-m-t',
    strtotime($monthStart)
);

/*
 * Never report beyond today.
 *
 * For the current month:
 *     monthEnd = today
 *
 * For previous months:
 *     monthEnd = normal month end
 *
 * For a future month:
 *     the query naturally returns zero rows.
 */
$monthEnd = min(
    $calendarMonthEnd,
    $today
);


/* ============================================================
   MONTH LABEL
============================================================ */

$monthLabel = date(
    'F Y',
    strtotime($monthStart)
);


/* ============================================================
   REVENUE QUERY
============================================================ */

$sql = "
    SELECT
        tc.id,
        tc.name,

        COUNT(t.id) AS classes,

        COALESCE(
            SUM(t.student_count),
            0
        ) AS students,

        COALESCE(
            SUM(
                t.student_count * (
    CASE
        WHEN (
            CASE
                WHEN TIME_TO_SEC(t.end_time) >= TIME_TO_SEC(t.start_time)
                THEN (TIME_TO_SEC(t.end_time) - TIME_TO_SEC(t.start_time)) / 60
                ELSE (TIME_TO_SEC(t.end_time) + 86400 - TIME_TO_SEC(t.start_time)) / 60
            END
        ) <= 150 THEN 500
        WHEN (
            CASE
                WHEN TIME_TO_SEC(t.end_time) >= TIME_TO_SEC(t.start_time)
                THEN (TIME_TO_SEC(t.end_time) - TIME_TO_SEC(t.start_time)) / 60
                ELSE (TIME_TO_SEC(t.end_time) + 86400 - TIME_TO_SEC(t.start_time)) / 60
            END
        ) <= 210 THEN 700
        WHEN (
            CASE
                WHEN TIME_TO_SEC(t.end_time) >= TIME_TO_SEC(t.start_time)
                THEN (TIME_TO_SEC(t.end_time) - TIME_TO_SEC(t.start_time)) / 60
                ELSE (TIME_TO_SEC(t.end_time) + 86400 - TIME_TO_SEC(t.start_time)) / 60
            END
        ) <= 270 THEN 900
        ELSE 1100
    END
)
            ),
            0
        ) AS revenue,

        COALESCE(
            SUM(
                CASE
                    WHEN t.payment_status = 'paid'
                    THEN t.student_count * (
    CASE
        WHEN (
            CASE
                WHEN TIME_TO_SEC(t.end_time) >= TIME_TO_SEC(t.start_time)
                THEN (TIME_TO_SEC(t.end_time) - TIME_TO_SEC(t.start_time)) / 60
                ELSE (TIME_TO_SEC(t.end_time) + 86400 - TIME_TO_SEC(t.start_time)) / 60
            END
        ) <= 150 THEN 500
        WHEN (
            CASE
                WHEN TIME_TO_SEC(t.end_time) >= TIME_TO_SEC(t.start_time)
                THEN (TIME_TO_SEC(t.end_time) - TIME_TO_SEC(t.start_time)) / 60
                ELSE (TIME_TO_SEC(t.end_time) + 86400 - TIME_TO_SEC(t.start_time)) / 60
            END
        ) <= 210 THEN 700
        WHEN (
            CASE
                WHEN TIME_TO_SEC(t.end_time) >= TIME_TO_SEC(t.start_time)
                THEN (TIME_TO_SEC(t.end_time) - TIME_TO_SEC(t.start_time)) / 60
                ELSE (TIME_TO_SEC(t.end_time) + 86400 - TIME_TO_SEC(t.start_time)) / 60
            END
        ) <= 270 THEN 900
        ELSE 1100
    END
)
                    ELSE 0
                END
            ),
            0
        ) AS paid

    FROM teachers tc

    LEFT JOIN timetable t
        ON tc.id = t.teacher_id

        AND t.deleted_at IS NULL

        /*
         * Only lessons in the selected month
         * and never beyond today.
         */
        AND t.date BETWEEN ? AND ?

    WHERE
        tc.deleted_at IS NULL

    GROUP BY
        tc.id

    ORDER BY
        revenue DESC,
        tc.name ASC
";


$stmt = $pdo->prepare($sql);

$stmt->execute([
    $monthStart,
    $monthEnd
]);

$teachers = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);


/* ============================================================
   TOTALS
============================================================ */

$total =
    array_sum(
        array_column(
            $teachers,
            'revenue'
        )
    );

$paid =
    array_sum(
        array_column(
            $teachers,
            'paid'
        )
    );

$pending =
    $total - $paid;

$lessons =
    array_sum(
        array_column(
            $teachers,
            'classes'
        )
    );

$students =
    array_sum(
        array_column(
            $teachers,
            'students'
        )
    );

$paidPct =
    $total > 0
        ? round(
            ($paid / $total) * 100,
            1
        )
        : 0;


/* ============================================================
   REPORT RANGE DISPLAY
============================================================ */

$displayStart =
    date(
        'd M Y',
        strtotime($monthStart)
    );

$displayEnd =
    date(
        'd M Y',
        strtotime($monthEnd)
    );


include __DIR__ .
    '/../includes/header.php';

?>

<style>

/* ============================================================
   MONTHLY REVENUE REPORT
============================================================ */

.monthly-report-page {

    width: 100%;
    max-width: 1500px;

    margin: 0 auto;

    padding: 28px 30px 50px;
}


/* ============================================================
   HEADER
============================================================ */

.monthly-page-header {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 30px;

    margin-bottom: 26px;
}


.monthly-page-title h1 {

    display: flex;

    align-items: center;

    gap: 12px;

    margin: 0 0 8px;

    color: #f8fafc;

    font-size: 34px;

    line-height: 1.2;

    font-weight: 800;
}


.monthly-page-title h1 i {

    font-size: 33px;

    color: #818cf8;
}


.monthly-page-title p {

    margin: 0;

    color: #94a3b8;

    font-size: 15px;

    line-height: 1.6;
}


.monthly-report-period {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    margin-top: 11px;

    padding: 7px 12px;

    border-radius: 9px;

    background:
        rgba(99, 102, 241, 0.10);

    color: #aebcff;

    font-size: 13px;

    font-weight: 650;
}


/* ============================================================
   HEADER BUTTONS
============================================================ */

.monthly-page-actions {

    display: flex;

    align-items: center;

    gap: 10px;

    flex-shrink: 0;
}


.monthly-page-actions .btn {

    min-height: 44px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    padding: 9px 16px;

    border-radius: 10px;

    font-weight: 650;
}


/* ============================================================
   FILTER PANEL
============================================================ */

.monthly-filter-panel {

    margin-bottom: 26px;

    padding: 21px 22px;

    border: 1px solid
        rgba(148, 163, 184, 0.16);

    border-radius: 16px;

    background:
        rgba(17, 24, 39, 0.74);

    box-shadow:
        0 10px 30px
        rgba(0, 0, 0, 0.12);
}


.monthly-filter-grid {

    display: grid;

    grid-template-columns:
        minmax(180px, 1fr)
        minmax(180px, 1fr)
        auto;

    align-items: end;

    gap: 16px;
}


.monthly-filter-field label {

    display: block;

    margin-bottom: 7px;

    color: #94a3b8;

    font-size: 12px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .55px;
}


.monthly-filter-field select {

    width: 100%;

    min-height: 45px;

    border-radius: 10px;

    border: 1px solid
        rgba(148, 163, 184, 0.20);

    background:
        #111827;

    color: #e5e7eb;

    padding: 9px 12px;

    font-size: 14px;
}


.monthly-filter-actions {

    display: flex;

    gap: 9px;

    min-height: 45px;
}


.monthly-filter-actions .btn {

    min-width: 120px;

    border-radius: 10px;

    font-weight: 650;
}


/* ============================================================
   SUMMARY CARDS
============================================================ */

.monthly-summary-grid {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 18px;

    margin-bottom: 30px;
}


.monthly-summary-card {

    position: relative;

    min-height: 155px;

    padding: 22px;

    overflow: hidden;

    border: 1px solid
        rgba(148, 163, 184, 0.16);

    border-radius: 16px;

    background:
        linear-gradient(
            145deg,
            rgba(30, 41, 59, .96),
            rgba(17, 24, 39, .96)
        );

    box-shadow:
        0 10px 30px
        rgba(0, 0, 0, .15);
}


.monthly-summary-card::before {

    content: "";

    position: absolute;

    top: 0;

    left: 0;

    bottom: 0;

    width: 4px;

    background: #6366f1;
}


.monthly-summary-card.paid::before {

    background: #10b981;
}


.monthly-summary-card.pending::before {

    background: #f59e0b;
}


.monthly-summary-card.students::before {

    background: #8b5cf6;
}


/* ============================================================
   SUMMARY ICON
============================================================ */

.monthly-card-icon {

    width: 42px;

    height: 42px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 14px;

    border-radius: 11px;

    background:
        rgba(99, 102, 241, .13);

    color: #aebcff;

    font-size: 20px;
}


.monthly-summary-card.paid
.monthly-card-icon {

    background:
        rgba(16, 185, 129, .13);

    color: #34d399;
}


.monthly-summary-card.pending
.monthly-card-icon {

    background:
        rgba(245, 158, 11, .13);

    color: #fbbf24;
}


.monthly-summary-card.students
.monthly-card-icon {

    background:
        rgba(139, 92, 246, .13);

    color: #a78bfa;
}


/* ============================================================
   SUMMARY TEXT
============================================================ */

.monthly-card-label {

    display: block;

    margin-bottom: 5px;

    color: #94a3b8;

    font-size: 12px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .7px;
}


.monthly-card-value {

    display: block;

    margin-bottom: 8px;

    color: #f8fafc;

    font-size: 25px;

    line-height: 1.2;

    font-weight: 800;
}


.monthly-summary-card.paid
.monthly-card-value {

    color: #34d399;
}


.monthly-summary-card.pending
.monthly-card-value {

    color: #fbbf24;
}


.monthly-card-meta {

    display: flex;

    align-items: center;

    gap: 6px;

    color: #94a3b8;

    font-size: 13px;
}


/* ============================================================
   MAIN PANEL
============================================================ */

.monthly-main-panel {

    overflow: hidden;

    border: 1px solid
        rgba(148, 163, 184, .16);

    border-radius: 17px;

    background:
        rgba(17, 24, 39, .72);

    box-shadow:
        0 12px 35px
        rgba(0, 0, 0, .15);
}


/* ============================================================
   PANEL HEADER
============================================================ */

.monthly-panel-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    padding: 21px 23px;

    border-bottom: 1px solid
        rgba(148, 163, 184, .14);
}


.monthly-panel-heading h2 {

    margin: 0 0 5px;

    color: #f8fafc;

    font-size: 21px;

    font-weight: 750;
}


.monthly-panel-heading p {

    margin: 0;

    color: #94a3b8;

    font-size: 13px;
}


.monthly-export {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    white-space: nowrap;
}


/* ============================================================
   TABLE
============================================================ */

.monthly-table-wrapper {

    width: 100%;

    overflow-x: auto;
}


.monthly-table {

    width: 100%;

    margin: 0;

    border-collapse: separate;

    border-spacing: 0;
}


.monthly-table th {

    padding: 14px 18px;

    border-bottom: 1px solid
        rgba(148, 163, 184, .15);

    background:
        rgba(15, 23, 42, .55);

    color: #94a3b8;

    font-size: 11px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .6px;

    white-space: nowrap;
}


.monthly-table td {

    padding: 17px 18px;

    border-bottom: 1px solid
        rgba(148, 163, 184, .09);

    color: #dbe4f0;

    font-size: 14px;

    vertical-align: middle;

    white-space: nowrap;
}


.monthly-table tbody tr {

    transition:
        background .15s ease;
}


.monthly-table tbody tr:hover {

    background:
        rgba(99, 102, 241, .055);
}


.monthly-table tbody tr:last-child td {

    border-bottom: 0;
}


.monthly-teacher {

    min-width: 220px;
}


.monthly-teacher strong {

    color: #f8fafc;

    font-weight: 700;
}


.monthly-revenue {

    color: #f8fafc;

    font-weight: 700;
}


.monthly-paid {

    color: #34d399;

    font-weight: 700;
}


.monthly-pending {

    color: #fbbf24;

    font-weight: 700;
}


/* ============================================================
   GRAND TOTAL
============================================================ */

.monthly-grand-total td {

    padding-top: 18px;

    padding-bottom: 18px;

    border-top: 1px solid
        rgba(148, 163, 184, .18);

    background:
        rgba(99, 102, 241, .06);

    color: #f8fafc;

    font-weight: 800;
}


/* ============================================================
   EMPTY STATE
============================================================ */

.monthly-empty {

    padding: 45px 20px !important;

    text-align: center;

    color: #94a3b8 !important;
}


.monthly-empty i {

    display: block;

    margin-bottom: 10px;

    font-size: 30px;

    color: #64748b;
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 1100px) {

    .monthly-summary-grid {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

}


@media (max-width: 800px) {

    .monthly-page-header {

        flex-direction: column;

        gap: 18px;
    }


    .monthly-page-actions {

        width: 100%;
    }


    .monthly-page-actions .btn {

        flex: 1;
    }


    .monthly-filter-grid {

        grid-template-columns: 1fr 1fr;
    }


    .monthly-filter-actions {

        grid-column: 1 / -1;
    }

}


@media (max-width: 600px) {

    .monthly-report-page {

        padding:
            20px 15px 40px;
    }


    .monthly-page-title h1 {

        font-size: 27px;
    }


    .monthly-summary-grid {

        grid-template-columns: 1fr;

        gap: 13px;
    }


    .monthly-filter-grid {

        grid-template-columns: 1fr;
    }


    .monthly-filter-actions {

        grid-column: auto;

        width: 100%;
    }


    .monthly-filter-actions .btn {

        flex: 1;

        min-width: 0;
    }


    .monthly-panel-header {

        align-items: flex-start;

        flex-direction: column;
    }


    .monthly-export {

        width: 100%;

        justify-content: center;
    }


    .monthly-table th,
    .monthly-table td {

        padding:
            13px 14px;
    }

}

</style>


<div class="monthly-report-page">


    <!-- ========================================================
         PAGE HEADER
    ========================================================= -->

    <div class="monthly-page-header">

        <div class="monthly-page-title">

            <h1>
                <i class="bi bi-calendar-month"></i>
                Monthly Revenue
            </h1>

            <p>
                <?= htmlspecialchars($monthLabel) ?>
                ·
                <?= htmlspecialchars($CURRENCY_SYMBOL_LIVE) ?>
                <?= number_format($FEE_PER_STUDENT_LIVE) ?>
                per student / lesson.
            </p>

            <span class="monthly-report-period">
                <i class="bi bi-calendar-check"></i>

                Report period:
                <?= htmlspecialchars($displayStart) ?>
                –
                <?= htmlspecialchars($displayEnd) ?>

                <?php if ($monthEnd === $today): ?>

                    <span>
                        · Up to today
                    </span>

                <?php endif; ?>

            </span>

        </div>


        <div class="monthly-page-actions">

            <a
                class="btn btn-outline-primary"
                href="revenue.php"
            >
                <i class="bi bi-graph-up-arrow"></i>
                Revenue Overview
            </a>


            <a
                class="btn btn-primary"
                href="yearly.php?year=<?= $year ?>"
            >
                <i class="bi bi-calendar3"></i>
                Yearly Report
            </a>

        </div>

    </div>



    <!-- ========================================================
         FILTERS
    ========================================================= -->

    <section class="monthly-filter-panel">

        <form method="get">

            <div class="monthly-filter-grid">


                <div class="monthly-filter-field">

                    <label for="report-year">
                        Year
                    </label>

                    <select
                        id="report-year"
                        name="year"
                        class="form-select"
                    >

                        <?php
                        for (
                            $y = $currentYear - 3;
                            $y <= $currentYear;
                            $y++
                        ):
                        ?>

                            <option
                                value="<?= $y ?>"
                                <?= $y === $year
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $y ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>



                <div class="monthly-filter-field">

                    <label for="report-month">
                        Month
                    </label>

                    <select
                        id="report-month"
                        name="month"
                        class="form-select"
                    >

                        <?php
                        for (
                            $m = 1;
                            $m <= 12;
                            $m++
                        ):
                        ?>

                            <option
                                value="<?= $m ?>"
                                <?= $m === $month
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= date(
                                    'F',
                                    mktime(
                                        0,
                                        0,
                                        0,
                                        $m,
                                        1
                                    )
                                ) ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>



                <div class="monthly-filter-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-filter"></i>
                        Update Report
                    </button>


                    <a
                        class="btn btn-outline-secondary"
                        href="monthly.php"
                    >
                        <i class="bi bi-calendar-check"></i>
                        Current Month
                    </a>

                </div>

            </div>

        </form>

    </section>



    <!-- ========================================================
         SUMMARY CARDS
    ========================================================= -->

    <div class="monthly-summary-grid">


        <!-- REVENUE -->

        <article class="monthly-summary-card">

            <div class="monthly-card-icon">

                <i class="bi bi-cash-stack"></i>

            </div>

            <span class="monthly-card-label">
                Total Revenue
            </span>

            <span class="monthly-card-value">

                <?= htmlspecialchars(
                    $CURRENCY_SYMBOL_LIVE
                ) ?>

                <?= number_format($total) ?>

            </span>

            <span class="monthly-card-meta">

                <i class="bi bi-journal-check"></i>

                <?= number_format($lessons) ?>
                lessons

            </span>

        </article>



        <!-- PAID -->

        <article class="monthly-summary-card paid">

            <div class="monthly-card-icon">

                <i class="bi bi-check-circle"></i>

            </div>

            <span class="monthly-card-label">
                Paid
            </span>

            <span class="monthly-card-value">

                <?= htmlspecialchars(
                    $CURRENCY_SYMBOL_LIVE
                ) ?>

                <?= number_format($paid) ?>

            </span>

            <span class="monthly-card-meta">

                <i class="bi bi-percent"></i>

                <?= $paidPct ?>%
                collected

            </span>

        </article>



        <!-- PENDING -->

        <article class="monthly-summary-card pending">

            <div class="monthly-card-icon">

                <i class="bi bi-hourglass-split"></i>

            </div>

            <span class="monthly-card-label">
                Pending
            </span>

            <span class="monthly-card-value">

                <?= htmlspecialchars(
                    $CURRENCY_SYMBOL_LIVE
                ) ?>

                <?= number_format($pending) ?>

            </span>

            <span class="monthly-card-meta">

                <i class="bi bi-clock"></i>

                Outstanding

            </span>

        </article>



        <!-- STUDENTS -->

        <article class="monthly-summary-card students">

            <div class="monthly-card-icon">

                <i class="bi bi-people"></i>

            </div>

            <span class="monthly-card-label">
                Student Places
            </span>

            <span class="monthly-card-value">

                <?= number_format($students) ?>

            </span>

            <span class="monthly-card-meta">

                <i class="bi bi-person-check"></i>

                Across all active teachers

            </span>

        </article>

    </div>



    <!-- ========================================================
         TEACHER BREAKDOWN
    ========================================================= -->

    <section class="monthly-main-panel">


        <div class="monthly-panel-header">

            <div class="monthly-panel-heading">

                <h2>
                    Teacher Breakdown
                </h2>

                <p>
                    <?= htmlspecialchars($monthLabel) ?>
                    revenue by teacher.
                    Future lessons are excluded.
                </p>

            </div>


            <a
                class="btn btn-sm btn-outline-secondary monthly-export"
                href="../timetable/export_csv.php?type=timetable&amp;date_from=<?= urlencode($monthStart) ?>&amp;date_to=<?= urlencode($monthEnd) ?>"
            >
                <i class="bi bi-download"></i>
                Export Report
            </a>

        </div>



        <div class="monthly-table-wrapper">

            <table class="table monthly-table">

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

                    </tr>

                </thead>


                <tbody>


                    <?php if (!$teachers): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="monthly-empty"
                            >

                                <i class="bi bi-calendar-x"></i>

                                No lessons were recorded
                                for this reporting period.

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach ($teachers as $t): ?>

                            <?php

                            $teacherRevenue =
                                (float)$t['revenue'];

                            $teacherPaid =
                                (float)$t['paid'];

                            $teacherPending =
                                $teacherRevenue
                                -
                                $teacherPaid;

                            ?>


                            <tr>


                                <td class="monthly-teacher">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $t['name']
                                        ) ?>
                                    </strong>

                                </td>


                                <td>

                                    <?= number_format(
                                        $t['classes']
                                    ) ?>

                                </td>


                                <td>

                                    <?= number_format(
                                        $t['students']
                                    ) ?>

                                </td>


                                <td class="monthly-revenue">

                                    <?= htmlspecialchars(
                                        $CURRENCY_SYMBOL_LIVE
                                    ) ?>

                                    <?= number_format(
                                        $teacherRevenue
                                    ) ?>

                                </td>


                                <td class="monthly-paid">

                                    <?= htmlspecialchars(
                                        $CURRENCY_SYMBOL_LIVE
                                    ) ?>

                                    <?= number_format(
                                        $teacherPaid
                                    ) ?>

                                </td>


                                <td class="monthly-pending">

                                    <?= htmlspecialchars(
                                        $CURRENCY_SYMBOL_LIVE
                                    ) ?>

                                    <?= number_format(
                                        $teacherPending
                                    ) ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        <!-- GRAND TOTAL -->

                        <tr
                            class="monthly-grand-total"
                        >

                            <td>
                                Grand Total
                            </td>

                            <td>
                                <?= number_format(
                                    $lessons
                                ) ?>
                            </td>

                            <td>
                                <?= number_format(
                                    $students
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $CURRENCY_SYMBOL_LIVE
                                ) ?>

                                <?= number_format(
                                    $total
                                ) ?>
                            </td>

                            <td class="monthly-paid">
                                <?= htmlspecialchars(
                                    $CURRENCY_SYMBOL_LIVE
                                ) ?>

                                <?= number_format(
                                    $paid
                                ) ?>
                            </td>

                            <td class="monthly-pending">
                                <?= htmlspecialchars(
                                    $CURRENCY_SYMBOL_LIVE
                                ) ?>

                                <?= number_format(
                                    $pending
                                ) ?>
                            </td>

                        </tr>


                    <?php endif; ?>


                </tbody>

            </table>

        </div>

    </section>

</div>


<?php

include __DIR__ .
    '/../includes/footer.php';

?>