<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class PerformanceMonitor
{
    public function __construct(private PDO $pdo) {}

    public function record(string $route, string $method, float $durationMs, ?int $statusCode = null): void
    {
        try {
            $slow = $durationMs >= 400;
            if (!$slow && random_int(1, 50) !== 1) {
                $this->maybePrune();
                return;
            }
            $stmt = $this->pdo->prepare(
                'INSERT INTO performance_metrics(request_id,route,method,duration_ms,memory_bytes,status_code) VALUES(?,?,?,?,?,?)'
            );
            $stmt->execute([
                AppLogger::requestId(),
                mb_substr(explode('?', $route, 2)[0], 0, 255),
                mb_substr($method, 0, 10),
                round($durationMs, 2),
                function_exists('memory_get_peak_usage') ? memory_get_peak_usage(true) : null,
                $statusCode,
            ]);
            $this->maybePrune();
        } catch (Throwable $e) {
        }
    }

    private function maybePrune(): void
    {
        try {
            if (random_int(1, 200) !== 1) {
                return;
            }
            $this->pdo->exec(
                'DELETE FROM performance_metrics WHERE created_at < (NOW() - INTERVAL 14 DAY) LIMIT 800'
            );
        } catch (Throwable $e) {
        }
    }

    /** @return list<array<string,mixed>> */
    public function slow(int $limit=50):array
    {try{$s=$this->pdo->prepare("SELECT SUBSTRING_INDEX(route,'?',1) route,method,AVG(duration_ms) avg_ms,MAX(duration_ms) max_ms,COUNT(*) samples FROM performance_metrics WHERE created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY SUBSTRING_INDEX(route,'?',1),method ORDER BY avg_ms DESC LIMIT ?");$s->bindValue(1,max(1,min(200,$limit)),PDO::PARAM_INT);$s->execute();return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){return [];}}
}
