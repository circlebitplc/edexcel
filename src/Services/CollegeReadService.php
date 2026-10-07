<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class CollegeReadService
{
    public function __construct(private PDO $pdo) {}

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function students(array $auth,string $search,int $limit,int $offset): array
    {
        $where=["u.role='student'","u.deleted_at IS NULL"];$params=[];
        $this->studentScope($where,$params,$auth);
        if($search!==''){$where[]='(u.username LIKE ? OR sp.full_name LIKE ? OR sp.email LIKE ?)';$q='%'.$search.'%';array_push($params,$q,$q,$q);}
        return $this->paged("SELECT u.id,u.username,u.is_active,u.last_login_at,sp.full_name,sp.email,sp.whatsapp_number FROM users u LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE ".implode(' AND ',$where)." ORDER BY COALESCE(sp.full_name,u.username),u.id LIMIT {$limit} OFFSET {$offset}",$params,"SELECT COUNT(*) FROM users u LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE ".implode(' AND ',$where),$params);
    }

    /** @return array<string,mixed>|null */
    public function student(int $id,array $auth):?array
    {
        if(!$this->canStudent($id,$auth))return null;
        $s=$this->pdo->prepare("SELECT u.id,u.username,u.is_active,u.created_at,u.last_login_at,sp.* FROM users u LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE u.id=? AND u.role='student' AND u.deleted_at IS NULL");$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);return $row?:null;
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function teachers(array $auth,string $search,int $limit,int $offset):array
    {
        $where=['t.deleted_at IS NULL'];$p=[];
        if(($auth['role']??'')==='teacher'){$where[]='t.id=?';$p[]=(int)($_SESSION['teacher_id']??0);}
        if($search!==''){$where[]='(t.name LIKE ? OR t.email LIKE ? OR t.phone LIKE ?)';$q='%'.$search.'%';array_push($p,$q,$q,$q);}
        return $this->paged("SELECT t.id,t.name,t.email,t.phone,t.photo FROM teachers t WHERE ".implode(' AND ',$where)." ORDER BY t.name LIMIT {$limit} OFFSET {$offset}",$p,"SELECT COUNT(*) FROM teachers t WHERE ".implode(' AND ',$where),$p);
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function classes(array $auth,string $search,int $limit,int $offset):array
    {
        $where=['c.deleted_at IS NULL'];$p=[];$role=$auth['role']??'';
        if($role==='teacher'){$where[]='EXISTS (SELECT 1 FROM timetable tx WHERE tx.class_id=c.id AND tx.teacher_id=? AND tx.deleted_at IS NULL)';$p[]=(int)($_SESSION['teacher_id']??0);}
        elseif($role==='student'){$where[]='EXISTS (SELECT 1 FROM student_enrollments se WHERE se.class_id=c.id AND se.student_id=?)';$p[]=(int)$auth['user_id'];}
        elseif($role==='parent'){$where[]='EXISTS (SELECT 1 FROM parent_students ps JOIN student_enrollments se ON se.student_id=ps.student_id AND se.class_id=c.id WHERE ps.parent_id=?)';$p[]=(int)$auth['parent_id'];}
        if($search!==''){$where[]='(c.name LIKE ? OR c.class_code LIKE ?)';$q='%'.$search.'%';array_push($p,$q,$q);}
        return $this->paged("SELECT c.id,c.name,c.class_code,c.description,c.subject_id,c.teacher_id,c.capacity,s.name subject_name,t.name teacher_name FROM student_classes c LEFT JOIN subjects s ON s.id=c.subject_id LEFT JOIN teachers t ON t.id=c.teacher_id WHERE ".implode(' AND ',$where)." ORDER BY c.name LIMIT {$limit} OFFSET {$offset}",$p,"SELECT COUNT(*) FROM student_classes c WHERE ".implode(' AND ',$where),$p);
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function subjects(array $auth,string $search,int $limit,int $offset):array
    {
        $where=['s.deleted_at IS NULL'];$p=[];if($search!==''){$where[]='(s.name LIKE ? OR s.code LIKE ?)';$q='%'.$search.'%';array_push($p,$q,$q);}
        return $this->paged("SELECT s.id,s.name,s.code,s.description FROM subjects s WHERE ".implode(' AND ',$where)." ORDER BY s.name LIMIT {$limit} OFFSET {$offset}",$p,"SELECT COUNT(*) FROM subjects s WHERE ".implode(' AND ',$where),$p);
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function timetable(array $auth,string $from,string $to,int $limit,int $offset):array
    {
        $where=['tt.deleted_at IS NULL','tt.date BETWEEN ? AND ?'];$p=[$from,$to];$role=$auth['role']??'';
        if($role==='admin'){}elseif($role==='teacher'){$where[]='tt.teacher_id=?';$p[]=(int)($_SESSION['teacher_id']??0);}elseif($role==='student'){$where[]='EXISTS (SELECT 1 FROM student_enrollments se WHERE se.student_id=? AND se.class_id=tt.class_id)';$p[]=(int)$auth['user_id'];}elseif($role==='parent'){$where[]='EXISTS (SELECT 1 FROM parent_students ps JOIN student_enrollments se ON se.student_id=ps.student_id AND se.class_id=tt.class_id WHERE ps.parent_id=?)';$p[]=(int)$auth['parent_id'];}else{return ['rows'=>[],'total'=>0];}
        return $this->paged("SELECT tt.id,tt.date,tt.start_time,tt.end_time,tt.class_id,tt.subject_id,tt.teacher_id,tt.room_id,tt.delivery_mode,tt.lesson_status,c.name class_name,s.name subject_name,t.name teacher_name FROM timetable tt LEFT JOIN student_classes c ON c.id=tt.class_id LEFT JOIN subjects s ON s.id=tt.subject_id LEFT JOIN teachers t ON t.id=tt.teacher_id WHERE ".implode(' AND ',$where)." ORDER BY tt.date,tt.start_time LIMIT {$limit} OFFSET {$offset}",$p,"SELECT COUNT(*) FROM timetable tt WHERE ".implode(' AND ',$where),$p);
    }

    /** @return array<string,mixed> */
    public function analytics(array $auth):array
    {
        if(($auth['role']??'')!=='admin')return [];
        $out=[];foreach(['students'=>"SELECT COUNT(*) FROM users WHERE role='student' AND deleted_at IS NULL",'teachers'=>"SELECT COUNT(*) FROM teachers WHERE deleted_at IS NULL",'classes'=>"SELECT COUNT(*) FROM student_classes WHERE deleted_at IS NULL",'classes_today'=>"SELECT COUNT(*) FROM timetable WHERE date=CURDATE() AND deleted_at IS NULL"] as $key=>$sql){try{$out[$key]=(int)$this->pdo->query($sql)->fetchColumn();}catch(Throwable $e){$out[$key]=null;}}return $out;
    }

    private function studentScope(array &$where,array &$params,array $auth):void
    { $role=$auth['role']??'';if($role==='admin')return;if($role==='student'){$where[]='u.id=?';$params[]=(int)$auth['user_id'];return;}if($role==='parent'){$where[]='EXISTS (SELECT 1 FROM parent_students ps WHERE ps.parent_id=? AND ps.student_id=u.id)';$params[]=(int)$auth['parent_id'];return;}if($role==='teacher'){$where[]='EXISTS (SELECT 1 FROM student_enrollments se JOIN timetable tx ON tx.class_id=se.class_id WHERE se.student_id=u.id AND tx.teacher_id=? AND tx.deleted_at IS NULL)';$params[]=(int)($_SESSION['teacher_id']??0);return;}$where[]='1=0';}

    private function canStudent(int $id,array $auth):bool
    { $where=['u.id=?','u.role=\'student\'','u.deleted_at IS NULL'];$p=[$id];$this->studentScope($where,$p,$auth);try{$s=$this->pdo->prepare('SELECT 1 FROM users u WHERE '.implode(' AND ',$where).' LIMIT 1');$s->execute($p);return(bool)$s->fetchColumn();}catch(Throwable $e){return false;}}

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    private function paged(string $query,array $params,string $countQuery,array $countParams):array
    {try{$s=$this->pdo->prepare($query);$s->execute($params);$c=$this->pdo->prepare($countQuery);$c->execute($countParams);return['rows'=>$s->fetchAll(PDO::FETCH_ASSOC)?:[],'total'=>(int)$c->fetchColumn()];}catch(Throwable $e){return['rows'=>[],'total'=>0];}}
}
