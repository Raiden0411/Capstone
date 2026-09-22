<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Services\AccountDeletionService;
use App\Services\SuperadminNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DeletionRequestController extends Controller
{
    public function __construct(
        protected AccountDeletionService $deletionService,
        protected SuperadminNotificationService $notifications,
    ) {}

    public function approve(Request $request, AccountDeletionRequest $deletionRequest): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        if (! $deletionRequest->isPending()) {
            return back()->with('error', 'This request has already been ' . $deletionRequest->status . '.');
        }

        try {
            DB::transaction(function () use ($request, $deletionRequest): void {
                /** @var AccountDeletionRequest|null $locked */
                $locked = AccountDeletionRequest::query()
                    ->whereKey($deletionRequest->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $locked || ! $locked->isPending()) {
                    throw new RuntimeException('Request is no longer pending.');
                }

                $locked->update([
                    'status'       => AccountDeletionRequest::STATUS_APPROVED,
                    'reviewed_by'  => Auth::id(),
                    'reviewed_at'  => now(),
                    'review_notes' => $request->input('review_notes') ?: null,
                ]);

                $target = $locked->user;
                if (! $target) {
                    return;
                }

                if ($locked->isBoth()) {
                    $this->deletionService->deleteBusinessAndUser($target, force: true);
                } else {
                    $this->deletionService->deleteBusinessOnly($target, force: true);
                }
            });

            // Refresh BOTH superadmin badges (KYB + deletion-requests).
            $this->notifications->flush();

            return redirect()
                ->route('superadmin.deletion-requests.index')
                ->with('message', 'Request approved and account deleted.');
        } catch (Throwable $e) {
            Log::error('Deletion request approval failed', [
                'request_id' => $deletionRequest->id,
                'error'      => $e->getMessage(),
                'class'      => get_class($e),
            ]);

            return back()->with('error', 'Deletion failed: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, AccountDeletionRequest $deletionRequest): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'review_notes.required' => 'Please explain why this request is being rejected.',
            'review_notes.min'      => 'Please provide a bit more detail (at least 5 characters).',
        ]);

        if (! $deletionRequest->isPending()) {
            return back()->with('error', 'This request has already been ' . $deletionRequest->status . '.');
        }

        try {
            DB::transaction(function () use ($deletionRequest, $validated): void {
                /** @var AccountDeletionRequest|null $locked */
                $locked = AccountDeletionRequest::query()
                    ->whereKey($deletionRequest->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $locked || ! $locked->isPending()) {
                    throw new RuntimeException('Request is no longer pending.');
                }

                $locked->update([
                    'status'       => AccountDeletionRequest::STATUS_REJECTED,
                    'reviewed_by'  => Auth::id(),
                    'reviewed_at'  => now(),
                    'review_notes' => $validated['review_notes'],
                ]);
            });

            // Refresh BOTH superadmin badges (KYB + deletion-requests).
            $this->notifications->flush();

            return redirect()
                ->route('superadmin.deletion-requests.index')
                ->with('message', 'Request rejected.');
        } catch (Throwable $e) {
            Log::error('Deletion request rejection failed', [
                'request_id' => $deletionRequest->id,
                'error'      => $e->getMessage(),
            ]);

            return back()->with('error', 'Could not reject the request: ' . $e->getMessage());
        }
    }
}