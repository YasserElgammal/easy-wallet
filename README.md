# Laravel Easy Wallet

**Laravel Easy Wallet** is a simple, secure, and highly extensible wallet system for Laravel applications. It allows you to associate wallets with any model, manage balances, and record transactions safely with concurrency protection, custom exceptions, and event dispatches.

## 🚀 Features

- **Wallet Association**: Attach a wallet to any Eloquent model (`Walletable` contract).
- **Concurrency Safe**: Uses `lockForUpdate` on DB transactions to prevent race conditions during debits and transfers.
- **Transaction History**: Tracks all `credit` and `debit` operations with secure UUIDs.
- **Events System**: Dispatches Laravel events upon transaction completion (`WalletCredited`, `WalletDebited`, `WalletTransferCompleted`).
- **Custom Exceptions**: Easy-to-catch exceptions (`InsufficientBalanceException`, `WalletNotFoundException`, `InvalidAmountException`).
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

```php
use YasserElgammal\LaravelEasyWallet\Facades\EasyWallet;
use App\Models\User;

$user = User::find(1);

EasyWallet::credit($user, 100.00, 'Initial deposit');
```

### 3. Debit Wallet

```php
try {
    EasyWallet::debit($user, 25.00, 'Purchased course');
} catch (\YasserElgammal\LaravelEasyWallet\Exceptions\InsufficientBalanceException $e) {
    // Handle lack of funds...
}
```

### 4. Transfer Between Wallets

```php
$fromUser = User::find(1);
$toUser = User::find(2);

try {
    EasyWallet::transfer($fromUser, $toUser, 40.00, 'Transfer to friend');
} catch (\Exception $e) {
    // Handle error...
}
```

### 5. Get Wallet Balance

```php
$balance = EasyWallet::balance($user);
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
