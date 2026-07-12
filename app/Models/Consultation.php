<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consultation extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id', 'diagnosis', 'prescription', 'doctor_notes', 'next_visit_date',
    ];

    protected $casts = [
        'next_visit_date' => 'date',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
