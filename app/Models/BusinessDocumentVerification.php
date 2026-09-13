<?php
// app/Models/BusinessDocumentVerification.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDocumentVerification extends Model
{
    protected $fillable = [
        'business_application_id',
        'verification_type',
        'source_field',
        'reference_value',
        'submitted_value',
        'confidence_score',
        'matched',
        'notes',
        'verified_by',
    ];

    protected $casts = [
        'matched'          => 'boolean',
        'confidence_score' => 'float',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(BusinessApplication::class, 'business_application_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}