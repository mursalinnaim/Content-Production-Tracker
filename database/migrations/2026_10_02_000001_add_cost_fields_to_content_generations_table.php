<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->decimal('input_cost_per_million', 20, 12)->nullable()->after('output_tokens');
            $table->decimal('output_cost_per_million', 20, 12)->nullable()->after('input_cost_per_million');
            $table->string('cost_currency', 3)->nullable()->after('output_cost_per_million');
            $table->decimal('estimated_cost', 20, 12)->nullable()->after('cost_currency');
            $table->text('pricing_source')->nullable()->after('estimated_cost');
            $table->date('pricing_checked_at')->nullable()->after('pricing_source');
        });
    }

    public function down(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->dropColumn([
                'input_cost_per_million',
                'output_cost_per_million',
                'cost_currency',
                'estimated_cost',
                'pricing_source',
                'pricing_checked_at',
            ]);
        });
    }
};
