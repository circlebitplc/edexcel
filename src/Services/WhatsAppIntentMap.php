<?php
declare(strict_types=1);

namespace Edexcel\Services;

/**
 * Coverage map for the institute WhatsApp question set.
 * Phrases are examples of how people ask — not static answers.
 */
final class WhatsAppIntentMap
{
    /**
     * @return array<string, list<string>>
     */
    public static function signals(): array
    {
        return [
            'REFUSE' => ['all students', 'every student', 'another student', 'who hasnt paid', 'all teacher phone'],
            'REGISTER' => ['register', 'sign up', 'admission', 'how to join', 'enrol', 'enroll', 'ලියාපදිංචි', 'create account'],
            'REPORT_ATTENDANCE_ERROR' => ['marked absent but', 'attendance is wrong', 'correct my attendance', 'i was there', 'forgot to sign'],
            'GET_FEES' => ['fee', 'fees', 'owe', 'outstanding', 'due', 'ගාස්තු', 'payment', 'how much do i', 'invoice'],
            'GET_ATTENDANCE' => ['attendance', 'absent', 'present today', 'පැමිණීම', 'did my child attend', 'was i present'],
            'GET_HOMEWORK' => ['homework', 'assignment', 'cw', 'classwork'],
            'GET_MATERIALS' => ['material', 'materials', 'notes', 'past paper', 'pdf', 'revision pack'],
            'GET_PROGRESS' => ['progress', 'score', 'marks', 'average', 'how am i doing', 'test result'],
            'GET_EXAMS' => ['exam', 'exams', 'mock', 'විභාග', 'assessment'],
            'GET_WHATSAPP_GROUP' => ['whatsapp group', 'group link', 'class group', 'add me to the class group'],
            'GET_TEACHER' => ['who teaches', 'who teach', 'teachers list', 'list of teachers', 'how does teach', 'lecturers'],
            'GET_TEACHER_PROFILE' => ['qualification', 'experience', 'tell me about'],
            'GET_TEACHER_CONTACT' => ['sir ge number', 'teacher number', 'teacher whatsapp', 'miss number'],
            'GET_NEXT_CLASS' => ['next class', 'coming up', 'upcoming', 'mage next', 'what should i come'],
            'GET_TOMORROW_CLASSES' => ['tomorrow', 'tmr', 'tmrw', 'heta'],
            'GET_TODAY_CLASSES' => ['today', 'tod', 'tonight', 'ada', 'this evening', 'this morning'],
            'GET_SUBJECT_SCHEDULE' => ['class times', 'class time', 'time table', 'timetable', 'what time', 'when is', 'when are'],
            'CHECK_CLASS_CAPACITY' => ['waitlist', 'waiting list', 'class full', 'places available', 'how many places'],
            'GET_AVAILABLE_CLASSES' => ['do you have', 'do you offer', 'classes available', 'what subjects', 'what classes are available', 'is admission'],
            'GET_MY_CLASSES' => ['enrolled', 'my classes', 'am i enrolled', 'can i leave'],
            'GET_HOLIDAYS' => ['holiday', 'holidays', 'closed', 'නිවාඩු', 'institute open'],
            'GET_NEWS' => ['news', 'announcement', 'whats new', 'latest update'],
            'CHECK_CANCELLATION' => ['cancelled', 'still on', 'substitute', 'postponed', 'are we having', 'replacement'],
            'GET_CLASS_ROOM' => ['where', 'room', 'koheda', 'which room', 'location'],
            'GET_TIMETABLE' => ['schedule', 'class', 'classes', 'doing', 'free', 'anything tomorrow', 'am i free'],
        ];
    }

