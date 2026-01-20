<?php

namespace daacreators\CreatorsTicketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpamLog extends Model
{
    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('creators-ticketing.table_prefix') . 'spam_logs');
    }

    protected $casts = [
        'ticket_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        return $this->belongsTo($userModel);
    }

    public function spamFilter(): BelongsTo
    {
        return $this->belongsTo(SpamFilter::class);
    }
}
