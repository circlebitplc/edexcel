<?php
function is_holiday($pdo, $date) {
    $stmt = $pdo->prepare("SELECT id FROM holidays WHERE date = ? LIMIT 1");
    $stmt->execute([$date]);
    return (bool)$stmt->fetchColumn();
}

function get_holidays($pdo, $start = null, $end = null) {
    $sql = "SELECT * FROM holidays";
    $params = [];
    if ($start) {
        $sql .= " WHERE date >= ?";
        $params[] = $start;
        if ($end) {
            $sql .= " AND date <= ?";
            $params[] = $end;
        }
    }
    $sql .= " ORDER BY date";
    if ($params !== []) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->query($sql);
    }
    return $stmt->fetchAll();
}