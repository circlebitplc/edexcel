<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Seeds safe default automation rules that queue via the communication hub (async).
 */
final class CommunicationRulePackService
{
    public function __construct(private PDO $pdo) {}

    public function seedDefaults(int $userId = 0): int
    {
        $rules = [
            [
                'name' => 'Attendance absent → parent in-app',
                'event_name' => 'attendance.absent',
                'conditions_json' => '[]',
                'actions_json' => json_encode([[
                    'type' => 'communication',
                    'channel' => 'in_app',
                    'audience_type' => 'individual',
                    'category' => 'attendance',
                    'title' => 'Attendance notice',
                    'body' => 'An attendance absence was recorded for your linked student. Please review the parent portal.',
                ]], JSON_UNESCAPED_UNICODE),
                'cooldown_seconds' => 86400,
            ],
            [
                'name' => 'Fee overdue → in-app reminder',
                'event_name' => 'fee.overdue',
                'conditions_json' => '[]',
                'actions_json' => json_encode([[
                    'type' => 'communication',
                    'channel' => 'in_app',
                    'audience_type' => 'individual',
                    'category' => 'payments',
                    'title' => 'Fees due',
                    'body' => 'A fee balance is outstanding. Please pay via the portal or college counter.',
                ]], JSON_UNESCAPED_UNICODE),
                'cooldown_seconds' => 86400,
            ],
            [
                'name' => 'Application approved → student notice',
                'event_name' => 'application_approved',
                'conditions_json' => '[]',
                'actions_json' => json_encode([[
                    'type' => 'communication',
                    'channel' => 'in_app',
                    'audience_type' => 'individual',
                    'category' => 'admissions',
                    'title' => 'Application approved',
                    'body' => 'Your application has been approved. Check admissions status for next steps.',
                ]], JSON_UNESCAPED_UNICODE),
                'cooldown_seconds' => 3600,
            ],
            [
                'name' => 'Enrollment completed → welcome',
                'event_name' => 'enrollment_completed',
                'conditions_json' => '[]',
                'actions_json' => json_encode([[
                    'type' => 'communication',
                    'channel' => 'in_app',
                    'audience_type' => 'individual',
                    'category' => 'admissions',
                    'title' => 'Welcome to Edexcel College',
                    'body' => 'Enrollment is complete. Open the student portal for timetable, fees, and onboarding.',
                ]], JSON_UNESCAPED_UNICODE),
                'cooldown_seconds' => 3600,
            ],
            [
                'name' => 'Class cancelled → class notice',
                'event_name' => 'timetable.cancelled',
                'conditions_json' => '[]',
                'actions_json' => json_encode([[
                    'type' => 'notification',
                    'audience' => 'admin',
                    'title' => 'Class cancellation logged',
                    'body' => 'A class cancellation was processed through the communication platform.',
                    'priority' => 'normal',
                ]], JSON_UNESCAPED_UNICODE),
                'cooldown_seconds' => 300,
            ],
        ];
        $added = 0;
        $auto = new AutomationService($this->pdo);
        foreach ($rules as $rule) {
            try {
                $check = $this->pdo->prepare('SELECT id FROM automation_rules WHERE name=? LIMIT 1');
                $check->execute([$rule['name']]);
                if ($check->fetchColumn()) {
                    continue;
                }
                $auto->saveRule(array_merge($rule, ['enabled' => 1]), $userId);
                $added++;
            } catch (Throwable $e) {
            }
        }
        return $added;
    }
}
