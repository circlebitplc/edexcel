<?php
declare(strict_types=1);

namespace Edexcel\Services;

interface WhatsAppSender
{
    /**
     * @return array<string,mixed>
     */
    public function sendText(string $number, string $text): array;
}
