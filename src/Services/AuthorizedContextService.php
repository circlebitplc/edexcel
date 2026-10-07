<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

final class AuthorizedContextService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function studentAcademic(int $studentId,array $auth):array
    { $this->assertStudent($studentId,$auth);$a=(new AcademicProgressService($this->pdo))->forStudent($studentId);return['student_id'=>$studentId,'subject_averages'=>$a['subject_averages'],'recent_marks'=>$a['recent_marks'],'weak_topics'=>$a['weak_topics'],'strong_topics'=>$a['strong_topics']]; }

    /** @return array<string,mixed> */
    public function studentAttendance(int $studentId,array $auth):array
    { $this->assertStudent($studentId,$auth);return['student_id'=>$studentId,'attendance'=>(new AttendanceAnalyticsService($this->pdo))->student($studentId)]; }

    /** @return array<string,mixed> */
    public function studentExam(int $studentId,array $auth):array
    { $this->assertStudent($studentId,$auth);return['student_id'=>$studentId,'exam_prep'=>(new ExamPrepService($this->pdo))->dashboard($studentId)]; }

    /** @return array<string,mixed> */
    public function studentLearning(int $studentId,array $auth):array
    { $this->assertStudent($studentId,$auth);$s=(new CoursoLearnerService($this->pdo))->snapshot($studentId);return['student_id'=>$studentId,'learning'=>['strengths'=>$s['strengths']??[],'weaknesses'=>$s['weaknesses']??[],'completed_lessons'=>$s['completed_lessons']??0,'recordings_watched'=>$s['recordings_watched']??0,'quizzes_done'=>$s['quizzes_done']??0,'next_steps'=>$s['next_steps']??[]]]; }

    private function assertStudent(int $id,array $auth):void
    { $viewer=($auth['role']??'')==='parent'?(int)($auth['parent_id']??0):(int)($auth['user_id']??0);if($id<1||!(new Student360Service($this->pdo))->canView($viewer,(string)($auth['role']??''),$id))throw new RuntimeException('Student context access denied.'); }
}
