<?php
// app/Models/PermitRenewalReminder.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermitRenewalReminder extends Model
{
    protected $fillable = [
        'tenant_id',
        'year',
        'permit_expires_at',
        'status',
        'sent_at',
        'acknowledged_at',
        'renewed_document_id',
        'notes',
    ];

    protected $casts = [
        'permit_expires_at' => 'date',
        'sent_at'           => 'datetime',
        'acknowledged_at'   => 'datetime',
    ];

    public const STATUS_PENDING      = 'pending';
    public const STATUS_SENT         = 'sent';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RENEWED      = 'renewed';
    public const STATUS_EXPIRED      = 'expired';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function renewedDocument(): BelongsTo
    {
        return $this->belongsTo(BusinessDocument::class, 'renewed_document_id');
    }
}