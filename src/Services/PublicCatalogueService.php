<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class PublicCatalogueService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function catalogue(): array
    {
        $cache = new CacheService();
        return $cache->remember('public:catalogue:v1', function () {
            return [
                'subjects' => $this->subjects(),
                'programmes' => $this->programmes(),
                'teachers' => $this->teachers(),
                'classes' => $this->classes(),
            ];
        }, 300);
    }

    /** @return list<array<string,mixed>> */
    public function subjects(): array
    {
        try {
            return $this->pdo->query("SELECT id,name FROM subjects WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public function programmes(): array
    {
        try {
            return $this->pdo->query("SELECT id,name FROM qualifications ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public function teachers(): array
    {
        try {
            return $this->pdo->query("SELECT id,name FROM teachers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public function classes(): array
    {
        $capacity = new CapacityService($this->pdo);
        $out = [];
        try {
            $rows = $this->pdo->query("SELECT c.id,c.name,s.name subject_name,t.name teacher_name FROM student_classes c LEFT JOIN subjects s ON s.id=c.subject_id LEFT JOIN teachers t ON t.id=c.teacher_id WHERE c.deleted_at IS NULL ORDER BY c.name LIMIT 80")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        foreach ($rows as $r) {
            $st = $capacity->status((int)$r['id']);
            $out[] = [
                'id' => (int)$r['id'],
                'name' => $r['name'],
                'subject' => $r['subject_name'],
                'teacher' => $r['teacher_name'],
                'available_seats' => $st['available_seats'],
                'full' => $st['full'],
            ];
        }
        return $out;
    }
}
