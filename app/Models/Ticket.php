<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketSlaStatus;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'subject',
        'description',
        'priority',
        'status',
        'sla_status',
        'is_escalated',
        'sla_due_at',
        'resolved_at',
        'closed_at',
        'category_id',
        'created_by_user_id',
        'assigned_to_user_id',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'sla_status' => TicketSlaStatus::class,
            'is_escalated' => 'boolean',
            'sla_due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class)->latest();
    }

    public function isAssigned(): bool
    {
        return $this->assigned_to_user_id !== null;
    }

    public function isClosed(): bool
    {
        return $this->status === TicketStatus::CLOSED;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [TicketStatus::RESOLVED->value, TicketStatus::CLOSED->value]);
    }

    public function scopeResolvedOrClosed(Builder $query): Builder
    {
        return $query->whereIn('status', [TicketStatus::RESOLVED->value, TicketStatus::CLOSED->value]);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to_user_id');
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->isCustomer()) {
            return $query->where('created_by_user_id', $user->id);
        }

        return $query;
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function (Builder $q, $search) {
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, function (Builder $q, $status) {
                $q->where('status', $status);
            })
            ->when($filters['priority'] ?? null, function (Builder $q, $priority) {
                $q->where('priority', $priority);
            })
            ->when($filters['category_id'] ?? null, function (Builder $q, $categoryId) {
                $q->where('category_id', $categoryId);
            })
            ->when($filters['assigned_to'] ?? null, function (Builder $q, $assignedTo) {
                if ($assignedTo === 'unassigned') {
                    $q->whereNull('assigned_to_user_id');
                } else {
                    $q->where('assigned_to_user_id', $assignedTo);
                }
            })
            ->when($filters['sla_status'] ?? null, function (Builder $q, $slaStatus) {
                $q->where('sla_status', $slaStatus);
            })
            ->when(isset($filters['is_escalated']) && $filters['is_escalated'] !== '', function (Builder $q) use ($filters) {
                $q->where('is_escalated', (bool)$filters['is_escalated']);
            });
    }
}

