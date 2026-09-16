<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NfcCard extends Model
{
    /** A student or employee may have at most this many active cards. */
    public const MAX_ACTIVE_CARDS_PER_HOLDER = 3;

    protected $fillable = [
        'uid',
        'student_id',
        'employee_id',
        'status',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'card_id');
    }

    /**
     * Whichever holder is assigned — a card belongs to exactly one of
     * student/employee, never both.
     */
    public function holder(): Student|Employee|null
    {
        return $this->student ?? $this->employee;
    }

    /**
     * Counts a holder's currently-active cards, optionally excluding one
     * card (itself, when editing) so it doesn't count against its own limit.
     */
    public static function activeCountFor(Student|Employee $holder, ?int $excludingCardId = null): int
    {
        $column = $holder instanceof Student ? 'student_id' : 'employee_id';

        return static::query()
            ->where($column, $holder->id)
            ->where('status', 'active')
            ->when($excludingCardId, fn ($query, $id) => $query->whereKeyNot($id))
            ->count();
    }
}
