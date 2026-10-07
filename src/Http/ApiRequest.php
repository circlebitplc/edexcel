<?php
declare(strict_types=1);

namespace Edexcel\Http;

use InvalidArgumentException;

final class ApiRequest
{
    /** @return array<string,mixed> */
    public static function body(): array
    {
        $raw=file_get_contents('php://input');
        if(is_string($raw)&&trim($raw)!==''){
            $json=json_decode($raw,true);
            if(is_array($json))return $json;
        }
        return $_POST;
    }

    public static function method(string $expected): void
    {
        if(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))!==strtoupper($expected)){
            throw new InvalidArgumentException('Method not allowed.');
        }
    }

    /** @return array{page:int,per_page:int,offset:int} */
    public static function pagination(): array
    {
        $page=max(1,(int)($_GET['page']??1));
        $per=max(1,min(100,(int)($_GET['per_page']??$_GET['page_size']??25)));
        return ['page'=>$page,'per_page'=>$per,'offset'=>($page-1)*$per];
    }

    public static function search(): string { return mb_substr(trim((string)($_GET['search']??'')),0,120); }

    /** @return array{sort:string,order:string} */
    public static function sorting(array $allowed,string $default): array
    {
        $sort=(string)($_GET['sort']??$default);if(!in_array($sort,$allowed,true))$sort=$default;
        $order=strtolower((string)($_GET['order']??'asc'));if(!in_array($order,['asc','desc'],true))$order='asc';
        return ['sort'=>$sort,'order'=>$order];
    }
}
