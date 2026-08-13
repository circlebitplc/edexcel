<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\TimetableRepository;
use RuntimeException;

final class TimetableConflictService
{
    public function __construct(private TimetableRepository $repository) {}

    public function message(
        int $teacherId,int $roomId,int $classId,
        string $date,string $start,string $end,int $excludeId=0
    ): ?string {
        if ($row=$this->repository->conflict(
            'teacher_id',$teacherId,$date,$start,$end,$excludeId
        )) {
            return "Teacher '{$row['teacher_name']}' is already booked on this date from "
                .date('h:i A',strtotime($row['start_time']))
                ." to ".date('h:i A',strtotime($row['end_time']));
        }
        if ($row=$this->repository->conflict(
            'room_id',$roomId,$date,$start,$end,$excludeId
        )) {
            return "Room '{$row['room_name']}' is already occupied on this date from "
                .date('h:i A',strtotime($row['start_time']))
                ." to ".date('h:i A',strtotime($row['end_time']));
        }
        if ($row=$this->repository->conflict(
            'class_id',$classId,$date,$start,$end,$excludeId
        )) {
            return "Class '{$row['class_name']}' already has a lesson on this date from "
                .date('h:i A',strtotime($row['start_time']))
                ." to ".date('h:i A',strtotime($row['end_time']));
        }
        return null;
    }

    public function hasConflict(
        int $teacherId,int $roomId,int $classId,
        string $date,string $start,string $end,int $excludeId=0
    ): bool {
        return $this->message(
            $teacherId,$roomId,$classId,$date,$start,$end,$excludeId
        ) !== null;
    }
}
