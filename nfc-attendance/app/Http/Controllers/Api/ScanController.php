<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScanRequest;
use App\Http\Resources\StudentResource;
use App\Models\Device;
use App\Services\AttendanceLogger;
use Illuminate\Http\JsonResponse;

class ScanController extends Controller
{
    public function store(StoreScanRequest $request, AttendanceLogger $logger): JsonResponse
    {
        $device = $request->user();

        if (! $device instanceof Device || ! $device->is_active) {
            return response()->json([
                'message' => 'This device is not registered or has been deactivated. Contact an administrator.',
            ], 403);
        }

        $device->forceFill(['last_used_at' => now()])->save();

        $uid = $request->string('uid')->trim()->toString();
        $log = $logger->logCardScan($uid, $device, $request->input('scanned_at'));

        // Always 200: a completed, logged scan is not an HTTP error condition,
        // even when the business result is a failure (e.g. unregistered card).
        // The `result` field carries the outcome for the client to act on.
        $holder = $log->holder();

        return response()->json([
            'result' => $log->result,
            'message' => $this->messageFor($log->result),
            'student' => $holder ? new StudentResource($holder) : null,
            'logged_at' => $log->scanned_at->toIso8601String(),
        ]);
    }

    private function messageFor(string $result): string
    {
        return match ($result) {
            'success' => 'Scan successful.',
            'unregistered_card' => 'This card is not registered.',
            'inactive_card' => 'This card has been reported lost or revoked.',
            'inactive_student' => 'This student is not active.',
            'inactive_employee' => 'This employee is not active.',
            default => 'Unknown result.',
        };
    }
}
