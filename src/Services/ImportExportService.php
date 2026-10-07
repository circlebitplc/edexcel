<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class ImportExportService
{
    public const DATASETS=['students','teachers','classes','marks','attendance'];
    public function __construct(private PDO $pdo) {}

    /** @return array{headers:list<string>,rows:list<array<string,string>>,errors:list<string>} */
    public function preview(string $dataset,string $path,int $maxRows=500):array
    {
        if(!in_array($dataset,self::DATASETS,true)||!is_file($path))throw new RuntimeException('Unsupported import.');
        $fh=fopen($path,'rb');if(!$fh)throw new RuntimeException('Cannot read import.');
        $headers=array_map(static fn($v)=>strtolower(trim((string)$v)),fgetcsv($fh)?:[]);$rows=[];$errors=[];$n=1;
        while(($r=fgetcsv($fh))!==false&&count($rows)<$maxRows){$n++;if(count($r)!==count($headers)){$errors[]="Row {$n}: wrong column count.";continue;}$rows[]=array_combine($headers,array_map('trim',$r));}
        fclose($fh);$required=['students'=>['username'],'teachers'=>['name'],'classes'=>['name'],'marks'=>['student_id','score'],'attendance'=>['student_id','timetable_id','status']][$dataset];
        foreach($required as $key)if(!in_array($key,$headers,true))$errors[]="Missing required column: {$key}.";
        return ['headers'=>$headers,'rows'=>$rows,'errors'=>$errors];
    }

    /** @return array{imported:int,errors:list<string>} */
    public function import(string $dataset,array $rows,int $userId):array
    {
        if(!in_array($dataset,self::DATASETS,true))throw new RuntimeException('Unsupported import.');
        $imported=0;$errors=[];
        $this->pdo->beginTransaction();
        try{
            foreach($rows as $index=>$r){try{
                if($dataset==='students'){$s=$this->pdo->prepare("INSERT INTO users(username,password_hash,role,is_active) VALUES(?,?, 'student',1)");$s->execute([$r['username'],password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT)]);}
                elseif($dataset==='teachers'){$this->pdo->prepare('INSERT INTO teachers(name,phone,email) VALUES(?,?,?)')->execute([$r['name'],$r['phone']??null,$r['email']??null]);}
                elseif($dataset==='classes'){$this->pdo->prepare('INSERT INTO student_classes(name,class_code,description) VALUES(?,?,?)')->execute([$r['name'],$r['class_code']??null,$r['description']??null]);}
                elseif($dataset==='marks'){$this->pdo->prepare("INSERT INTO student_progress(student_id,subject_id,metric,score,max_score,recorded_at,created_by) VALUES(?,?,?,?,?,?,?)")->execute([(int)$r['student_id'],isset($r['subject_id'])?(int)$r['subject_id']:null,$r['metric']??'Imported mark',(float)$r['score'],isset($r['max_score'])?(float)$r['max_score']:100,$r['recorded_at']??date('Y-m-d'),$userId]);}
                else{$this->pdo->prepare("INSERT INTO student_attendance(student_id,timetable_id,status,marked_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),marked_by=VALUES(marked_by)")->execute([(int)$r['student_id'],(int)$r['timetable_id'],$r['status'],$userId]);}
                $imported++;
            }catch(Throwable $e){$errors[]='Row '.($index+2).': '.$e->getMessage();}}
            if($errors!==[])throw new RuntimeException('Import validation failed; no rows were committed.');
            $this->pdo->commit();if(function_exists('log_audit'))log_audit($this->pdo,'data_import',$dataset,null,null,['rows'=>$imported]);return compact('imported','errors');
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
