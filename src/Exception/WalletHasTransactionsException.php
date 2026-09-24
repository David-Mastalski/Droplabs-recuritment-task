<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class WalletHasTransactionsException extends RuntimeException
{
    public function __construct(int $walletId)
    {
        parent::__construct(sprintf('Wallet %d cannot be deleted because it has transaction history.', $walletId));
    }
}
