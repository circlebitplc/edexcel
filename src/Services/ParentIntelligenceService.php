<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class ParentIntelligenceService
{
    public function __construct(private PDO $pdo) {}

    /** @return list<array<string,mixed>> */
    public function children(int $parentId):array
    {
        try{$s=$this->pdo->prepare("SELECT u.id,COALESCE(sp.full_name,u.username) name FROM parent_students ps JOIN users u ON u.id=ps.student_id LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE ps.parent_id=? AND u.deleted_at IS NULL");$s->execute([$parentId]);$out=[];foreach($s->fetchAll(PDO::FETCH_ASSOC)?:[] as $child){$success=(new StudentSuccessService($this->pdo))->assess((int)$child['id'],false);$out[]=['student_id'=>(int)$child['id'],'name'=>$child['name'],'attendance_recent'=>$success['attendance_recent'],'academic_recent'=>$success['academic_recent'],'homework_percent'=>$success['homework_percent'],'message'=>$this->message($success)];}return$out;}catch(Throwable $e){return[];}
    }
    private function message(array $s):string
    {if(($s['attendance_recent']??100)<75)return'Attention recommended: recent attendance has decreased.';if(($s['academic_recent']??100)<60)return'Attention recommended: recent assessment performance needs support.';if(($s['homework_percent']??100)<60)return'Attention recommended: homework completion could improve.';return'Good progress: no current trend requires urgent parent action.';}
}
