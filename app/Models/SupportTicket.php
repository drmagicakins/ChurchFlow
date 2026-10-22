<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory, BelongsToTenant, Auditable;

    protected $fillable = [
        'church_id', 'created_by', 'assigned_to', 'subject', 'category',
        'description', 'priority', 'status', 'screenshot_upload_id', 'resolved_at',
    ];

    /**
     * Ticket categories and priorities, kept in one place so the controller's
     * validation, the views' dropdowns and any future filter share one list
     * rather than three that drift.
     */
    public const CATEGORIES = ['billing', 'technical', 'feature_request', 'other'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const STATUSES = ['open', 'in_progress', 'waiting_on_customer', 'resolved', 'closed'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function screenshot()
    {
        return $this->belongsTo(UploadedFile::class, 'screenshot_upload_id');
    }

    public function replies()
    {
        return $this->hasMany(SupportTicketReply::class)->orderBy('created_at');
    }

    /** What the church that raised the ticket is allowed to see — internal notes excluded. */
    public function visibleReplies()
    {
        return $this->replies()->where('is_internal_note', false);
    }
}
