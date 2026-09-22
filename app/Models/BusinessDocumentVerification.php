<?php
// app/Models/BusinessDocumentVerification.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $business_application_id
 * @property string $verification_type
 * @property string $source_field
 * @property string|null $reference_value
 * @property string|null $submitted_value
 * @property float|null $confidence_score
 * @property bool $matched
 * @property string|null $notes
 * @property int|null $verified_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BusinessApplication|null $application
 * @property-read \App\Models\User|null $verifier
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification whereBusinessApplicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification whereConfidenceScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification whereMatched($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification whereReferenceValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification whereSourceField($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification whereSubmittedValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification whereVerificationType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocumentVerification whereVerifiedBy($value)
 * @mixin \Eloquent
 */
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