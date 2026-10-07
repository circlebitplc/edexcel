<?php
declare(strict_types=1);

namespace Edexcel\Services;

use RuntimeException;

final class TimetableInputValidator
{
    public static function validate(array $data): array
    {
        foreach (['teacher_id','subject_id','class_id','room_id'] as $key) {
            if ((int)($data[$key] ?? 0) <= 0) {
                throw new RuntimeException('All timetable fields are required.');
            }
        }

        $date=(string)($data['date']??'');
        $start=(string)($data['start_time']??'');
        $end=(string)($data['end_time']??'');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) {
            throw new RuntimeException('Invalid timetable date.');
        }
        $dateObject=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            throw new RuntimeException('Invalid timetable date.');
        }
        if (!preg_match('/^\d{2}:\d{2}$/',$start) ||
            !preg_match('/^\d{2}:\d{2}$/',$end)) {
            throw new RuntimeException('Invalid timetable time.');
        }
        if ($start >= $end) {
            throw new RuntimeException('Start time must be before end time.');
        }

        $count=(int)($data['student_count']??0);
        if($count<0) throw new RuntimeException('Student count cannot be negative.');

        $status=(string)($data['payment_status']??'pending');
        if(!in_array($status,['pending','paid'],true)) {
            throw new RuntimeException('Invalid payment status.');
        }

        return $data;
    }

    public static function validateRepeat(
        array $data,
        bool $repeat,
        string $until
    ): void {
        if (!$repeat) {
            return;
        }

        $dayOfWeek = (string)($data['day_of_week'] ?? '');
        if ($dayOfWeek === '') {
            $dayOfWeek = date('l', strtotime((string)$data['date']));
        }

        if (!in_array(
            strtolower($dayOfWeek),
            ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'],
            true
        )) {
            throw new RuntimeException('Invalid recurring day.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
            throw new RuntimeException('Invalid repeat end date.');
        }

        $untilDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $until);

        if (!$untilDate || $untilDate->format('Y-m-d') !== $until) {
            throw new RuntimeException('Invalid repeat end date.');
        }

        $startDate = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            (string)$data['date']
        );

        if ($startDate && $untilDate < $startDate) {
            throw new RuntimeException(
                'Repeat end date cannot be before the timetable date.'
            );
        }
    }
}
