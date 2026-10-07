<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class CommunicationService
{
    public function __construct(private PDO $pdo) {}

    public function queue(string $channel,string $audienceType,?int $audienceId,?string $recipient,string $body,?string $subject,int $userId,?string $scheduledAt=null):int
    {
        if(!in_array($channel,['whatsapp','sms','email','in_app','push'],true)||trim($body)==='')throw new \InvalidArgumentException('Invalid communication.');
        $status=$scheduledAt?'scheduled':'queued';
        $s=$this->pdo->prepare("INSERT INTO communication_messages(channel,audience_type,audience_id,recipient,subject,body,status,scheduled_at,created_by) VALUES(?,?,?,?,?,?,?,?,?)");
        $s->execute([$channel,$audienceType,$audienceId,$recipient,$subject,mb_substr($body,0,5000),$status,$scheduledAt?:null,$userId]);
        $id=(int)$this->pdo->lastInsertId();if(function_exists('log_audit'))log_audit($this->pdo,'communication_queued','communication_messages',$id,null,['channel'=>$channel,'audience_type'=>$audienceType]);return $id;
    }

    /** @return list<array<string,mixed>> */
    public function history(int $limit=100):array
    {try{$s=$this->pdo->prepare('SELECT * FROM communication_messages ORDER BY id DESC LIMIT ?');$s->bindValue(1,max(1,min(300,$limit)),PDO::PARAM_INT);$s->execute();return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){return [];}}

    /** @return list<array<string,mixed>> */
    public function due(int $limit=50):array
    {try{$s=$this->pdo->prepare("SELECT * FROM communication_messages WHERE status='scheduled' AND (scheduled_at IS NULL OR scheduled_at<=NOW()) ORDER BY id LIMIT ?");$s->bindValue(1,$limit,PDO::PARAM_INT);$s->execute();return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){return [];}}
}
