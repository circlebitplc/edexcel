<?php
declare(strict_types=1);

namespace Edexcel\Services;

final class WhatsAppGuideService
{
    public static function publicBase(): string
    {
        require_once dirname(__DIR__, 2) . '/config/evolution.php';

        return rtrim(edexcel_public_app_url(), '/');
    }

    public static function page(string $path): string
    {
        return self::publicBase() . '/' . ltrim($path, '/');
    }

    /**
     * @return array<string, list<string>>
     */
    public static function topicKeywords(): array
    {
        return [
            'register' => [
                'admission', 'admissions', 'register', 'registration', 'sign up', 'signup',
                'create account', 'student account', 'how to join', 'how do i join',
                'new student', 'ලියාපදිංචි',
            ],
            'login' => [
                'login', 'log in', 'sign in', 'signin', 'student portal', 'username',
                'cant login', "can't login", 'පිවිසෙන්න',
            ],
            'password' => [
                'password', 'forgot password', 'reset password', 'change password',
                'මුරපද',
            ],
            'join' => [
                'join class', 'join a class', 'available classes', 'class group',
                'whatsapp group', 'waitlist', 'leave class', 'enrolled class',
            ],
            'fees' => [
                'fee', 'fees', 'payment', 'pay fees', 'how much', 'invoice',
                'amount due', 'ගාස්තු', 'ගෙවීම',
            ],
            'attendance' => [
                'attendance', 'absent', 'present', 'late', 'පැමිණීම',
            ],
            'parent' => [
                'parent', 'parents', 'mother', 'father', 'evening note',
                'parent whatsapp', 'guardian', 'දෙමාපිය',
            ],
            'holiday' => [
                'holiday', 'holidays', 'closed', 'public holiday', 'නිවාඩු',
            ],
            'exam' => [
                'exam', 'exams', 'mock', 'mocks', 'assessment', 'විභාග',
            ],
            'verify' => [
                'otp', 'verify', 'verification', 'whatsapp code',
            ],
            'contact' => [
                'location', 'address', 'office hours', 'open hours',
                'contact college', 'where is the college', 'where is college',
            ],
            'teachers_list' => [
                'list of teachers', 'list of teacher', 'all teachers',
                'teacher list', 'teachers list', 'our teachers',
                'who are the teachers', 'teacher names', 'teachers',
                'lecturers',
            ],
            'subject_teacher' => [
                'who teaches', 'who is my teacher', 'who is the teacher',
                'my teacher',
            ],
            'teacher' => [
                'teacher login', 'staff login', 'teacher portal', 'i am a teacher',
                'teacher account', 'mark attendance', 'teacher payment',
            ],
            'timetable' => [
                'today', 'tomorrow', 'this week', 'timetable', 'schedule',
                'my classes', 'class time',
            ],
        ];
    }

    public static function matchTopic(string $command): ?string
    {
        foreach (self::topicKeywords() as $topic => $phrases) {
            foreach ($phrases as $phrase) {
                if ($command === $phrase) {
                    return $topic;
                }
                if (strlen($phrase) > 4 && str_contains($command, $phrase)) {
                    return $topic;
                }
            }
        }

        return null;
    }

