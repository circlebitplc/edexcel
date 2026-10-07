<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use RuntimeException;
use Throwable;

final class OnePayCallbackHandler
{
    public function __construct(
        private PDO $pdo,
        private OnePayService $onepay,
        private PaymentTransactionService $payments,
        private StudentLessonFeeService $fees
    ) {
        date_default_timezone_set('Asia/Colombo');
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function isSuccessCallback(array $payload): bool
    {
        $status = $payload['status'] ?? null;
        $message = strtoupper(trim((string)($payload['status_message'] ?? '')));
        if ($status === 1 || $status === '1' || $status === true) {
            return true;
        }
        return $message === 'SUCCESS';
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,paid:bool,message:string,reference:?string}
     */
    public function handle(array $payload): array
    {
        $gatewayId = trim((string)($payload['transaction_id'] ?? $payload['ipg_transaction_id'] ?? ''));
        $additional = trim((string)($payload['additional_data'] ?? $payload['additionalData'] ?? ''));
        $txn = null;
        if ($gatewayId !== '') {
            $txn = $this->payments->findByGatewayId($gatewayId);
            if (!$txn) {
                $txn = $this->payments->findByReference($gatewayId);
            }
        }
        if (!$txn && $additional !== '') {
            $txn = $this->payments->findByReference($additional);
        }
        if (!$txn) {
            return ['ok' => false, 'paid' => false, 'message' => 'unknown transaction', 'reference' => null];
        }

        $ipg = (string)($txn['gateway_transaction_id'] ?? $gatewayId);
        if ($ipg === '') {
            return ['ok' => false, 'paid' => false, 'message' => 'missing gateway id', 'reference' => (string)$txn['gateway_reference']];
        }

        try {
            $verified = $this->onepay->transactionStatus($ipg);
        } catch (Throwable $e) {
            error_log('OnePay status lookup failed');
            return ['ok' => false, 'paid' => false, 'message' => 'status lookup failed', 'reference' => (string)$txn['gateway_reference']];
        }

        if (!empty($verified['paid'])) {
            $payloadStudent = PaymentVerificationService::studentIdFromPayload($payload);
            if ($payloadStudent !== null && !PaymentVerificationService::studentMatches((int)$txn['student_id'], $payloadStudent)) {
                return ['ok' => false, 'paid' => false, 'message' => 'student mismatch', 'reference' => (string)$txn['gateway_reference']];
            }
            try {
                $updated = $this->payments->applyVerifiedStatus($txn, $verified, $this->fees);
                $this->audit('onepay_paid', (int)$updated['id'], $txn);
                return ['ok' => true, 'paid' => true, 'message' => 'paid', 'reference' => (string)$txn['gateway_reference']];
            } catch (RuntimeException $e) {
                $this->audit('onepay_rejected', (int)$txn['id'], ['reason' => $e->getMessage()]);
                return ['ok' => false, 'paid' => false, 'message' => 'verification failed', 'reference' => (string)$txn['gateway_reference']];
            }
        }

        if (self::isSuccessCallback($payload) && empty($verified['paid'])) {
            $this->audit('onepay_unverified_success', (int)$txn['id'], ['callback' => 'claimed success']);
            return ['ok' => true, 'paid' => false, 'message' => 'not paid', 'reference' => (string)$txn['gateway_reference']];
        }

        $message = strtoupper((string)($payload['status_message'] ?? ''));
        if (in_array($message, ['CANCELLED', 'CANCELED'], true)) {
            $this->payments->markCancelled((int)$txn['id'], 'OnePay cancelled');
        } elseif (in_array($message, ['FAILED', 'FAIL', 'EXPIRED'], true)) {
            $this->payments->fail((int)$txn['id'], 'OnePay ' . strtolower($message));
        }
        return ['ok' => true, 'paid' => false, 'message' => 'updated', 'reference' => (string)$txn['gateway_reference']];
    }

    /**
     * @param mixed $detail
     */
    private function audit(string $action, int $recordId, $detail): void
    {
        if (function_exists('log_audit')) {
            $safe = is_array($detail) ? $detail : ['info' => (string)$detail];
            unset($safe['hash'], $safe['app_token']);
            log_audit($this->pdo, $action, 'payment_transactions', $recordId, null, $safe);
        }
    }
}
