<?php

namespace daacreators\CreatorsTicketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use daacreators\CreatorsTicketing\Enums\TicketPriority;

class Ticket extends Model
{
    protected $guarded = [];

    protected $casts = [
        'custom_fields' => 'array',
        'last_activity_at' => 'datetime',
        'priority' => TicketPriority::class,
        'is_seen' => 'boolean',
        'seen_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (empty($ticket->last_activity_at)) {
                $ticket->last_activity_at = now();
            }
        });

        static::deleted(function (Ticket $ticket) {
            Storage::disk('private')->deleteDirectory('ticket-attachments/' . $ticket->id);
        });
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('creators-ticketing.table_prefix') . 'tickets');
    }

    public function requester(): BelongsTo
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        return $this->belongsTo($userModel, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        return $this->belongsTo($userModel, 'assignee_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'ticket_status_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    public function publicReplies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->where('is_internal_note', false);
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(TicketReply::class)->where('is_internal_note', true);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class);
    }

    public function markSeenBy($userId): void
    {
        if ($userId == $this->user_id) {
            return;
        }

        if ($this->is_seen) {
            return;
        }

        $this->is_seen = true;
        $this->seen_by = $userId;
        $this->seen_at = now();
        $this->saveQuietly();
    }

    public function markUnseen(): void
    {
        $this->is_seen = false;
        $this->seen_by = null;
        $this->seen_at = null;
        $this->saveQuietly();
    }

    public function isUnseen(): bool
    {
        return !$this->is_seen;
    }
    
    public function getCustomField(string $fieldName)
    {
        return $this->custom_fields[$fieldName] ?? null;
    }

    public function setCustomField(string $fieldName, $value): void
    {
        $customFields = $this->custom_fields ?? [];
        $customFields[$fieldName] = $value;
        $this->custom_fields = $customFields;
    }

    public static function scopeForUser($query, $userId)
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        $user = $userModel::find($userId);

        if (!$user) {
            return $query->where('id', null);
        }

        $field = config('creators-ticketing.navigation_visibility.field', 'email');
        $allowed = config('creators-ticketing.navigation_visibility.allowed', []);

        if (in_array($user->{$field} ?? null, $allowed, true)) {
            return $query;
        }

        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('assignee_id', $userId);
        });
    }

    protected function title(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->custom_fields) {
                    return 'Ticket #' . $this->ticket_uid;
                }

                $titleKeys = ['title', 'subject', 'issue_title', 'ticket_title'];
                foreach ($titleKeys as $key) {
                    if (isset($this->custom_fields[$key])) {
                        return $this->custom_fields[$key];
                    }
                }

                foreach ($this->custom_fields as $value) {
                    if (is_string($value) && strlen($value) > 0) {
                        return substr(strip_tags($value), 0, 100);
                    }
                }

                return 'Ticket #' . $this->ticket_uid;
            }
        );
    }

    protected function content(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->custom_fields) {
                    return '';
                }

                $contentKeys = ['content', 'description', 'details', 'message', 'issue_description'];
                foreach ($contentKeys as $key) {
                    if (isset($this->custom_fields[$key])) {
                        return $this->custom_fields[$key];
                    }
                }

                return '';
            }
        );
    }
}