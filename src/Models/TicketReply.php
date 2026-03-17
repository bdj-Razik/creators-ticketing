<?php

namespace daacreators\CreatorsTicketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReply extends Model
{
    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('creators-ticketing.table_prefix') . 'ticket_replies');
    }

    protected $casts = [
        'is_internal_note' => 'boolean',
        'is_seen' => 'boolean',
        'seen_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author() 
    {
        return $this->morphTo();
    }

    public function markSeenBy($user): void
    {
        if ($user->id == $this->author_id) {
            return;
        }

        if ($this->is_seen) {
            return;
        }

        $this->is_seen = true;
        $this->seen_by_id = $user->id;
        $this->seen_by_type = get_class($user);
        $this->seen_at = now();
        $this->saveQuietly();
    }

}