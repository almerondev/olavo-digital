<?php

namespace App\Support\Http;

use RuntimeException;

/** Erro de regra de negócio com mensagem pronta para o usuário. */
class ApiException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode, public readonly int $status = 400)
    {
        parent::__construct($message);
    }
}
