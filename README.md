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

### 1. Credit Wallet

```php
use EasyWallet;
use App\Models\User;

$user = User::find(1);

EasyWallet::credit($user, 100.00, 'Initial deposit');
```

---

### 2. Debit Wallet

```php
EasyWallet::debit($user, 25.00, 'Purchased course');
```

---

### 3. Transfer Between Wallets

```php
$fromUser = User::find(1);
$toUser = User::find(2);

EasyWallet::transfer($fromUser, $toUser, 40.00, 'Transfer to friend');
```

---

### 4. Get Wallet Balance

```php
$balance = EasyWallet::balance($user);

```

## ⛏ Customization


### Change prefix for wallet transaction number 
by default it's "TXN-" You can customize it by adding this attribute in `.env` file

```bash
WALLET_TXN_PREFIX=

```
## 🔧 Extra : Publishing Resources

You can optionally publish the following resources if you want to customize them:

### 🛠️ 1. Publish the Configuration File

```bash
php artisan vendor:publish --tag=easy-wallet-config
```

This will copy the config file to:

```
config/easy-wallet.php
```

---

### 🗃️ 2. Publish the Migrations

```bash
php artisan vendor:publish --tag=easy-wallet-migrations
```

This will copy the migration files to:

```
database/migrations/
```

You can then modify them if needed before running:

```bash
php artisan migrate
```

---

### 🧩 3. Publish the Models

```bash
php artisan vendor:publish --tag=easy-wallet-models
```

This will copy the Eloquent models to your application:

```
app/Models/Wallet.php
app/Models/WalletTransaction.php
```

You can publish these if you want to override or extend the default behavior.

---

## 🤝 Contributing

Contributions are welcome and appreciated!

If you have an idea, feature request, bug fix, or any improvement:

* Feel free to open an issue.
* Submit a Pull Request.
* Or simply get in touch if you need help.

Thank you for supporting the project! 🙌
