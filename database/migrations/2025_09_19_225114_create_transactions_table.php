<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('transactions', function (Blueprint $table) {
        $table->id();
        $table->string('transaction_id',50)->unique();
        $table->bigInteger('user_id');
        $table->string('account_number',30);
        $table->dateTime('transaction_date');
        $table->enum('transaction_type',['credit','debit']);
        $table->decimal('amount',18,2);
        $table->char('currency',3);
        $table->enum('status',['pending','completed','failed']);
        $table->string('merchant_name',100)->nullable();
        $table->string('merchant_category',50)->nullable();
        $table->string('description',255)->nullable();
        $table->string('reference_number',50)->nullable();
        $table->char('country',2)->nullable();
        $table->string('city',50)->nullable();
        $table->string('ip_address',45)->nullable();
        $table->string('device',50)->nullable();
        $table->enum('channel',['online','branch','mobile'])->default('online');
        $table->decimal('fee',18,2)->default(0);
        $table->decimal('tax',18,2)->default(0);
        $table->decimal('balance_before',18,2)->nullable();
        $table->decimal('balance_after',18,2)->nullable();
        $table->integer('processing_time_ms')->nullable();
        $table->string('extra_field1',100)->nullable();
        $table->string('extra_field2',100)->nullable();
        $table->string('extra_field3',100)->nullable();
        $table->string('extra_field4',100)->nullable();
        $table->string('extra_field5',100)->nullable();
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
