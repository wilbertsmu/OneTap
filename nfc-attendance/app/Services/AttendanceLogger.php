<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Employee;
use App\Models\NfcCard;
use App\Models\Student;
use App\Models\User;

class AttendanceLogger
{
    public function logCardScan(string $uid, ?Device $device, ?string $scannedAt = null): AttendanceLog
    {
        $card = NfcCard::with(['student', 'employee'])->where('uid', $uid)->first();
        [$result, $holder] = $this->evaluateCard($card);

        return AttendanceLog::create([
            'uid_scanned' => $uid,
            'card_id' => $card?->id,
            'student_id' => $holder instanceof Student ? $holder->id : null,
            'employee_id' => $holder instanceof Employee ? $holder->id : null,
            'device_id' => $device?->device_key,
            'scanned_at' => $scannedAt ?? now(),
            'result' => $result,
            'source' => 'scan',
        ]);
    }

    /**
     * @return array{0: ?AttendanceLog, 1: ?string} [log, errorMessage]
     */
    public function logManualEntry(string $idNumber, User $encodedBy): array
    {
        $idNumber = trim($idNumber);
        $holder = Student::where('id_number', $idNumber)->first()
            ?? Employee::where('id_number', $idNumber)->first();

        if (! $holder) {
            return [null, "No student or employee found with ID number \"{$idNumber}\"."];
        }

        $result = $holder->status === 'active'
            ? 'success'
            : ($holder instanceof Employee ? 'inactive_employee' : 'inactive_student');

        $log = AttendanceLog::create([
            'uid_scanned' => null,
            'card_id' => null,
            'student_id' => $holder instanceof Student ? $holder->id : null,
            'employee_id' => $holder instanceof Employee ? $holder->id : null,
            'device_id' => null,
            'scanned_at' => now(),
            'result' => $result,
            'source' => 'manual',
            'encoded_by_id' => $encodedBy->id,
        ]);

        return [$log, null];
    }

    /**
     * @return array{0: string, 1: Student|Employee|null}
     */
    private function evaluateCard(?NfcCard $card): array
    {
        if (! $card) {
            return ['unregistered_card', null];
        }

        if ($card->status !== 'active') {
            return ['inactive_card', $card->holder()];
        }

        $holder = $card->holder();

        if (! $holder) {
            return ['unregistered_card', null];
        }

        if ($holder->status !== 'active') {
            return [$holder instanceof Employee ? 'inactive_employee' : 'inactive_student', $holder];
        }

        return ['success', $holder];
    }
}
