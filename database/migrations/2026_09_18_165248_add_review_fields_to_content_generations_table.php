<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->foreignId('source_generation_id')
                ->nullable()
                ->after('project_id')
                ->constrained('content_generations')
                ->nullOnDelete();

            $table->json('draft')->nullable()->after('response');

            $table->text('regeneration_instructions')
                ->nullable()
                ->after('prompt');
        });
    }

    public function down(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->dropForeign(['source_generation_id']);
            $table->dropColumn([
                'source_generation_id',
                'draft',
                'regeneration_instructions',
            ]);
        });
    }
};
