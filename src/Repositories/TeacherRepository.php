<?php
declare(strict_types=1);

namespace Edexcel\Repositories;

use PDO;

final class TeacherRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Retrieve all active teachers with aggregated subjects and profile details.
     */
    public function listWithProfiles(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                t.*,
                tp.bio AS profile_bio,
                tp.qualifications AS profile_qualifications,
                tp.experience_years AS profile_experience,
                tp.achievements AS profile_achievements,
                tp.profile_background AS profile_background,
                tp.website AS profile_website,
                tp.facebook AS profile_facebook,
                tp.instagram AS profile_instagram,
                tp.youtube AS profile_youtube,
                GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') AS subjects
            FROM teachers t
            LEFT JOIN teacher_subjects ts ON t.id = ts.teacher_id
            LEFT JOIN subjects s ON ts.subject_id = s.id
            LEFT JOIN teacher_profiles tp ON tp.teacher_id = t.id
            WHERE t.deleted_at IS NULL
            GROUP BY t.id
            ORDER BY t.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find teacher by ID including profile.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                t.*,
                tp.bio,
                tp.qualifications,
                tp.experience_years,
                tp.achievements,
                tp.profile_background,
                tp.website,
                tp.facebook,
                tp.instagram,
                tp.youtube
            FROM teachers t
            LEFT JOIN teacher_profiles tp ON tp.teacher_id = t.id
            WHERE t.id = ? AND t.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get subject IDs assigned to teacher.
     */
    public function getSubjectIds(int $teacherId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT subject_id FROM teacher_subjects WHERE teacher_id = ?
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}
