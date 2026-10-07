<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;

final class TimetableAuditLogger
{
    public function __construct(private PDO $pdo) {}

    public function log(
        string $action,int $recordId,
        ?array $old=null,?array $new=null
    ): void {
        if (function_exists('log_audit')) {
            try {
                log_audit(
                    $this->pdo,$action,
                    'timetable',$recordId,$old,$new
                );
            } catch (\Throwable $e) {
                error_log('Timetable audit failed: '.$e->getMessage());
            }
            return;
        }

        try {
            $stmt=$this->pdo->prepare(
                "INSERT INTO audit_logs
                 (user_id,username,action,table_name,record_id,
                  old_values,new_values,ip_address,user_agent)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0
                    ? (int)$_SESSION['user_id']
                    : null,
                $_SESSION['username']??'system',
                $action,'timetable',$recordId,
                $old?json_encode($old):null,
                $new?json_encode($new):null,
                $_SERVER['REMOTE_ADDR']??'',
                $_SERVER['HTTP_USER_AGENT']??''
            ]);
        } catch (\Throwable $e) {
            error_log('Timetable audit failed: '.$e->getMessage());
        }
    }
}
