<?php
declare(strict_types=1);

namespace Edexcel\Services;

/**
 * Who may see a classroom chat row. Private teacher↔student threads are
 * visible only to the sender and that recipient — never to other students,
 * and not to a host who is not a party to the thread.
 */
final class ClassroomChatVisibility
{
    /**
     * @param array<string,mixed> $row
     */
    public static function canView(array $row, int $viewerUserId): bool
    {
        if (empty($row['is_private'])) {
            return true;
        }
        if ($viewerUserId < 1) {
            return false;
        }
        return $viewerUserId === (int)($row['user_id'] ?? 0)
            || $viewerUserId === (int)($row['recipient_user_id'] ?? 0);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public static function filterVisible(array $rows, int $viewerUserId): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && self::canView($row, $viewerUserId)) {
                $out[] = $row;
            }
        }
        return $out;
    }
}
