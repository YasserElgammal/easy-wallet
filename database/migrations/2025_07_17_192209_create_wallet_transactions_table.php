<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->nullable()->index();
            $table->string('idempotency_key')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->enum('type', ['credit', 'debit', 'refund']);
            $table->decimal('amount', 18, 4);
            $table->decimal('balance_before', 18, 4)->nullable();
            $table->decimal('balance_after', 18, 4)->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('parent_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->boolean('is_refunded')->default(false);
            $table->decimal('refunded_amount', 18, 4)->default(0);
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->foreignId('from_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignId('to_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->timestamps();

            $table->unique(['wallet_id', 'idempotency_key'], 'wallet_txn_idempotency_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