    /**
     * Representative coverage phrases (not 6,000 hardcoded answers).
     *
     * @return list<array{intent:string,q:string}>
     */
    public static function coveragePhrases(): array
    {
        $rows = [];
        $add = static function (string $intent, array $qs) use (&$rows): void {
            foreach ($qs as $q) {
                $rows[] = ['intent' => $intent, 'q' => $q];
            }
        };

        $add('GET_TODAY_CLASSES', [
            'what classes do i have today', 'todays classes', 'ada class ekak thiyenawada',
            'classes today', 'am i having class today', 'tod class?', 'what am i doing today',
        ]);
        $add('GET_TOMORROW_CLASSES', [
            'what class do i have tomorrow', 'do i have class tmr', 'what classes do i have tomorrow',
            'tmr class?', "what's my schedule tomorrow", 'what am i doing tmr', 'am i free tomorrow',
            'do i have anything tomorrow', 'what should i come for tomorrow', 'heta ICT thiyenawada',
            'tmrw class', 'tomorrow timetable',
        ]);
        $add('GET_NEXT_CLASS', [
            'when is my next class', 'mage next class eka mokakda', 'next class', 'upcoming class',
            'when should i come',
        ]);
        $add('GET_SUBJECT_SCHEDULE', [
            'when is maths', 'IGCSE ICT class times', 'IGCSE ICT class time table',
            'do i have ICT tomorrow', 'heta ICT thiyenawada', 'what time is ICT',
            "what's my ICT timetable",
        ]);
        $add('GET_CLASS_ROOM', [
            'class eka koheda', 'where is my class', 'which room', 'where is ICT',
        ]);
        $add('GET_TEACHER', [
            'who teaches ICT', 'how does teach ICT', 'i need teachers list how does ICT',
            'list of teachers', 'who is my teacher', 'teacher kawda',
        ]);
        $add('GET_TEACHER_CONTACT', [
            'sir ge number eka denna', 'teacher whatsapp', 'miss number',
        ]);
        $add('GET_TEACHER_SCHEDULE', [
            'when does Enidu teach', 'Enidu timetable',
        ]);
        $add('GET_ATTENDANCE', [
            'was i present today', 'my attendance', 'did my child attend', 'attendance this month',
        ]);
        $add('REPORT_ATTENDANCE_ERROR', [
            'i was marked absent but i was there', 'correct my attendance',
        ]);
        $add('GET_FEES', [
            'how much do i owe', 'outstanding fees', 'did i pay this month', 'ගාස්තු',
        ]);
        $add('GET_EXAMS', [
            'when is my exam', 'mock timetable', 'next exam',
        ]);
        $add('GET_HOMEWORK', [
            'what homework do i have', 'assignments due',
        ]);
        $add('GET_MATERIALS', [
            'class notes', 'past papers', 'study materials',
        ]);
        $add('GET_PROGRESS', [
            'how am i doing', 'my marks', 'progress report',
        ]);
        $add('GET_MY_CLASSES', [
            'what classes am i enrolled in', 'my classes',
        ]);
        $add('GET_AVAILABLE_CLASSES', [
            'do you have IGCSE ICT', 'what subjects do you teach', 'what classes are available',
            'is admission open', 'do you offer AS ICT',
        ]);
        $add('CHECK_CLASS_CAPACITY', [
            'is the class full', 'how many places are available', 'waiting list',
        ]);
        $add('GET_WHATSAPP_GROUP', [
            'send the class whatsapp group', 'group link',
        ]);
        $add('CHECK_CANCELLATION', [
            'is class cancelled', 'are we having ICT tomorrow', 'substitute teacher',
        ]);
        $add('GET_HOLIDAYS', [
            'are you closed tomorrow', 'next holiday',
        ]);
        $add('GET_NEWS', [
            'any announcements', 'whats new',
        ]);
        $add('REGISTER', [
            'how to register', 'how do i join', 'create a student account',
        ]);

        return $rows;
    }

    /**
     * Intents that need a linked student/parent (not public).
     *
     * @return list<string>
     */
    public static function personalIntents(): array
    {
        return [
            'GET_TIMETABLE', 'GET_TODAY_CLASSES', 'GET_TOMORROW_CLASSES', 'GET_NEXT_CLASS',
            'GET_ATTENDANCE', 'GET_ATTENDANCE_BY_SUBJECT', 'REPORT_ATTENDANCE_ERROR',
            'GET_FEES', 'GET_OUTSTANDING_FEES', 'GET_PAYMENT_HISTORY', 'PAYMENT_INQUIRY',
            'GET_EXAMS', 'GET_NEXT_EXAM', 'GET_HOMEWORK', 'GET_MATERIALS', 'GET_PROGRESS',
            'GET_MY_CLASSES', 'GET_WHATSAPP_GROUP',
        ];
    }

    /**
     * @return list<string>
     */
    public static function teacherIntents(): array
    {
        return ['TEACHER_MY_TIMETABLE', 'TEACHER_MY_STUDENTS', 'TEACHER_PAYMENTS'];
    }
}
