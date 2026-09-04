<?php

namespace App\Modules\Account\Exceptions;

use InvalidArgumentException;

class AccountUpdateException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
