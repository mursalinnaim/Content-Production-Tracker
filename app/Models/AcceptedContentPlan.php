<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcceptedContentPlan extends Model
{
    protected $fillable = [
        'project_id',
        'source_generation_id',
        'content',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<ContentGeneration, $this>
     */
    public function sourceGeneration(): BelongsTo
    {
        return $this->belongsTo(ContentGeneration::class, 'source_generation_id');
    }
}
