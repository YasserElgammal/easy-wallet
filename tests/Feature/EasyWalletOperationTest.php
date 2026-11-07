<?php

namespace YasserElgammal\LaravelEasyWallet\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use YasserElgammal\LaravelEasyWallet\EasyWalletServiceProvider;
use YasserElgammal\LaravelEasyWallet\Exceptions\IdempotencyConflictException;
use YasserElgammal\LaravelEasyWallet\Exceptions\InvalidAmountException;
use YasserElgammal\LaravelEasyWallet\Exceptions\InvalidRefundAmountException;
use YasserElgammal\LaravelEasyWallet\Exceptions\TransactionAlreadyRefundedException;
use YasserElgammal\LaravelEasyWallet\Services\EasyWalletOperation;
use YasserElgammal\LaravelEasyWallet\Tests\Models\User;

class EasyWalletOperationTest extends TestCase
{
    use RefreshDatabase;

    protected EasyWalletOperation $walletService;

    protected function getPackageProviders($app)
    {
        return [
            EasyWalletServiceProvider::class,
        ];
    }

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
        $this->assertDatabaseHas('wallets', [
            'walletable_id' => $user->id,
            'slug'          => 'default',
        ]);
    }

    public function test_credits_amount_to_wallet(): void
    {
        $user = User::factory()->create();
        $this->walletService->credit($user, 100);

        $this->assertEquals(100, $user->wallet->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id'      => $user->wallet->id,
            'type'           => 'credit',
            'amount'         => 100,
            'balance_before' => 0,
            'balance_after'  => 100,
        ]);
    }

    public function test_debits_amount_from_wallet(): void
    {
        $user = User::factory()->create();
        $this->walletService->credit($user, 100);

        $this->walletService->debit($user, 40);

        $this->assertEquals(60, $user->wallet->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'type'           => 'debit',
            'amount'         => -40,
            'balance_before' => 100,
            'balance_after'  => 60,
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
            'type'           => 'debit',
            'amount'         => -50,
            'balance_before' => 200,
            'balance_after'  => 150,
            'from_wallet_id' => $sender->wallet->id,
            'to_wallet_id'   => $receiver->wallet->id,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'type'           => 'credit',
            'amount'         => 50,
            'balance_before' => 0,
            'balance_after'  => 50,
            'from_wallet_id' => $sender->wallet->id,
            'to_wallet_id'   => $receiver->wallet->id,
        ]);
    }

    public function test_returns_zero_balance_if_wallet_does_not_exist(): void
    {
        $user = User::factory()->create();
        $this->assertEquals(0, $this->walletService->balance($user, 'non_existent'));
    }

    public function test_throws_exception_if_amount_is_zero_or_negative(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidAmountException::class);
        $this->walletService->credit($user, 0);
    }

    public function test_throws_exception_if_transfer_is_to_same_wallet(): void
    {
        $user = User::factory()->create();
        $this->walletService->credit($user, 100);

        $this->expectException(InvalidAmountException::class);
        $this->expectExceptionMessage('Cannot transfer to the same wallet');

        $this->walletService->transfer($user, $user, 50);
    }

    public function test_direct_trait_methods_on_model(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        // Direct credit
        $user->credit(150.00, 'Initial deposit');
        $this->assertEquals(150.00, $user->balance());
        $this->assertEquals(150.00, $user->wallet_balance);
        $this->assertTrue($user->hasSufficientBalance(100));
        $this->assertFalse($user->hasSufficientBalance(200));

        // Direct debit
        $user->debit(50.00, 'Purchased subscription');
        $this->assertEquals(100.00, $user->balance());

        // Direct transfer
        $user->transferTo($recipient, 30.00, 'Sent money to friend');
        $this->assertEquals(70.00, $user->balance());
        $this->assertEquals(30.00, $recipient->balance());

        // Direct relationship to wallet transactions
        $this->assertCount(3, $user->walletTransactions);
    }

    public function test_polymorphic_reference_and_meta_recording(): void
    {
        $user = User::factory()->create();
        $order = User::factory()->create();

        $transaction = $user->credit(
            amount: 75.50,
            description: 'Payment for order',
            reference: $order,
            meta: ['gateway' => 'stripe', 'ip' => '127.0.0.1']
        );

        $this->assertEquals($order->id, $transaction->reference_id);
        $this->assertEquals($order->getMorphClass(), $transaction->reference_type);
        $this->assertEquals('stripe', $transaction->meta['gateway']);
        $this->assertInstanceOf(User::class, $transaction->reference);
    }

    public function test_idempotency_replays_identical_requests(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();
        $key = 'txn-idempotency-12345';

        // Credit replay with same payload
        $tx1 = $user->credit(100.00, 'First try', idempotencyKey: $key);
        $tx2 = $user->credit(100.00, 'First try', idempotencyKey: $key);

        $this->assertEquals($tx1->id, $tx2->id);
        $this->assertEquals(100.00, $user->balance());
        $this->assertEquals(1, $user->walletTransactions()->count());

        // Debit replay
        $debitKey = 'debit-idempotency-999';
        $d1 = $user->debit(30.00, 'Debit first', idempotencyKey: $debitKey);
        $d2 = $user->debit(30.00, 'Debit first', idempotencyKey: $debitKey);

        $this->assertEquals($d1->id, $d2->id);
        $this->assertEquals(70.00, $user->balance());

        // Transfer replay
        $transferKey = 'transfer-key-abc';
        $user->transferTo($recipient, 20.00, 'Transfer 1', idempotencyKey: $transferKey);
        $user->transferTo($recipient, 20.00, 'Transfer 1', idempotencyKey: $transferKey);

        $this->assertEquals(50.00, $user->balance());
        $this->assertEquals(20.00, $recipient->balance());
    }

    public function test_idempotency_throws_conflict_when_payload_differs(): void
    {
        $user = User::factory()->create();
        $key = 'txn-conflict-key';

        // First attempt with amount = 100
        $user->credit(100.00, 'Initial payment', idempotencyKey: $key);

        // Second attempt with SAME key but DIFFERENT amount = 200 -> Must throw Conflict
        $this->expectException(IdempotencyConflictException::class);
        $this->expectExceptionMessage("Idempotency key '{$key}' was already used with a different payload.");

        $user->credit(200.00, 'Initial payment', idempotencyKey: $key);
    }

    public function test_idempotency_is_scoped_per_wallet(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $sharedKey = 'order-invoice-500';

        // Both wallets can use the same key because the DB constraint is scoped by [wallet_id, idempotency_key]
        $tx1 = $user1->credit(50.00, 'Order 500', idempotencyKey: $sharedKey);
        $tx2 = $user2->credit(50.00, 'Order 500', idempotencyKey: $sharedKey);

        $this->assertNotEquals($tx1->id, $tx2->id);
        $this->assertEquals(50.00, $user1->balance());
        $this->assertEquals(50.00, $user2->balance());
    }

    public function test_multi_wallet_support(): void
    {
        $user = User::factory()->create();

        // Credit points wallet
        $user->credit(500, 'Welcome bonus points', wallet: 'points');
        $this->assertEquals(0, $user->balance()); // default wallet remains 0
        $this->assertEquals(500, $user->balance('points'));

        // Credit cashback wallet
        $user->credit(25.00, 'Cashback reward', wallet: 'cashback');

        $this->assertEquals(25.00, $user->balance('cashback'));
        $this->assertCount(3, $user->wallets); // default, points, cashback
    }

    public function test_refund_system_for_debit_transaction(): void
    {
        $user = User::factory()->create();
        $user->credit(100.00);

        // User spends 60
        $debitTx = $user->debit(60.00, 'Subscription payment');
        $this->assertEquals(40.00, $user->balance());

        // Partial refund of 20
        $refundTx1 = $user->refund($debitTx, 20.00, 'Partial refund');
        $this->assertEquals(60.00, $user->balance());
        $this->assertEquals('refund', $refundTx1->type);
        $this->assertEquals(20.00, $refundTx1->amount);
        $this->assertEquals($debitTx->id, $refundTx1->parent_transaction_id);
        $this->assertFalse($debitTx->fresh()->is_refunded);
        $this->assertEquals(20.00, $debitTx->fresh()->refunded_amount);

        // Remaining refund of 40 (completes full refund)
        $user->refund($debitTx);
        $this->assertEquals(100.00, $user->balance());
        $this->assertTrue($debitTx->fresh()->is_refunded);
        $this->assertEquals(60.00, $debitTx->fresh()->refunded_amount);

        // Attempting to refund again throws TransactionAlreadyRefundedException
        $this->expectException(TransactionAlreadyRefundedException::class);
        $user->refund($debitTx, 10.00);
    }

    public function test_refund_throws_exception_on_excessive_amount(): void
    {
        $user = User::factory()->create();
        $user->credit(100.00);
        $debitTx = $user->debit(50.00);

        $this->expectException(InvalidRefundAmountException::class);
        $user->refund($debitTx, 60.00);
    }

    public function test_audit_running_balance_sequence(): void
    {
        $user = User::factory()->create();

        $t1 = $user->credit(100.00);
        $this->assertEquals(0.00, $t1->balance_before);
        $this->assertEquals(100.00, $t1->balance_after);

        $t2 = $user->credit(50.00);
        $this->assertEquals(100.00, $t2->balance_before);
        $this->assertEquals(150.00, $t2->balance_after);

        $t3 = $user->debit(30.00);
        $this->assertEquals(150.00, $t3->balance_before);
        $this->assertEquals(120.00, $t3->balance_after);
    }
}
