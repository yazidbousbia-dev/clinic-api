<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Appointment;

class DashboardController extends Controller
{
    public function stats()
    {
        return response()->json([
            'total_doctors'      => User::where('role', 'doctor')->count(),
            'total_patients'     => User::where('role', 'patient')->count(),
            'total_appointments' => Appointment::count(),
            'pending_appointments' => Appointment::where('status', 'pending')->count(),
        ]);
    }
}