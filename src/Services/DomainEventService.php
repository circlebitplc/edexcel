<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class DomainEventService
{
    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed> $payload */
    public function record(string $name,?string $aggregateType=null,?int $aggregateId=null,array $payload=[]):string
    {
        $eventId=self::uuid();$this->pdo->prepare('INSERT INTO domain_events(event_id,event_name,aggregate_type,aggregate_id,payload_json,actor_user_id) VALUES(?,?,?,?,?,?)')->execute([$eventId,$name,$aggregateType,$aggregateId,json_encode($payload,JSON_UNESCAPED_UNICODE),(int)($_SESSION['user_id']??0)?:null]);
        if(function_exists('log_audit'))log_audit($this->pdo,'domain_event','domain_events',null,null,['event_id'=>$eventId,'event_name'=>$name]);
        return $eventId;
    }

    private static function uuid():string
    { $d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4)); }
}
