<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Check if indexes don't exist before adding them
            if (!Schema::hasIndex('transactions', 'transactions_transaction_id_index')) {
                $table->index('transaction_id');
            }
            
            if (!Schema::hasIndex('transactions', 'transactions_user_id_index')) {
                $table->index('user_id');
            }
            
            if (!Schema::hasIndex('transactions', 'transactions_account_number_index')) {
                $table->index('account_number');
            }
            
            if (!Schema::hasIndex('transactions', 'transactions_transaction_date_index')) {
                $table->index('transaction_date');
            }
            
            if (!Schema::hasIndex('transactions', 'transactions_transaction_type_index')) {
                $table->index('transaction_type');
            }
            
            if (!Schema::hasIndex('transactions', 'transactions_status_index')) {
                $table->index('status');
            }
            
            if (!Schema::hasIndex('transactions', 'transactions_merchant_name_index')) {
                $table->index('merchant_name');
            }
            
            if (!Schema::hasIndex('transactions', 'transactions_country_index')) {
                $table->index('country');
            }
            
            if (!Schema::hasIndex('transactions', 'transactions_transaction_date_status_index')) {
                $table->index(['transaction_date', 'status']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Drop the indexes if they exist
            $table->dropIndexIfExists('transactions_transaction_id_index');
            $table->dropIndexIfExists('transactions_user_id_index');
            $table->dropIndexIfExists('transactions_account_number_index');
            $table->dropIndexIfExists('transactions_transaction_date_index');
            $table->dropIndexIfExists('transactions_transaction_type_index');
            $table->dropIndexIfExists('transactions_status_index');
            $table->dropIndexIfExists('transactions_merchant_name_index');
            $table->dropIndexIfExists('transactions_country_index');
            $table->dropIndexIfExists('transactions_transaction_date_status_index');
        });
    }
};