<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ProcessTransactionsCommand;
use App\Entity\Transaction;
use App\Entity\Wallet;
use App\Enum\Currency;
use App\Enum\TransactionStatus;
use App\Repository\CompanyWalletRepositoryInterface;
use App\Repository\TransactionRepositoryInterface;
use App\Repository\WalletRepositoryInterface;
use App\Service\TransactionProcessorService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[AllowMockObjectsWithoutExpectations]
class ProcessTransactionsCommandTest extends TestCase
{
    private TransactionRepositoryInterface $transactionRepository;

    private WalletRepositoryInterface $walletRepository;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->transactionRepository = $this->createMock(TransactionRepositoryInterface::class);
        $this->walletRepository = $this->createMock(WalletRepositoryInterface::class);

        $transactionProcessorService = new TransactionProcessorService(
            $this->walletRepository,
            $this->transactionRepository,
            $this->createMock(CompanyWalletRepositoryInterface::class),
        );

        $command = new ProcessTransactionsCommand(
            $this->transactionRepository,
            $transactionProcessorService,
        );

        $this->commandTester = new CommandTester($command);
    }

    public function testNoTransactionsToProcess(): void
    {
        $this->transactionRepository
            ->method('findByStatus')
            ->willReturn([]);

        $this->commandTester->execute([]);

        self::assertStringContainsString('No transactions to process.', $this->commandTester->getDisplay());
    }

    public function testPendingTransactionIsCompleted(): void
    {
        $transaction = Transaction::create(
            fromWalletId: 1,
            toWalletId: 2,
            fromAmount: '100.0000',
            toAmount: '25.0000',
            fromCurrency: Currency::PLN,
            toCurrency: Currency::EUR,
            spread: '0.5000',
            exchangeRate: '0.250000',
            requiresAntiFraudCheck: false,
        );

        $this->transactionRepository
            ->method('findByStatus')
            ->willReturnMap([
                [TransactionStatus::PENDING, [$transaction]],
                [TransactionStatus::FRAUD_REVIEW, []],
            ]);

        $fromWallet = Wallet::create(1, Currency::PLN);
        $fromWallet->setBalance(500.0);
        $toWallet = Wallet::create(1, Currency::EUR);

        $this->walletRepository
            ->method('findById')
            ->willReturnMap([
                [1, $fromWallet],
                [2, $toWallet],
            ]);

        $this->commandTester->execute([]);

        self::assertStringContainsString('completed.', $this->commandTester->getDisplay());
    }
}