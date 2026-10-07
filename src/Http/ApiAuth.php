<?php
declare(strict_types=1);

namespace Edexcel\Http;

use PDO;
use RuntimeException;

final class ApiAuth
{
    /** @return array{user_id:int,role:string,parent_id:int} */
    public static function requireSession(PDO $pdo): array
    {
        $userId=(int)($_SESSION['user_id']??0);
        $parentId=(int)($_SESSION['parent_id']??0);
        if($userId<1&&$parentId<1)throw new RuntimeException('Authentication required.');
        if($userId>0&&function_exists('session_user_is_valid')&&!session_user_is_valid($pdo)){
            throw new RuntimeException('Authentication required.');
        }
        return ['user_id'=>$userId,'role'=>strtolower((string)($_SESSION['role']??($parentId>0?'parent':''))),'parent_id'=>$parentId];
    }

    /** @param array<string,mixed>|null $body */
    public static function csrfRequired(?array $body=null): void
    {
        $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??($body['csrf_token']??''));
        if(!function_exists('verify_csrf_token')||!verify_csrf_token($token))throw new RuntimeException('CSRF validation failed.');
    }
}
