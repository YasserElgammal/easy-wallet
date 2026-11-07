# Changelog

All notable changes to this project will be documented in this file.

## [1.2.0] - 2026-11-07

### Added
- **Multi-Wallet Support**: Support multiple named wallets per model via `slug` (e.g. `$user->wallet('points')`, `$user->credit(50, wallet: 'points')`, `$user->wallets`).
- **Database-Scoped Idempotency**: Added database-level unique constraint on `['wallet_id', 'idempotency_key']` preventing duplicate concurrent inserts across parallel requests.
- **Payload Conflict Detection**: Generates a cryptographic payload hash (`payload_hash`). Re-using the same idempotency key with a differing payload throws an `IdempotencyConflictException` (409 Conflict).
- **Full & Partial Refunds**: Added `refund()` system with over-refund protection, `TransactionAlreadyRefundedException`, `InvalidRefundAmountException`, and `WalletRefunded` event.
- **Direct Model Helpers (`HasWallet`)**: Added convenient methods directly on models using `HasWallet`: `$user->credit()`, `$user->debit()`, `$user->transferTo()`, `$user->balance()`, `$user->wallet_balance`, `$user->hasSufficientBalance()`, and `$user->walletTransactions()`.
- **Audit Snapshot (`balance_before` & `balance_after`)**: Transactions now automatically capture the immutable balance snapshot before and after execution for seamless financial audits.
- **Polymorphic Reference & Meta**: Added `reference_type`, `reference_id` (`reference()` MorphTo relation) and JSON `meta` column to attach any business model (e.g. `Order`, `Invoice`) and custom payload to transactions.
- **High Precision Storage**: Upgraded wallets and transactions balance to `decimal(18, 4)` to support enterprise balance scales and micro-transactions.
- **IDE Facade Annotations**: Added full PHPDoc docblocks on `EasyWallet` Facade for auto-completion.

### Fixed
- **Stale Memory Balance**: Added automatic cache invalidation (`unsetRelation('wallet')`) after balance changes so subsequent balance checks in the same request lifecycle reflect fresh database values.

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
