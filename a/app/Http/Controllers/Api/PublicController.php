<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;

class PublicController extends Controller
{
    /**
     * GET /api/public/doctors
     * Public list of doctors for the landing page (no auth required).
     */
    public function doctors()
    {
        $doctors = Doctor::with('user:id,name,avatar')->get()->map(function ($d) {
            return [
                'id' => $d->id,
                'name' => $d->user->name,
                'speciality' => $d->speciality,
                'consultation_fee' => $d->consultation_fee,
                'bio' => $d->bio,
                'avatar' => $d->user->avatar,
            ];
        });

        return response()->json($doctors);
    }

    /**
     * GET /api/public/specialities
     * Distinct specialities + how many doctors cover each one.
     */
    public function specialities()
    {
        $data = Doctor::selectRaw('speciality, count(*) as doctors_count')
            ->groupBy('speciality')
            ->orderByDesc('doctors_count')
            ->get();

        return response()->json($data);
    }

    /**
     * GET /api/public/stats
     * Real counters shown in the hero / stats banner of the landing page.
     */
    public function stats()
    {
        return response()->json([
            'total_patients'     => Patient::count(),
            'total_doctors'      => Doctor::count(),
            'total_specialities' => Doctor::distinct('speciality')->count('speciality'),
            'total_appointments' => Appointment::count(),
            'completed_appointments' => Appointment::where('status', 'completed')->count(),
        ]);
    }
}
