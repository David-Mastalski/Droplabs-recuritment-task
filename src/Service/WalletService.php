<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Wallet;
use App\Enum\Currency;
use App\Exception\WalletAlreadyExistsException;
use App\Exception\WalletBalanceNotZeroException;
use App\Exception\WalletHasTransactionsException;
use App\Exception\WalletNotFoundException;
use App\Repository\TransactionRepositoryInterface;
use App\Repository\WalletRepositoryInterface;

readonly class WalletService
{
    public function __construct(
        private WalletRepositoryInterface $walletRepository,
        private TransactionRepositoryInterface $transactionRepository,
    ) {
    }

    public function createWallet(int $userId, Currency $currency): Wallet
    {
        $existing = $this->walletRepository->findByUserIdAndCurrency($userId, $currency);

        if (null !== $existing) {
            throw new WalletAlreadyExistsException($userId, $currency);
        }

        $wallet = Wallet::create($userId, $currency);
        $this->walletRepository->save($wallet);

        return $wallet;
    }

    public function deleteWallet(int $userId, int $walletId): void
    {
        $wallet = $this->walletRepository->findById($walletId);

        if (null === $wallet || $wallet->getUserId() !== $userId) {
            throw new WalletNotFoundException($walletId);
        }

        if (0.0 !== $wallet->getBalance()) {
            throw new WalletBalanceNotZeroException($walletId);
        }

        if ([] !== $this->transactionRepository->findByWalletId($walletId)) {
            throw new WalletHasTransactionsException($walletId);
        }

        $this->walletRepository->delete($wallet);
    }

}
