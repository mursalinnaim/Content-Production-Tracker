<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->timestamp('processing_started_at')->nullable()->after('status');
            $table->timestamp('completed_at')->nullable()->after('output_tokens');
            $table->text('error_message')->nullable()->after('error_code');
        });
    }

    public function down(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->dropColumn([
                'processing_started_at',
                'completed_at',
                'error_message',
            ]);
        });
    }
};
