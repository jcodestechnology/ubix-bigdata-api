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
        Schema::table('export_logs', function (Blueprint $table) {
            // Add downloaded_at column to track when file was downloaded
            $table->timestamp('downloaded_at')->nullable()->after('status');
            
            // Add processed_at column to track when processing completed
            $table->timestamp('processed_at')->nullable()->after('downloaded_at');
            
            // Add error_message column for better error tracking
            $table->text('error_message')->nullable()->after('filters');
            
            // Add file_size column to store the size of the exported file
            $table->bigInteger('file_size')->nullable()->after('file_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('export_logs', function (Blueprint $table) {
            $table->dropColumn([
                'downloaded_at',
                'processed_at', 
                'error_message',
                'file_size'
            ]);
        });
    }
};