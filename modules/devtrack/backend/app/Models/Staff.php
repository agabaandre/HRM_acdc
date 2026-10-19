<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = [
        'staff_id',
        'work_email',
        'signature',
        'sap_no',
        'title',
        'fname',
        'lname',
        'oname',
        'grade',
        'gender',
        'date_of_birth',
        'job_name',
        'contracting_institution',
        'contract_type',
        'nationality',
        'division_name',
        'division_id',
        'associated_divisions',
        'directorate_id',
        'duty_station_id',
        'duty_station_name',
        'supervisor_id',
        'status',
        'active',
        'tel_1',
        'whatsapp',
        'private_email',
        'photo',
        'physical_location',
    ];

    protected function casts(): array
    {
        return [
            'staff_id' => 'integer',
            'division_id' => 'integer',
            'directorate_id' => 'integer',
            'duty_station_id' => 'integer',
            'supervisor_id' => 'integer',
            'associated_divisions' => 'array',
            'active' => 'boolean',
            'date_of_birth' => 'date',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'staff_id', 'staff_id');
    }

    public function fullName(): string
    {
        $name = trim(implode(' ', array_filter([
            (string) $this->fname,
            (string) $this->oname,
            (string) $this->lname,
        ])));

        return $name !== '' ? $name : (string) ($this->work_email ?? 'Staff '.$this->staff_id);
    }

    public function phone(): ?string
    {
        return $this->tel_1 ?: $this->whatsapp;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'staff_id' => $this->staff_id,
            'fname' => $this->fname,
            'lname' => $this->lname,
            'oname' => $this->oname,
            'name' => $this->fullName(),
            'work_email' => $this->work_email,
            'phone' => $this->phone(),
            'tel_1' => $this->tel_1,
            'title' => $this->title,
            'job_name' => $this->job_name,
            'division_id' => $this->division_id,
            'division_name' => $this->division_name,
            'directorate_id' => $this->directorate_id,
            'duty_station_name' => $this->duty_station_name,
            'associated_divisions' => $this->associated_divisions,
            'status' => $this->status,
            'active' => (bool) $this->active,
            'is_active' => (bool) $this->active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
