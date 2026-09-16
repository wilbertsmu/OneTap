<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    protected $fillable = [
        'uid_scanned',
        'card_id',
        'student_id',
        'employee_id',
        'device_id',
        'scanned_at',
        'result',
        'source',
        'encoded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(NfcCard::class, 'card_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function encodedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by_id');
    }

    /**
     * Whichever holder this log is for — a log belongs to exactly one of
     * student/employee, never both (or neither, for an unregistered card).
     */
    public function holder(): Student|Employee|null
    {
        return $this->student ?? $this->employee;
    }
}
