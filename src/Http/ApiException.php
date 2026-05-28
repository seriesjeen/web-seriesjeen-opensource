<?php
declare(strict_types=1);

namespace App\Http;

final class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 500,
        public readonly ?array $body = null,
    ) {
        parent::__construct($message, $httpStatus);
    }
}
