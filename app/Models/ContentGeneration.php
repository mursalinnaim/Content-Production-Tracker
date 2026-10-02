<?php

namespace App\Models;

use Database\Factories\ContentGenerationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentGeneration extends Model
{
    /** @use HasFactory<ContentGenerationFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'generation_number',
        'source_generation_id',
        'status',
        'prompt',
        'regeneration_instructions',
        'response',
        'draft',
        'model',
        'input_tokens',
        'output_tokens',
        'input_cost_per_million',
        'output_cost_per_million',
        'cost_currency',
        'estimated_cost',
        'pricing_source',
        'pricing_checked_at',
        'error_code',
        'error_message',
        'processing_started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'draft' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'input_cost_per_million' => 'decimal:12',
            'output_cost_per_million' => 'decimal:12',
            'estimated_cost' => 'decimal:12',
            'pricing_checked_at' => 'date',
            'processing_started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ContentGeneration, $this> */
    public function sourceGeneration(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_generation_id');
    }

    /** @return HasMany<ContentGeneration, $this> */
    public function regeneratedGenerations(): HasMany
    {
        return $this->hasMany(self::class, 'source_generation_id');
    }
}
