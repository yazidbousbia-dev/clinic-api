<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;

class PublicController extends Controller
{
    // GET /api/public/doctors
    public function doctors()
    {
        $doctors = Doctor::with('user:id,name,avatar')->get()->map(function ($d) {
            return [
                'id'               => $d->id,
                'name'             => $d->user->name,
                'speciality'       => $d->speciality,
                'consultation_fee' => $d->consultation_fee,
                'bio'              => $d->bio,
                'avatar'           => $d->user->avatar,
            ];
        });

        return response()->json($doctors);
    }

    // GET /api/public/specialities
    public function specialities()
    {
        $data = Doctor::selectRaw('speciality, count(*) as doctors_count')
            ->groupBy('speciality')
            ->orderByDesc('doctors_count')
            ->get();

        return response()->json($data);
    }

    // GET /api/public/stats
    public function stats()
    {
        return response()->json([
            'total_patients'         => Patient::count(),
            'total_doctors'          => Doctor::count(),
            'total_specialities'     => Doctor::distinct('speciality')->count('speciality'),
            'total_appointments'     => Appointment::count(),
            'completed_appointments' => Appointment::where('status', 'completed')->count(),
        ]);
    }

    /**
     * GET /api/public/today-appointments
     * Rendez-vous du jour — données anonymisées pour la landing page
     * (aucune info patient, juste médecin + heure + statut)
     */
    public function todayAppointments()
    {
        $appointments = Appointment::with('doctor.user:id,name')
            ->whereDate('date', today())
            ->whereIn('status', ['pending', 'confirmed', 'completed'])
            ->orderBy('time')
            ->limit(5)
            ->get()
            ->map(function ($a) {
                return [
                    'time'       => substr($a->time, 0, 5),   // "09:00"
                    'status'     => $a->status,
                    'doctor'     => $a->doctor->user->name ?? '—',
                    'speciality' => $a->doctor->speciality   ?? '—',
                ];
            });

        return response()->json($appointments);
    }
}