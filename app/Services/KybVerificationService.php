<?php

namespace App\Services;

use App\Models\BusinessApplication;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentVerification;
use Illuminate\Support\Str;

class KybVerificationService
{
    /**
     * Run automated name + TIN cross-matching on a submitted application.
     *
     * @return array{tin_match: bool|null, name_matches: array<int, bool>, all_names_match: bool}
     */
    public function verify(BusinessApplication $application): array
    {
        $application->loadMissing('documents');

        /** @var BusinessDocument|null $birDoc */
        $birDoc = $application->documents->firstWhere('document_type', BusinessDocument::TYPE_BIR_2303);

        /** @var BusinessDocument|null $dtiDoc */
        $dtiDoc = $application->documents->firstWhere('document_type', BusinessDocument::TYPE_DTI_SEC_CDA);

        /** @var BusinessDocument|null $ownerIdDoc */
        $ownerIdDoc = $application->documents->firstWhere('document_type', BusinessDocument::TYPE_OWNER_ID);

        // ── TIN match against BIR Form 2303 ─────────────────
        $tinMatch = null;
        if ($birDoc && $birDoc->document_number && $application->tin_number) {
            $matched = $this->compareTin($birDoc->document_number, $application->tin_number);

            BusinessDocumentVerification::create([
                'business_application_id' => $application->id,
                'verification_type'       => 'tin_match',
                'source_field'            => 'tin_number',
                'reference_value'         => $birDoc->document_number,
                'submitted_value'         => $application->tin_number,
                'matched'                 => $matched,
                'confidence_score'        => $matched ? 100.0 : 0.0,
                'notes'                   => 'Automated TIN match against BIR Form 2303.',
            ]);

            $tinMatch = $matched;
        }

        // ── Name similarity against each document ───────────
        $nameMatches = [];
        $checks = array_filter([
            $birDoc     ? ['doc' => $birDoc,     'field' => 'owner_full_name'] : null,
            $dtiDoc     ? ['doc' => $dtiDoc,     'field' => 'business_name']   : null,
            $ownerIdDoc ? ['doc' => $ownerIdDoc, 'field' => 'owner_full_name'] : null,
        ]);

        foreach ($checks as $entry) {
            /** @var BusinessDocument $doc */
            $doc = $entry['doc'];
            /** @var string $field */
            $field = $entry['field'];

            $extractedName = $doc->document_number ?? null;
            if (!$extractedName) {
                continue;
            }

            $submitted = $field === 'owner_full_name'
                ? $application->owner_full_name
                : $application->business_name;

            if (!$submitted) {
                continue;
            }

            $score   = $this->nameSimilarity($extractedName, $submitted);
            $matched = $score >= 85.0;

            BusinessDocumentVerification::create([
                'business_application_id' => $application->id,
                'verification_type'       => 'name_match',
                'source_field'            => $field,
                'reference_value'         => $extractedName,
                'submitted_value'         => $submitted,
                'matched'                 => $matched,
                'confidence_score'        => $score,
                'notes'                   => "Automated name match against {$doc->document_type}.",
            ]);

            $nameMatches[] = $matched;
        }

        return [
            'tin_match'        => $tinMatch,
            'name_matches'     => $nameMatches,
            'all_names_match'  => !empty($nameMatches) && !in_array(false, $nameMatches, true),
        ];
    }

    protected function compareTin(string $a, string $b): bool
    {
        $clean = fn ($v) => preg_replace('/[^0-9]/', '', $v);
        return $clean($a) === $clean($b);
    }

    protected function nameSimilarity(string $a, string $b): float
    {
        $a = Str::lower(trim($a));
        $b = Str::lower(trim($b));

        if ($a === $b) {
            return 100.0;
        }

        similar_text($a, $b, $percent);
        return round($percent, 2);
    }
}