<?php

namespace daacreators\CreatorsTicketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpamFilter extends Model
{
    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('creators-ticketing.table_prefix') . 'spam_filters');
    }

    protected $casts = [
        'is_active' => 'boolean',
        'case_sensitive' => 'boolean',
        'hits' => 'integer',
        'priority' => 'integer',
        'last_triggered_at' => 'datetime',
        'values' => 'array',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(SpamLog::class);
    }
}