    public static function handbook(): string
    {
        $base = self::publicBase();
        $register = self::page('student/register.php');
        $login = self::page('student/login.php');
        $portal = self::page('student/dashboard.php');
        $join = self::page('student/dashboard.php?tab=join');
        $fees = self::page('student/dashboard.php?tab=fees');
        $attendance = self::page('student/dashboard.php?tab=attendance');
        $settings = self::page('student/dashboard.php?tab=settings');
        $exams = self::page('student/dashboard.php?tab=exams');
        $timetable = self::page('student/dashboard.php?tab=timetable');
        $home = $base . '/';

        return <<<TEXT
OFFICIAL EDEXCEL COLLEGE ANSWERS (use these; do not invent other links or fees):

Q: How do I register / create a student account?
A: Open {$register} and follow the instructions. Enter full name, WhatsApp number, password (8+ characters), then verify with the WhatsApp OTP. That WhatsApp number becomes the login username.

Q: How do I log in?
A: Open {$login} . Username = the student's WhatsApp number (e.g. 0771234567). Password = the one set at registration.

Q: I forgot my password / how do I change it?
A: There is no public reset email. Log in if possible and change it at {$settings} (current password + new password, 8+ characters). If they cannot log in, type *7* so an administrator can help.

Q: How do I join a class / get the WhatsApp group?
A: Log in to the portal, open {$join} , pick the class, and join. WhatsApp must be verified first (message this college bot from the student's number). After joining, the class WhatsApp group link is sent on WhatsApp. If the class is full, the student is placed on the waitlist and gets a 24-hour offer when a seat opens.

Q: How do I see my timetable?
A: Ask this bot: today, tomorrow, or this week. Or open {$timetable} . Personal times only appear after the student has joined classes.

Q: Exams / mocks?
A: Ask this bot *8* or open {$exams} . Only published papers for enrolled classes are shown.

Q: Fees / how much / how to pay?
A: Class fees are added only after the teacher marks the student present or late. See {$fees} . Pay at the college counter. A WhatsApp receipt is sent when admin marks it paid. Never invent a rupee amount unless LIVE STUDENT FEES is in the data.

Q: Attendance?
A: Teachers mark present / absent / late. Students see it at {$attendance} . Parents receive an evening WhatsApp note (6pm) with today's attendance if a parent number is saved.

Q: How do parents get updates?
A: In {$settings} save parent name + parent WhatsApp. Around 18:00 Sri Lanka time the college sends an evening note: tomorrow's classes, fees due (parent), and today's attendance.

Q: WhatsApp verification?
A: During register, an OTP is sent on WhatsApp. After that, messaging this college bot from the student's number links the account. The portal shows Verified when verified_at is set.

Q: Holidays?
A: If LIVE HOLIDAYS lists a date, the college is closed that day and class reminders are not sent. If none are listed, say you do not see an upcoming holiday and offer *7*.

Q: Where is the college / contact?
A: Edexcel College, Kandy, Sri Lanka. Public site: {$home} . For a person, type *7*. Do not invent a street address, map pin, or extra phone number.

Q: Online classes?
A: Some classes may be online; join from {$join} and use the timetable room/link shown for that lesson. Do not invent Zoom/Meet links.

Q: I am a parent messaging from my own phone.
A: If this WhatsApp is saved as the student's parent number, answer about that student. Otherwise explain they should be added under the student's Settings → Parent WhatsApp, or type *7*.

Q: Homework / notes / materials?
A: After login, open the student portal Services / class materials tabs if the teacher has uploaded files. Do not invent download links. If nothing is listed, type *7*.

Q: Can I leave a class?
A: Yes, from the portal Join/Classes area using Leave. WhatsApp group access is managed by the class admin.

Q: Wrong WhatsApp OTP / I did not get the code?
A: Wait one minute, request a new OTP from {$register}. The number must be a Sri Lankan mobile (07XXXXXXXX). If it still fails, type *7*.

Q: This number is already registered.
A: Use Student Login {$login} . Do not register again.

Q: How do teachers log in?
A: Teachers and admins use the staff login (not the student portal): {$base}/login.php then the staff dashboard. Students must use {$login} only.

Q: Teacher: where is the timetable / attendance?
A: After staff login: timetable at {$base}/timetable/index.php . Mark student attendance at {$base}/campus/attendance.php . Mark cash class fees (unlocks recordings) at {$base}/campus/lesson_fees.php . Today's campus board: {$base}/campus/today.php .

Q: Teacher: will I get WhatsApp reminders?
A: Class WhatsApp groups get a reminder at 6:00 pm (tomorrow) and 6:00 am (today) if a group invite is saved. Teachers can also get personal WhatsApp notices when a class is added, changed, or cancelled, and when a payment is recorded — if the teacher's phone is saved on their profile.

Q: Teacher: how are fees / payments handled?
A: Teachers mark cash received in class at {$base}/campus/lesson_fees.php — that unlocks the lesson recording for the student. Students can also pay online (OnePay). Monthly wallet fees are still marked at the college counter by admin. Do not quote commission figures unless asked to type *7*.
TEXT;
    }
}
