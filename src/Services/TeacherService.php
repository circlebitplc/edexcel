<?php
declare(strict_types=1);

namespace Edexcel\Services;

use Edexcel\Repositories\TeacherRepository;

final class TeacherService
{
    public function __construct(private TeacherRepository $repository) {}

    /**
     * Get all teachers with resolved photo paths and completion stats.
     */
    public function getAllTeachers(): array
    {
        $teachers = $this->repository->listWithProfiles();

        foreach ($teachers as &$t) {
            $t['photo_path'] = teacherPhotoPath((int)$t['id'], $t['photo'] ?? null);
            $t['initials'] = teacherInitials((string)$t['name']);
            $t['avatar_color'] = teacherAvatarColor((string)$t['name']);
            $t['subject_list'] = parseSubjects($t['subjects'] ?? '');
            $t['completion_percent'] = $this->calculateCompletionScore($t);
        }
        unset($t);

        return $teachers;
    }

    /**
     * Calculate 5-point profile completion percentage.
     */
    public function calculateCompletionScore(array $teacher): int
    {
        $hasPhoto = !empty($teacher['photo_path']) || !empty($teacher['photo']);
        $hasBio = !empty(trim((string)($teacher['profile_bio'] ?? $teacher['bio'] ?? '')));
        $hasQual = !empty(trim((string)($teacher['profile_qualifications'] ?? $teacher['qualifications'] ?? '')));
        $hasExp = isset($teacher['profile_experience']) ? $teacher['profile_experience'] !== '' && $teacher['profile_experience'] !== null : !empty($teacher['experience_years']);
        $hasAch = !empty(trim((string)($teacher['profile_achievements'] ?? $teacher['achievements'] ?? '')));

        $fields = [$hasPhoto, $hasBio, $hasQual, $hasExp, $hasAch];
        $filled = count(array_filter($fields));

        return (int)round(($filled / count($fields)) * 100);
    }
}
