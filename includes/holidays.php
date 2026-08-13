<?php
function is_holiday($pdo, $date) {
    $stmt = $pdo->prepare("SELECT id FROM holidays WHERE date = ?");
    $stmt->execute([$date]);
    return $stmt->rowCount() > 0;
}

function get_holidays($pdo, $start = null, $end = null) {
    $sql = "SELECT * FROM holidays ORDER BY date";
    if ($start) {
        $sql .= " WHERE date >= ?";
        if ($end) {
            $sql .= " AND date <= ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$start, $end]);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$start]);
        }
    } else {
        $stmt = $pdo->query($sql);
    }
    return $stmt->fetchAll();
}