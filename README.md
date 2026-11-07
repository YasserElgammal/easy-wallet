# Laravel Easy Wallet

**Laravel Easy Wallet** is a simple, secure, and highly extensible wallet system for Laravel applications. It allows you to associate wallets with any model, manage balances, and record transactions safely with concurrency protection, custom exceptions, and event dispatches.

## 🚀 Features

- **Wallet Association**: Attach a wallet to any Eloquent model (`Walletable` contract).
- **Multi-Wallet Support**: Support multiple named wallets per model (e.g. `default`, `points`, `cashback`, `crypto`).
- **Direct Model Helpers**: Intuitive `$user->credit()`, `$user->debit()`, `$user->transferTo()`, and `$user->balance()`.
- **Concurrency Safe**: Uses `lockForUpdate` on DB transactions to prevent race conditions during debits and transfers.
- **Enterprise Idempotency Protection**: Scoped Database Unique Constraint `['wallet_id', 'idempotency_key']` preventing double-charging across concurrent requests.
- **Payload Conflict Detection**: Detects and prevents misuse of identical idempotency keys with differing payloads (`IdempotencyConflictException`).
- **Audit Running Balance**: Records `balance_before` and `balance_after` on every transaction for effortless financial reconciliation.
- **Full & Partial Refund System**: Built-in `refund()` method with over-refund protection and `WalletRefunded` event.
- **Polymorphic References & Metadata**: Attach any model reference (e.g., `Order`, `Invoice`) and arbitrary custom `meta` JSON data to transactions.
- **Transaction History**: Tracks all `credit` and `debit` operations with secure UUIDs.
- **High Precision**: `decimal(18, 4)` precision supporting large balances, micro-transactions, and multiple denominations.
- **Events System**: Dispatches Laravel events upon transaction completion (`WalletCredited`, `WalletDebited`, `WalletTransferCompleted`, `WalletRefunded`).
- **Custom Exceptions**: Easy-to-catch exceptions (`InsufficientBalanceException`, `WalletNotFoundException`, `InvalidAmountException`, `IdempotencyConflictException`, `TransactionAlreadyRefundedException`, `InvalidRefundAmountException`).
- **Highly Extensible**: Change the package's internal models easily via configuration.

## 📦 Installation

```bash
composer require yasser-elgammal/laravel-easy-wallet
```

## 💸 Usage

### 1. Implement `Walletable` and add `HasWallet` Trait

Implement the `Walletable` contract and add the `HasWallet` trait into models that need wallets.

```php
use Illuminate\Database\Eloquent\Model;
use YasserElgammal\LaravelEasyWallet\Contracts\Walletable;
use YasserElgammal\LaravelEasyWallet\Traits\HasWallet;

class User extends Model implements Walletable
{
    use HasWallet;

    /**
     * Automatically create a wallet when the model is created.
     * Default is [true]. Set to false to disable auto creation.
     */
    protected bool $autoCreateWallet = true;
}
```

### 2. Credit Wallet

You can use the `EasyWallet` facade or the direct `$user->credit()` helper:

```php
use YasserElgammal\LaravelEasyWallet\Facades\EasyWallet;
use App\Models\User;

$user = User::find(1);

// Using the direct model helper
$transaction = $user->credit(100.00, 'Initial deposit');

// Or using the Facade
EasyWallet::credit($user, 100.00, 'Initial deposit');

// With polymorphic reference and metadata
$order = Order::find(42);
$user->credit(
    amount: 100.00,
    description: 'Order refund',
    reference: $order,
    meta: ['gateway' => 'stripe', 'refund_id' => 're_123'],
    idempotencyKey: 'refund_order_42'
);
```

### 3. Debit Wallet

```php
try {
    // Direct model helper
    $user->debit(25.00, 'Purchased course');

    // Or with idempotency and reference
    $user->debit(
        amount: 25.00,
        description: 'Purchased course',
        reference: $order,
        idempotencyKey: 'order_payment_42'
    );
} catch (\YasserElgammal\LaravelEasyWallet\Exceptions\InsufficientBalanceException $e) {
    // Handle lack of funds...
}
```

### 4. Transfer Between Wallets

```php
$fromUser = User::find(1);
$toUser = User::find(2);

try {
    // Direct model helper
    $fromUser->transferTo($toUser, 40.00, 'Transfer to friend', idempotencyKey: 'transfer_123');

    // Or via Facade
    EasyWallet::transfer($fromUser, $toUser, 40.00, 'Transfer to friend');
} catch (\Exception $e) {
    // Handle error...
}
```

### 5. Check Balance & Sufficiency

```php
// Direct helpers
$balance = $user->balance();
$balance = $user->wallet_balance; // via attribute

// Check if user has enough funds
if ($user->hasSufficientBalance(50.00)) {
    // Proceed...
}

// Or via Facade
$balance = EasyWallet::balance($user);
```

