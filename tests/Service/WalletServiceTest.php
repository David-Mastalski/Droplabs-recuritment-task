<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Transaction;
use App\Entity\Wallet;
use App\Enum\Currency;
use App\Exception\WalletAlreadyExistsException;
use App\Exception\WalletBalanceNotZeroException;
use App\Exception\WalletHasTransactionsException;
use App\Exception\WalletNotFoundException;
use App\Repository\TransactionRepositoryInterface;
use App\Repository\WalletRepositoryInterface;
use App\Service\WalletService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class WalletServiceTest extends TestCase
{
    private WalletRepositoryInterface $walletRepository;

    private TransactionRepositoryInterface $transactionRepository;
    private WalletService $walletService;

    protected function setUp(): void
    {
        $this->walletRepository = $this->createMock(WalletRepositoryInterface::class);
        $this->transactionRepository = $this->createMock(TransactionRepositoryInterface::class);

        $this->walletService = new WalletService(
            $this->walletRepository,
            $this->transactionRepository,
        );
    }


    public function testCreateWalletSuccessfully(): void
    {
        $userId = 1;
        $currency = Currency::EUR;

        $this->walletRepository
            ->expects(self::once())
            ->method('findByUserIdAndCurrency')
            ->with($userId, $currency)
            ->willReturn(null);

        $this->walletRepository
            ->expects(self::once())
            ->method('save')
            ->with($this->isInstanceOf(Wallet::class));

        $wallet = $this->walletService->createWallet($userId, $currency);

        self::assertSame($userId, $wallet->getUserId());
        self::assertSame($currency, $wallet->getCurrency());
        self::assertSame(0.0, $wallet->getBalance());
        self::assertFalse($wallet->isBlocked());
    }

    public function testCreateWalletThrowsWhenWalletAlreadyExists(): void
    {
        $userId = 1;
        $currency = Currency::PLN;

        $existingWallet = Wallet::create($userId, $currency);

        $this->walletRepository
            ->expects(self::once())
            ->method('findByUserIdAndCurrency')
            ->with($userId, $currency)
            ->willReturn($existingWallet);

        $this->walletRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(WalletAlreadyExistsException::class);
        $this->expectExceptionMessage('Wallet for user 1 in currency PLN already exists.');

        $this->walletService->createWallet($userId, $currency);
    }

    public function testDeleteWalletSuccessfully(): void
    {
        $userId = 1;
        $wallet = Wallet::create($userId, Currency::PLN);

        $this->walletRepository
            ->expects(self::once())
            ->method('findById')
            ->with(5)
            ->willReturn($wallet);

        $this->transactionRepository
            ->expects(self::once())
            ->method('findByWalletId')
            ->willReturn([]);

        $this->walletRepository
            ->expects(self::once())
            ->method('delete')
            ->with($wallet);

        $this->walletService->deleteWallet($userId, 5);
    }

    public function testDeleteWalletThrowsWhenWalletNotFound(): void
    {
        $this->walletRepository
            ->expects(self::once())
            ->method('findById')
            ->with(99)
            ->willReturn(null);

        $this->walletRepository
            ->expects(self::never())
            ->method('delete');

        $this->expectException(WalletNotFoundException::class);
        $this->expectExceptionMessage('Wallet 99 not found.');

        $this->walletService->deleteWallet(1, 99);
    }

    public function testDeleteWalletThrowsWhenWalletBelongsToOtherUser(): void
    {
        $this->walletRepository
            ->expects(self::once())
            ->method('findById')
            ->with(99)
            ->willReturn(Wallet::create(2, Currency::EUR));

        $this->walletRepository
            ->expects(self::never())
            ->method('delete');

        $this->expectException(WalletNotFoundException::class);
        $this->expectExceptionMessage('Wallet 99 not found.');

        $this->walletService->deleteWallet(1, 99);
    }

    public function testDeleteWalletThrowsWhenBalanceIsNotZero(): void
    {
        $wallet = Wallet::create(1, Currency::PLN);
        $wallet->setBalance(50.0);

        $this->walletRepository
            ->method('findById')
            ->willReturn($wallet);

        $this->walletRepository
            ->expects(self::never())
            ->method('delete');

        $this->expectException(WalletBalanceNotZeroException::class);
        $this->expectExceptionMessage('Wallet 5 cannot be deleted because it has a non-zero balance.');

        $this->walletService->deleteWallet(1, 5);   
    }

    public function testDeleteWalletHasTransactions(): void
    {
        $userId = 1;
        $wallet = Wallet::create($userId, Currency::PLN);

        $this->walletRepository
            ->expects(self::once())
            ->method('findById')
            ->with(5)
            ->willReturn($wallet);

        $this->transactionRepository
            ->expects(self::once())
            ->method('findByWalletId')
            ->willReturn([$this->createStub(Transaction::class)]);

        $this->walletRepository
            ->expects(self::never())
            ->method('delete');

        $this->expectException(WalletHasTransactionsException::class);
        $this->expectExceptionMessage('Wallet 5 cannot be deleted because it has transaction history.');

        $this->walletService->deleteWallet($userId, 5);
    }
}
