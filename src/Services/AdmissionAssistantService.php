<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class AdmissionAssistantService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed> */
    public function summarize(int $applicationId): array
    {
        $life = new AdmissionLifecycleService($this->pdo);
        $app = $life->get($applicationId);
        if (!$app) {
            return [];
        }
        $timeline = $life->timeline((int)($app['lead_id'] ?? 0) ?: null, $applicationId, (int)($app['student_id'] ?? 0) ?: null);
        $docs = $life->documents($applicationId);
        $recs = (new ClassAllocationService($this->pdo))->recommend([
            'class_ids' => json_decode((string)($app['selected_classes_json'] ?? '[]'), true) ?: [],
        ]);
        $facts = [
            'applicant' => $app['full_name'],
            'status' => $app['lifecycle_status'] ?? $app['status'],
            'qualification' => $app['qualification_label'] ?? null,
            'documents' => count($docs),
            'timeline' => array_slice($timeline, -8),
            'top_class' => $recs[0]['class_name'] ?? null,
            'missing' => $this->missing($app, $docs),
        ];
        $text = '';
        $ai = new WhatsAppAiService($this->pdo);
        if ($ai->enabled()) {
            try {
                $text = (string)$ai->completeText(
                    'You are an admissions assistant. Summarize only the supplied facts. Recommend a follow-up priority and next human action. Never reject, approve, or score a person. Never use protected characteristics. Human staff remain responsible for admission decisions.',
                    json_encode($facts, JSON_UNESCAPED_UNICODE),
                    [],
                    700
                );
            } catch (Throwable $e) {
            }
        }
        if (trim($text) === '') {
            $text = $this->deterministic($facts);
        }
        return ['facts' => $facts, 'summary' => $text, 'disclaimer' => 'AI assistance is advisory only. Staff make every admission decision.'];
    }

    /** @param array<string,mixed> $app @param list<array<string,mixed>> $docs */
    private function missing(array $app, array $docs): array
    {
        $missing = [];
        if (trim((string)($app['phone'] ?? '')) === '') {
            $missing[] = 'Student phone';
        }
        if (trim((string)($app['parent_phone'] ?? '')) === '') {
            $missing[] = 'Parent contact';
        }
        if ($docs === []) {
            $missing[] = 'Supporting documents';
        }
        return $missing;
    }

    private function deterministic(array $facts): string
    {
        $missing = $facts['missing'] ? implode(', ', $facts['missing']) : 'no obvious required fields';
        return 'Status: '.$facts['status'].'. Missing: '.$missing.'. Suggested next action: review documents and class availability, then a staff member should decide. This is not an admission decision.';
    }
}
