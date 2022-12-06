<?php

declare(strict_types=1);

namespace metalinspired\MultiComposer;

use Exception;
use Throwable;

class PluginException extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct("multi-composer: $message", $code, $previous);
    }
}
