<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

final class WebhookService
{
    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed> $payload */
    public function queue(string $eventId,string $endpoint,string $secret,array $payload):int
    {
        if(!filter_var($endpoint,FILTER_VALIDATE_URL)||$eventId==='')throw new RuntimeException('Invalid webhook endpoint.');
        $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$signature=hash_hmac('sha256',$body,$secret);
        $s=$this->pdo->prepare("INSERT INTO webhook_deliveries(event_id,endpoint_url,signature,status,next_attempt_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE event_id=event_id");
        $s->execute([$eventId,$endpoint,$signature,'pending']);return(int)$this->pdo->lastInsertId();
    }

    public static function signature(string $body,string $secret):string{return hash_hmac('sha256',$body,$secret);}
    public static function verify(string $body,string $secret,string $provided):bool{return hash_equals(self::signature($body,$secret),$provided);}
}
