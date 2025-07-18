# Laravel Easy Wallet

**Laravel Easy Wallet** is a simple and extensible wallet system for Laravel applications. It allows you to associate wallets with any model, manage balances, and record transactions with support for `credit` and `debit` operations using enums.

## 🚀 Features

- Attach a wallet to any Eloquent model (`walletable`)
- Automatically create a wallet if it doesn’t exist
- Credit and debit balance safely inside a DB transaction
- Transaction history tracking

## 📦 Installation

```bash
composer require yasser-elgammal/easy-wallet
