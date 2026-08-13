<?php
namespace Edexcel\Controllers;

use Edexcel\Models\Timetable;

class TimetableController
{
    private $model;

    public function __construct(Timetable $model)
    {
        $this->model = $model;
    }

    public function getTeacherSchedule($teacher_id, $week_start)
    {
        $date_from = $week_start;
        $date_to = date('Y-m-d', strtotime($week_start . ' +6 days'));
        return $this->model->getByTeacher($teacher_id, $date_from, $date_to);
    }

    // Other methods...
}