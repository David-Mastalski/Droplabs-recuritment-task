<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class SameWalletTransferException extends RuntimeException 
{
    public function __construct(int $walletId)
    {
        parent::__construct(sprintf('Transfer to the same wallet %d is not allowed.', $walletId));
    }
}