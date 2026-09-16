<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats either a Student or an Employee. `course`/`year_level` come back
 * null for an Employee — Eloquent returns null for a non-existent attribute
 * rather than throwing, so no type check is needed here.
 */
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_number' => $this->id_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => "{$this->first_name} {$this->last_name}",
            'course' => $this->course,
            'year_level' => $this->year_level,
            'department' => $this->department,
            'status' => $this->status,
        ];
    }
}
