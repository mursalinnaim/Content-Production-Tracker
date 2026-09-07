<?php

namespace App\Exceptions;

use RuntimeException;

class ContentGenerationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $prompt,
        public readonly string $model,
        public readonly string $errorCode = 'generation_failed',
    ) {
        parent::__construct($message);
    }
}
