# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-06-13

### Added
- **Events System**: Added `WalletCredited`, `WalletDebited`, and `WalletTransferCompleted` events to listen for wallet transactions.
- **Custom Exceptions**: Introduced specific exceptions: `InsufficientBalanceException`, `WalletNotFoundException`, and `InvalidAmountException` for cleaner error handling.
- **Extensible Models**: Added `models` configuration in `config/easy-wallet.php` allowing developers to override `Wallet` and `WalletTransaction` models.
- **UUIDs for Transactions**: Switched from `uniqid()` to `Str::uuid()` to generate highly unique and secure transaction numbers.
- **Walletable Interface**: Added `Walletable` contract for better type hinting and strict architecture.
- **Strict Typing**: Applied `declare(strict_types=1);` and added return types across the codebase.

### Fixed
- **Race Conditions**: Added `lockForUpdate()` during wallet debits and transfers to prevent concurrent transactions from bypassing balance checks and causing negative balances.

## [1.0.0] - Initial Release
- Initial basic functionality.
- Credit, debit, and transfer features.