### 6. Idempotency & Conflict Detection

To prevent accidental double-charging from network retries or concurrent clicks, provide an `idempotencyKey`. The package enforces a database-level unique constraint scoped by `[wallet_id, idempotency_key]`:

```php
// First call executes normally:
$user->credit(100.00, 'Payment', idempotencyKey: 'charge_123');

// Re-running with the EXACT same payload safely replays the original transaction without double-charging:
$tx = $user->credit(100.00, 'Payment', idempotencyKey: 'charge_123');

// Re-running with the SAME key but a DIFFERENT payload (e.g., amount = 200) throws an IdempotencyConflictException:
try {
    $user->credit(200.00, 'Different amount', idempotencyKey: 'charge_123');
} catch (\YasserElgammal\LaravelEasyWallet\Exceptions\IdempotencyConflictException $e) {
    // 409 Conflict: Same idempotency key used with different parameters!
}
```

### 7. Multi-Wallet Support

Any model can hold multiple wallets differentiated by a `slug` (e.g. `default`, `points`, `cashback`, `usd`):

```php
// Credit specific wallets
$user->credit(500, 'Welcome points', wallet: 'points');
$user->credit(25.00, 'Cashback reward', wallet: 'cashback');

// Check balances per wallet
$user->balance();            // default wallet balance
$user->balance('points');    // 500
$user->balance('cashback');  // 25.00

// Access specific wallet model
$pointsWallet = $user->wallet('points')->first();
$pointsWallet->debit(50, 'Redeemed voucher');

// Get all wallets for user
$allWallets = $user->wallets;
```

### 8. Refunds & Reversals

Easily process full or partial refunds for any debit or credit transaction:

```php
$debitTx = $user->debit(50.00, 'Course subscription');

// Partial refund
$refundTx = $user->refund($debitTx, 20.00, 'Partial refund for course');

// Full refund (omit amount to refund remaining balance)
$user->refund($debitTx);

// Original transaction state is updated:
$debitTx->fresh()->is_refunded;     // true
$debitTx->fresh()->refunded_amount; // 50.00

// Excess refunds throw InvalidRefundAmountException:
// Double refunds throw TransactionAlreadyRefundedException.
```

### 9. Audit Trail & Running Balance

Each transaction records an immutable audit snapshot of the balance:

```php
$transaction = $user->credit(100.00);

echo $transaction->balance_before; // 0.00
echo $transaction->balance_after;  // 100.00
echo $transaction->reference;      // MorphTo related model (e.g., Order)
echo $transaction->meta['gateway']; // 'stripe'
```

Access transaction history directly from the model:
```php
foreach ($user->walletTransactions as $txn) {
    echo "{$txn->type}: {$txn->amount} | New Balance: {$txn->balance_after}\n";
}
```

---

## 🎧 Events

The package fires events that you can listen to in your `EventServiceProvider`:

- `YasserElgammal\LaravelEasyWallet\Events\WalletCredited`
- `YasserElgammal\LaravelEasyWallet\Events\WalletDebited`
- `YasserElgammal\LaravelEasyWallet\Events\WalletTransferCompleted`

**Example Listener:**
```php
use YasserElgammal\LaravelEasyWallet\Events\WalletCredited;

public function handle(WalletCredited $event)
{
    // Access transaction details:
    // $event->transaction->amount
    // Send email/notification to user...
}
```

---

## ⛏ Customization

### Change Prefix for Transaction Number 
By default, it uses `TXN-{UUID}`. You can customize the prefix in your `.env` file:

```bash
WALLET_TXN_PREFIX=MY-PREFIX-
```

### Extending Package Models
You can completely override the default `Wallet` and `WalletTransaction` models by publishing the config file and updating the class references.

```bash
php artisan vendor:publish --tag=easy-wallet-config
```

Then in `config/easy-wallet.php`:

```php
'models' => [
    'wallet' => \App\Models\CustomWallet::class,
    'transaction' => \App\Models\CustomWalletTransaction::class,
],
```

## 🔧 Publishing Resources

You can optionally publish resources to customize them:

### 1. Configuration File
```bash
php artisan vendor:publish --tag=easy-wallet-config
```

### 2. Migrations
```bash
php artisan vendor:publish --tag=easy-wallet-migrations
```

### 3. Models
```bash
php artisan vendor:publish --tag=easy-wallet-models
```
*(Remember to update the `config/easy-wallet.php` if you change the model namespaces!)*

---

## 🤝 Contributing

Contributions are welcome and appreciated!

If you have an idea, feature request, bug fix, or any improvement:
* Feel free to open an issue.
* Submit a Pull Request.
* Or simply get in touch if you need help.

Thank you for supporting the project! 🙌
