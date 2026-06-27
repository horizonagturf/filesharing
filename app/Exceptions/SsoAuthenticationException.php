<?php

namespace App\Exceptions;

use Exception;

class SsoAuthenticationException extends Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $translationKey,
        string $message = '',
        public readonly array $context = [],
    ) {
        parent::__construct($message !== '' ? $message : $translationKey);
    }
}
