<?php
// app/Models/PermitRenewalReminder.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $year
 * @property \Illuminate\Support\Carbon|null $permit_expires_at
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 * @property int|null $renewed_document_id
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BusinessDocument|null $renewedDocument
 * @property-read \App\Models\Tenant $tenant
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermitRenewalReminder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermitRenewalReminder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermitRenewalReminder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermitRenewalReminder whereAcknowledgedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermitRenewalReminder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermitRenewalReminder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermitRenewalReminder whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermitRenewalReminder wherePermitExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermitRenewalReminder whereRenewedDocumentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermitRenewalReminder whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermitRenewalReminder whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermitRenewalReminder whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermitRenewalReminder whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PermitRenewalReminder whereYear($value)
 * @mixin \Eloquent
 */
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