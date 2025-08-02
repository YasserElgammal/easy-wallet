<?php

namespace YasserElgammal\LaravelEasyWallet\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use YasserElgammal\LaravelEasyWallet\Services\EasyWalletOperation;
use YasserElgammal\LaravelEasyWallet\Tests\Models\User;

class EasyWalletOperationTest extends TestCase
{
    use RefreshDatabase;

    protected EasyWalletOperation $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->walletService = new EasyWalletOperation();
    }

    public function test_user_creation(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'email' => $user->email,
        ]);
    }

    public function test_credits_amount_to_wallet(): void
    {
        $user = User::factory()->create();
        $this->walletService->credit($user, 100);

        $this->assertEquals(100, $user->wallet->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $user->wallet->id,
            'type' => 'credit',
            'amount' => 100,
        ]);
    }

    public function test_debits_amount_from_wallet(): void
    {
        $user = User::factory()->create();
        $this->walletService->credit($user, 100);

        $this->walletService->debit($user, 40);

        $this->assertEquals(60, $user->wallet->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'debit',
            'amount' => -40,
        ]);
    }

    public function test_throws_exception_on_insufficient_balance(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $user = User::factory()->create();
        $this->walletService->credit($user, 50);
        $this->walletService->debit($user, 100);
    }

    public function test_transfers_balance_between_wallets(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $this->walletService->credit($sender, 200);

        $this->walletService->transfer($sender, $receiver, 50, 'test transfer');

        $this->assertEquals(150, $sender->fresh()->wallet->balance);
        $this->assertEquals(50, $receiver->fresh()->wallet->balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'debit',
            'amount' => -50,
            'from_wallet_id' => $sender->wallet->id,
            'to_wallet_id' => $receiver->wallet->id,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'credit',
            'amount' => 50,
            'from_wallet_id' => $sender->wallet->id,
            'to_wallet_id' => $receiver->wallet->id,
        ]);
    }

    public function test_returns_zero_balance_if_wallet_does_not_exist(): void
    {
        $user = User::factory()->create();
        $this->assertEquals(0, $this->walletService->balance($user));
    }

    public function test_throws_exception_if_amount_is_zero_or_negative(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->walletService->credit($user, 0);
    }

    public function test_throws_exception_if_transfer_is_to_same_wallet(): void
    {
        $user = User::factory()->create();
        $this->walletService->credit($user, 100);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot transfer to the same wallet');

        $this->walletService->transfer($user, $user, 50);
    }
}
