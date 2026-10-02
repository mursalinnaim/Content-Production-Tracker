<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->unsignedInteger('generation_number')->nullable()->after('project_id');
        });

        $nextNumbers = [];

        DB::table('content_generations')
            ->orderBy('project_id')
            ->orderBy('id')
            ->get(['id', 'project_id'])
            ->each(function (object $generation) use (&$nextNumbers): void {
                $nextNumbers[$generation->project_id] =
                    ($nextNumbers[$generation->project_id] ?? 0) + 1;

                DB::table('content_generations')
                    ->where('id', $generation->id)
                    ->update([
                        'generation_number' => $nextNumbers[$generation->project_id],
                    ]);
            });

        Schema::table('content_generations', function (Blueprint $table) {
            $table->unique(
                ['project_id', 'generation_number'],
                'content_generations_project_generation_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->dropUnique('content_generations_project_generation_unique');
            $table->dropColumn('generation_number');
        });
    }
};
