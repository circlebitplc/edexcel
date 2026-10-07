<?php
declare(strict_types=1);

namespace Edexcel\Services;

use Edexcel\Repositories\TimetableRepository;
use PDO;
use Throwable;

final class TimetableSuggestionService
{
    public function __construct(private PDO $pdo) {}

    /** @return list<array<string,string>> */
    public function suggest(int $teacherId,int $roomId,int $classId,string $date,string $duration='01:00'):array
    {
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new \RuntimeException('Valid date is required.');
        [$hours,$minutes]=array_map('intval',explode(':',$duration));$minutes=max(15,min(240,$hours*60+$minutes));$conflict=new TimetableConflictService(new TimetableRepository($this->pdo));$out=[];
        for($start=8*60;$start<=18*60&&count($out)<8;$start+=30){$end=$start+$minutes;$st=sprintf('%02d:%02d:00',intdiv($start,60),$start%60);$et=sprintf('%02d:%02d:00',intdiv($end,60),$end%60);if($end>21*60)continue;if(!$conflict->hasConflict($teacherId,$roomId,$classId,$date,$st,$et))$out[]=['date'=>$date,'start_time'=>$st,'end_time'=>$et,'reason'=>'No teacher, room, or class overlap found by the existing conflict service.'];}return$out;
    }
}
