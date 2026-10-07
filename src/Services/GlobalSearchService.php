<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class GlobalSearchService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,list<array<string,mixed>>> */
    public function search(string $query,string $role,int $userId=0,int $teacherId=0): array
    {
        $query=trim($query);if(strlen($query)<2)return [];
        $like='%'.$query.'%';$out=[];
        try{$s=$this->pdo->prepare("SELECT id,username AS title,role,'user' entity FROM users WHERE deleted_at IS NULL AND (username LIKE ? OR id=?) LIMIT 20");$s->execute([$like,(int)$query]);$out['users']=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        try{$s=$this->pdo->prepare("SELECT id,name title,'teacher' entity FROM teachers WHERE deleted_at IS NULL AND name LIKE ? LIMIT 20");$s->execute([$like]);$out['teachers']=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        try{$s=$this->pdo->prepare("SELECT id,name title,'class' entity FROM student_classes WHERE deleted_at IS NULL AND name LIKE ? LIMIT 20");$s->execute([$like]);$out['classes']=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        if($role==='admin'){
            try{$s=$this->pdo->prepare("SELECT p.id,p.amount,p.gateway,p.status,u.username title,'payment' entity FROM payment_transactions p LEFT JOIN users u ON u.id=p.student_id WHERE p.gateway LIKE ? OR p.status LIKE ? OR u.username LIKE ? ORDER BY p.id DESC LIMIT 30");$s->execute([$like,$like,$like]);$out['payments']=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        }
        if($role==='teacher'){
            try{$s=$this->pdo->prepare("SELECT DISTINCT u.id,u.username title,'student' entity FROM users u JOIN student_enrollments se ON se.student_id=u.id JOIN student_classes c ON c.id=se.class_id JOIN timetable tt ON tt.class_id=c.id WHERE u.role='student' AND u.deleted_at IS NULL AND tt.teacher_id=? AND u.username LIKE ? LIMIT 30");$s->execute([$teacherId,$like]);$out['students']=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        }else{
            try{$s=$this->pdo->prepare("SELECT id,username title,'student' entity FROM users WHERE role='student' AND deleted_at IS NULL AND username LIKE ? LIMIT 30");$s->execute([$like]);$out['students']=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){}
        }
        return array_filter($out,static fn($v)=>$v!==[]);
    }
}
