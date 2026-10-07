<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;

/**
 * Teacher resource library on top of online_lesson_resources.
 * A new version is a new row; the old row stays so lessons that point at it keep working.
 */
final class ResourceLibraryService
{
    public const TYPES = [
        'pdf' => 'PDF',
        'ppt' => 'PowerPoint',
        'image' => 'Image',
        'document' => 'Document',
        'video' => 'Video',
        'url' => 'Link',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Latest versions only.
     *
     * @param array{q?:string,type?:string,subject?:string,topic?:string,tag?:string,archived?:bool} $filters
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public function search(int $userId, bool $isAdmin, array $filters, int $page = 1, int $perPage = 30): array
    {
        $where = ['r.superseded_by IS NULL', 'r.archived = ?'];
        $params = [!empty($filters['archived']) ? 1 : 0];
        if (!$isAdmin) {
            $where[] = 'r.owner_user_id = ?';
            $params[] = $userId;
        }
        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(r.title LIKE ? OR r.description LIKE ? OR r.file_name LIKE ?)';
            $like = '%' . mb_substr($q, 0, 80) . '%';
            array_push($params, $like, $like, $like);
        }
        $type = (string)($filters['type'] ?? '');
        if (isset(self::TYPES[$type])) {
            $where[] = 'r.resource_type = ?';
            $params[] = $type;
        }
        foreach (['subject', 'topic'] as $field) {
            $value = trim((string)($filters[$field] ?? ''));
            if ($value !== '') {
                $where[] = "r.{$field} LIKE ?";
                $params[] = '%' . mb_substr($value, 0, 80) . '%';
            }
        }
        $tag = trim((string)($filters['tag'] ?? ''));
        if ($tag !== '') {
            $tag = mb_substr($tag, 0, 40);
            $where[] = '(r.tags = ? OR r.tags LIKE ? OR r.tags LIKE ? OR r.tags LIKE ?)';
            array_push($params, $tag, $tag . ',%', '%,' . $tag, '%,' . $tag . ',%');
        }
        $sqlWhere = implode(' AND ', $where);
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM online_lesson_resources r WHERE $sqlWhere");
        $count->execute($params);
        $perPage = max(1, min(100, $perPage));
        $offset = (max(1, $page) - 1) * $perPage;
        $stmt = $this->pdo->prepare("
            SELECT r.*, u.username AS owner_name
            FROM online_lesson_resources r
            LEFT JOIN users u ON u.id = r.owner_user_id
            WHERE $sqlWhere
            ORDER BY r.created_at DESC, r.id DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $usage = $this->usageByRoot(array_map(static fn (array $r): int => (int)($r['root_id'] ?: $r['id']), $rows));
        foreach ($rows as &$row) {
            $row['used_in'] = $usage[(int)($row['root_id'] ?: $row['id'])] ?? 0;
        }
        unset($row);
        return ['rows' => $rows, 'total' => (int)$count->fetchColumn()];
    }

    /**
     * @return array<string,mixed>
     */
    public function editable(int $resourceId, int $userId, bool $isAdmin): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM online_lesson_resources WHERE id = ? LIMIT 1');
        $stmt->execute([$resourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (!$isAdmin && (int)$row['owner_user_id'] !== $userId)) {
            throw new RuntimeException('That resource was not found.');
        }
        return $row;
    }

    /**
     * @param array{title:string,description?:string,subject?:string,topic?:string,tags?:string,type:string,url?:string} $meta
     * @param array<string,mixed>|null $file
     */
    public function create(int $userId, array $meta, ?array $file): int
    {
        $title = mb_substr(trim((string)$meta['title']), 0, 200);
        $type = isset(self::TYPES[(string)$meta['type']]) ? (string)$meta['type'] : 'document';
        $hasFile = $file !== null && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $row = [
            'owner_user_id' => $userId,
            'title' => $title,
            'resource_type' => $type,
            'url' => null,
            'file_key' => null,
            'file_name' => null,
            'mime' => null,
            'file_size' => null,
        ];
        if ($hasFile) {
            if (in_array($type, ['url', 'video'], true)) {
                $type = 'document';
                $row['resource_type'] = $type;
            }
            $stored = LearningModuleService::storeUpload($file, 'resources/' . $userId);
            $row['file_key'] = $stored['key'];
            $row['file_name'] = $stored['name'];
            $row['mime'] = $stored['mime'];
            $row['file_size'] = (int)($file['size'] ?? 0);
            if ($title === '') {
                $row['title'] = mb_substr((string)$stored['name'], 0, 200);
            }
        } else {
            $row['url'] = OnlineLessonService::normalizeExternalUrl((string)($meta['url'] ?? ''));
            if (!in_array($type, ['url', 'video'], true)) {
                $row['resource_type'] = 'url';
            }
        }
        if ($row['title'] === '') {
            throw new RuntimeException('Enter a resource title.');
        }
        $row += self::metaFields($meta);
        return $this->insert($row + ['version_no' => 1]);
    }

    /**
     * @param array{title:string,description?:string,subject?:string,topic?:string,tags?:string} $meta
     */
    public function updateMeta(int $resourceId, int $userId, bool $isAdmin, array $meta): void
    {
        $row = $this->editable($resourceId, $userId, $isAdmin);
        $title = mb_substr(trim((string)$meta['title']), 0, 200);
        if ($title === '') {
            throw new RuntimeException('Enter a resource title.');
        }
        $fields = ['title' => $title] + self::metaFields($meta);
        $sets = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($fields)));
        $this->pdo->prepare("UPDATE online_lesson_resources SET $sets WHERE id = ?")->execute([...array_values($fields), (int)$row['id']]);
    }

    /**
     * Uploads a replacement. Lessons already using the old version keep it until the teacher switches them.
     *
     * @param array<string,mixed>|null $file
     */
    public function newVersion(int $resourceId, int $userId, bool $isAdmin, ?array $file, string $url = ''): int
    {
        $old = $this->editable($resourceId, $userId, $isAdmin);
        if (!empty($old['superseded_by'])) {
            throw new RuntimeException('Upload the new version from the latest copy of this resource.');
        }
        $root = (int)($old['root_id'] ?: $old['id']);
        $row = [
            'owner_user_id' => (int)$old['owner_user_id'],
            'title' => (string)$old['title'],
            'resource_type' => (string)$old['resource_type'],
            'url' => null,
            'file_key' => null,
            'file_name' => null,
            'mime' => null,
            'file_size' => null,
            'description' => $old['description'] ?? null,
            'subject' => $old['subject'] ?? null,
            'topic' => $old['topic'] ?? null,
            'tags' => $old['tags'] ?? null,
            'root_id' => $root,
            'version_no' => (int)($old['version_no'] ?? 1) + 1,
        ];
        if (!empty($old['file_key'])) {
            if ($file === null || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Choose the new file.');
            }
            $stored = LearningModuleService::storeUpload($file, 'resources/' . (int)$old['owner_user_id']);
            $row['file_key'] = $stored['key'];
            $row['file_name'] = $stored['name'];
            $row['mime'] = $stored['mime'];
            $row['file_size'] = (int)($file['size'] ?? 0);
        } else {
            $row['url'] = OnlineLessonService::normalizeExternalUrl($url);
        }
        $this->pdo->beginTransaction();
        try {
            $newId = $this->insert($row);
            $this->pdo->prepare('UPDATE online_lesson_resources SET superseded_by = ?, root_id = ? WHERE id = ?')->execute([$newId, $root, (int)$old['id']]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        return $newId;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function versions(int $resourceId, int $userId, bool $isAdmin): array
    {
        $row = $this->editable($resourceId, $userId, $isAdmin);
        $root = (int)($row['root_id'] ?: $row['id']);
        $stmt = $this->pdo->prepare('
            SELECT r.id, r.version_no, r.file_name, r.file_size, r.url, r.created_at, r.superseded_by,
                   (SELECT COUNT(*) FROM online_lesson_items i WHERE i.resource_id = r.id) AS used_in
            FROM online_lesson_resources r
            WHERE r.id = ? OR r.root_id = ?
            ORDER BY r.version_no DESC, r.id DESC
        ');
        $stmt->execute([$root, $root]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function latestVersionId(int $resourceId): int
    {
        $id = $resourceId;
        $stmt = $this->pdo->prepare('SELECT superseded_by FROM online_lesson_resources WHERE id = ?');
        for ($i = 0; $i < 100; $i++) {
            $stmt->execute([$id]);
            $next = (int)$stmt->fetchColumn();
            if ($next < 1 || $next === $id) {
                break;
            }
            $id = $next;
        }
        return $id;
    }

    /**
     * Lesson items using any version of each resource.
     *
     * @param list<int> $rootIds
     * @return array<int,int>
     */
    public function usageByRoot(array $rootIds): array
    {
        $rootIds = array_values(array_unique(array_filter(array_map('intval', $rootIds))));
        if ($rootIds === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($rootIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(r.root_id, r.id) AS root, COUNT(i.id) AS n
            FROM online_lesson_resources r
            JOIN online_lesson_items i ON i.resource_id = r.id
            WHERE r.id IN ($ph) OR r.root_id IN ($ph)
            GROUP BY COALESCE(r.root_id, r.id)
        ");
        $stmt->execute(array_merge($rootIds, $rootIds));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int)$row['root']] = (int)$row['n'];
        }
        return $out;
    }

    public static function formatSize(?int $bytes): string
    {
        if ($bytes === null || $bytes < 1) {
            return '';
        }
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024) . ' KB';
        }
        return round($bytes / 1048576, 1) . ' MB';
    }

    /**
     * @return array<string,?string>
     */
    private static function metaFields(array $meta): array
    {
        $clean = static function (mixed $v, int $limit): ?string {
            $v = trim((string)$v);
            return $v === '' ? null : mb_substr($v, 0, $limit);
        };
        return [
            'description' => $clean($meta['description'] ?? '', 5000),
            'subject' => $clean($meta['subject'] ?? '', 120),
            'topic' => $clean($meta['topic'] ?? '', 120),
            'tags' => QuestionPool::normalizeTags((string)($meta['tags'] ?? '')),
        ];
    }

    private function insert(array $row): int
    {
        $cols = array_keys($row);
        $this->pdo->prepare('INSERT INTO online_lesson_resources (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
            ->execute(array_values($row));
        return (int)$this->pdo->lastInsertId();
    }
}
