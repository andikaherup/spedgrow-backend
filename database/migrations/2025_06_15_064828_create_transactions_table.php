// database/migrations/xxxx_create_transactions_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('type'); // debit, credit
            $table->string('status'); // pending, completed, failed
            $table->string('merchant_name')->nullable();
            $table->string('category')->nullable();
            $table->json('nfc_data')->nullable();
            $table->timestamp('transaction_date');
            $table->timestamps();

            $table->index(['transaction_date', 'status']);
            $table->index('type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
