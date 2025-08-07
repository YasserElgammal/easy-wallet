[![Stand With Palestine](https://raw.githubusercontent.com/TheBSD/StandWithPalestine/main/banner-no-action.svg)](https://thebsd.github.io/StandWithPalestine)

# Laravel Easy Wallet

**Laravel Easy Wallet** is a simple and extensible wallet system for Laravel applications. It allows you to associate wallets with any model, manage balances, and record transactions with support for `credit` and `debit` operations using enums.

## 🚀 Features

- Attach a wallet to any Eloquent model (`walletable`)
- Automatically create a wallet if it doesn’t exist
- Credit and debit balance safely inside a DB transaction
- Transaction history tracking

## 📦 Installation

```bash
composer require yasser-elgammal/laravel-easy-wallet
```

## 💸 Usage Example

### 1. Add Required Relation to related models

```php
use YasserElgammal\LaravelEasyWallet\Models\Wallet;

    public function wallet()
    {
        return $this->morphOne(Wallet::class, 'walletable');
    }
```

### 2. Credit Wallet

```php
use YasserElgammal\LaravelEasyWallet\Facades\EasyWallet;
use App\Models\User;

$user = User::find(1);

EasyWallet::credit($user, 100.00, 'Initial deposit');
```

---

### 3. Debit Wallet

```php
EasyWallet::debit($user, 25.00, 'Purchased course');
```

---

### 4. Transfer Between Wallets

```php
$fromUser = User::find(1);
$toUser = User::find(2);

EasyWallet::transfer($fromUser, $toUser, 40.00, 'Transfer to friend');
```

---

### 5. Get Wallet Balance

```php
$balance = EasyWallet::balance($user);

```

---

## 🤝 Contributing

Contributions are welcome and appreciated!

If you have an idea, feature request, bug fix, or any improvement:

* Feel free to open an issue.
* Submit a Pull Request.
* Or simply get in touch if you need help.

Thank you for supporting the project! 🙌
