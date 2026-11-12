<?php

namespace App\Services\PedigreeImport;

use RuntimeException;

/**
 * A sheet could not be read. The message is shown to the user. $usage is set when the reader answered (and was
 * paid for) but the answer could not be used.
 */
class PedigreeReadFailed extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $usage
     */
    public function __construct(string $message, public readonly ?array $usage = null)
    {
        parent::__construct($message);
    }
}
