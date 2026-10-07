<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;

final class ApiIdempotencyService
{
    public function __construct(private PDO $pdo) {}

    /** @return array{status:int,payload:array<string,mixed>}|null */
    public function existing(string $key,int $userId,string $route,string $requestHash):?array
    {
        $s=$this->pdo->prepare('SELECT response_status,response_json,request_hash FROM api_idempotency_keys WHERE idempotency_key=? AND user_id=? AND route=? AND expires_at>NOW() LIMIT 1');
        $s->execute([$key,$userId,$route]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)return null;
        if(!hash_equals((string)$r['request_hash'],$requestHash))throw new \RuntimeException('Idempotency key was already used with different request data.');
        $payload=json_decode((string)$r['response_json'],true);return['status'=>(int)$r['response_status'],'payload'=>is_array($payload)?$payload:[]];
    }

    /** @param array<string,mixed> $payload */
    public function store(string $key,int $userId,string $route,string $requestHash,int $status,array $payload,int $ttl=86400):void
    { $ttl=max(60,min(604800,$ttl));$this->pdo->prepare("INSERT INTO api_idempotency_keys(idempotency_key,user_id,route,request_hash,response_status,response_json,expires_at) VALUES(?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL {$ttl} SECOND))")->execute([$key,$userId,$route,$requestHash,$status,json_encode($payload,JSON_UNESCAPED_UNICODE)]); }
}
