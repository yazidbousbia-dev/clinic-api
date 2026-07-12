<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;

class DashboardController extends Controller
{
    public function stats()
    {
        $today = now()->toDateString();

        return response()->json([
            'total_patients'        => Patient::count(),
            'total_doctors'         => User::where('role', 'doctor')->count(),

            'today_appointments'    => Appointment::today()->count(),
            'upcoming_appointments' => Appointment::where('date', '>', $today)
                ->whereIn('status', ['pending', 'confirmed'])
                ->count(),
            'pending_appointments'  => Appointment::where('status', 'pending')->count(),
            'total_appointments'    => Appointment::count(),

            'unpaid_invoices'       => Invoice::where('status', 'unpaid')->count(),
            'revenue_this_month'    => (float) Invoice::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),

            'appointments_by_status' => Appointment::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
        ]);
    }
}