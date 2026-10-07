<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class CommunicationPreferenceService
{
    public const MANDATORY = ['payments', 'system', 'admissions'];

    public function __construct(private PDO $pdo) {}

    public function allowed(string $audience, ?int $userId, ?int $parentId, string $category, string $channel): bool
    {
        if (in_array($category, self::MANDATORY, true) && $channel === 'in_app') {
            return true;
        }
        $pref = $this->get($audience, $userId, $parentId, $category);
        return match ($channel) {
            'whatsapp' => !empty($pref['whatsapp']),
            'sms' => !empty($pref['sms']),
            default => ($pref['in_app'] ?? 1) ? true : false,
        };
    }

    /** @return array<string,mixed> */
    public function get(string $audience, ?int $userId, ?int $parentId, string $category): array
    {
        try {
            $s = $this->pdo->prepare('SELECT * FROM communication_preferences WHERE audience=? AND COALESCE(user_id,0)=? AND COALESCE(parent_id,0)=? AND category=? LIMIT 1');
            $s->execute([$audience, $userId ?: 0, $parentId ?: 0, $category]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        } catch (Throwable $e) {
        }
        return ['in_app' => 1, 'whatsapp' => 0, 'sms' => 0, 'category' => $category];
    }

    public function save(string $audience, ?int $userId, ?int $parentId, string $category, array $flags): void
    {
        $inApp = in_array($category, self::MANDATORY, true) ? 1 : (!empty($flags['in_app']) ? 1 : 0);
        $wa = !empty($flags['whatsapp']) ? 1 : 0;
        $sms = !empty($flags['sms']) ? 1 : 0;
        try {
            $this->pdo->prepare('INSERT INTO communication_preferences(audience,user_id,parent_id,category,in_app,whatsapp,sms) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE in_app=VALUES(in_app),whatsapp=VALUES(whatsapp),sms=VALUES(sms),updated_at=NOW()')
                ->execute([$audience, $userId ?: 0, $parentId ?: 0, $category, $inApp, $wa, $sms]);
        } catch (Throwable $e) {
        }
    }
}
