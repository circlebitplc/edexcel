<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class MessageTemplateService
{
    public const VARIABLES = [
        'student_name', 'parent_name', 'class_name', 'teacher_name',
        'date', 'time', 'subject', 'amount', 'college_name', 'programme',
    ];

    public function __construct(private PDO $pdo) {}

    /** @return list<array<string,mixed>> */
    public function list(bool $activeOnly = true): array
    {
        try {
            $sql = 'SELECT * FROM communication_templates';
            if ($activeOnly) {
                $sql .= ' WHERE active=1';
            }
            $sql .= ' ORDER BY name, channel';
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, int $userId): int
    {
        $name = trim((string)($data['name'] ?? ''));
        $body = trim((string)($data['body'] ?? ''));
        $channel = (string)($data['channel'] ?? 'in_app');
        if ($name === '' || $body === '') {
            throw new RuntimeException('Template name and body are required.');
        }
        if (!in_array($channel, ['whatsapp', 'sms', 'email', 'in_app', 'push'], true)) {
            throw new RuntimeException('Invalid channel.');
        }
        $this->assertVariables($body);
        $vars = $this->extractVariables($body);
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $this->pdo->prepare('UPDATE communication_templates SET name=?,channel=?,subject=?,body=?,variables_json=?,active=? WHERE id=?')
                ->execute([$name, $channel, $data['subject'] ?? null, $body, json_encode($vars), !isset($data['active']) || !empty($data['active']) ? 1 : 0, $id]);
            return $id;
        }
        $this->pdo->prepare('INSERT INTO communication_templates(name,channel,subject,body,variables_json,active,created_by) VALUES(?,?,?,?,?,?,?)')
            ->execute([$name, $channel, $data['subject'] ?? null, $body, json_encode($vars), 1, $userId ?: null]);
        return (int)$this->pdo->lastInsertId();
    }

    /** @param array<string,scalar|null> $vars */
    public function render(string $body, array $vars): string
    {
        $this->assertVariables($body);
        return (string)preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static function (array $m) use ($vars): string {
            $key = $m[1];
            if (!array_key_exists($key, $vars) || $vars[$key] === null || $vars[$key] === '') {
                return '—';
            }
            return (string)$vars[$key];
        }, $body);
    }

    public function assertVariables(string $body): void
    {
        foreach ($this->extractVariables($body) as $var) {
            if (!in_array($var, self::VARIABLES, true)) {
                throw new RuntimeException('Unknown template variable: {{'.$var.'}}');
            }
        }
    }

    /** @return list<string> */
    public function extractVariables(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $body, $m);
        return array_values(array_unique($m[1] ?? []));
    }

    public function seedDefaults(int $userId = 0): int
    {
        $defaults = [
            ['Attendance absence', 'in_app', 'Attendance notice', '{{parent_name}}, {{student_name}} was absent from {{class_name}} on {{date}}.'],
            ['Payment received', 'in_app', 'Payment confirmation', 'Payment of Rs {{amount}} for {{student_name}} has been received.'],
            ['Homework posted', 'in_app', 'New homework', 'New homework has been posted for {{class_name}} ({{subject}}).'],
            ['Exam reminder', 'in_app', 'Examination reminder', '{{student_name}}, your upcoming examination for {{subject}} is scheduled for {{date}} at {{time}}.'],
            ['Admission approved', 'in_app', 'Application approved', 'Your application has been approved. Welcome to {{college_name}}.'],
            ['Class updated', 'in_app', 'Class schedule update', 'The schedule for {{class_name}} taught by {{teacher_name}} has been updated.'],
            ['General notice', 'in_app', 'College notice', 'Important notice from {{college_name}}.'],
        ];
        $n = 0;
        foreach ($defaults as [$name, $channel, $subject, $body]) {
            try {
                $s = $this->pdo->prepare('SELECT id FROM communication_templates WHERE name=? AND channel=? LIMIT 1');
                $s->execute([$name, $channel]);
                if ($s->fetchColumn()) {
                    continue;
                }
                $this->save(compact('name', 'channel', 'subject', 'body'), $userId);
                $n++;
            } catch (Throwable $e) {
            }
        }
        return $n;
    }
}
