<?php
namespace Edexcel\Models;

use PDO;
use App\Repositories\TimetableRepository;

class Timetable
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getByTeacher($teacher_id, $date_from, $date_to)
    {
        $repository = new TimetableRepository($this->pdo);
        return $repository->listFiltered(
            (int)$teacher_id, 0, 0, $date_from, $date_to
        );
    }

    public function create($data)
    {
        // Compatibility adapter: authoritative mutation lives in the repository.
        require_once __DIR__ . '/../Repositories/TimetableRepository.php';
        $repository = new \App\Repositories\TimetableRepository($this->pdo);

        return $repository->create([
            'teacher_id' => $data['teacher_id'],
            'subject_id' => $data['subject_id'],
            'class_id' => $data['class_id'],
            'room_id' => $data['room_id'],
            'student_count' => $data['student_count'] ?? 0,
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'payment_status' => $data['payment_status'] ?? 'pending',
            'payment_date' => $data['payment_date'] ?? null,
        ]);
    }

    // More methods can be added...
}