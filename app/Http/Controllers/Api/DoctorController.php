<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DoctorController extends Controller
{
    // GET /api/doctors — public-ish: any authenticated user (patients need this to book)
    public function index(Request $request)
    {
        $query = Doctor::with('user:id,name,email,phone');

        if ($request->filled('speciality')) {
            $query->where('speciality', $request->speciality);
        }

        return response()->json($query->get());
    }

    public function show(Doctor $doctor)
    {
        return response()->json($doctor->load('user:id,name,email,phone'));
    }

    // PUT /api/doctors/{id} — doctor can update own schedule/fee, admin can update any
    public function update(Request $request, Doctor $doctor)
    {
        $user = $request->user();

        if ($user->role === 'doctor' && $doctor->user_id !== $user->id) {
            return response()->json(['message' => 'You can only edit your own profile'], 403);
        }

        $validator = Validator::make($request->all(), [
            'speciality' => 'sometimes|string',
            'consultation_fee' => 'sometimes|numeric',
            'schedule' => 'nullable|array',
            'bio' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $doctor->update($validator->validated());

        return response()->json($doctor);
    }

    // GET /api/doctors/{id}/availability?date=2026-07-01
    public function availability(Request $request, Doctor $doctor)
    {
        $date = $request->query('date', now()->toDateString());

        $bookedTimes = $doctor->appointments()
            ->whereDate('date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->pluck('time');

        return response()->json([
            'doctor_id' => $doctor->id,
            'date' => $date,
            'schedule' => $doctor->schedule,
            'booked_times' => $bookedTimes,
        ]);
    }

    // GET /api/doctors/{id}/stats — admin/secretary: full breakdown for one doctor
    public function stats(Request $request, Doctor $doctor)
    {
        $user = $request->user();

        if ($user->role === 'doctor' && $doctor->user_id !== $user->id) {
            return response()->json(['message' => 'You can only view your own statistics'], 403);
        }
        if ($user->role === 'patient') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $appointments = $doctor->appointments();

        $byStatus = (clone $appointments)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $totalPatients = (clone $appointments)->distinct('patient_id')->count('patient_id');

        $revenue = (float) \App\Models\Invoice::where('status', 'paid')
            ->whereHas('consultation.appointment', fn ($q) => $q->where('doctor_id', $doctor->id))
            ->sum('amount');

        $unpaidCount = \App\Models\Invoice::where('status', 'unpaid')
            ->whereHas('consultation.appointment', fn ($q) => $q->where('doctor_id', $doctor->id))
            ->count();

        return response()->json([
            'doctor' => $doctor->load('user:id,name,email,phone'),
            'total_patients' => $totalPatients,
            'total_appointments' => (clone $appointments)->count(),
            'appointments_by_status' => $byStatus,
            'pending_appointments' => $byStatus['pending'] ?? 0,
            'confirmed_appointments' => $byStatus['confirmed'] ?? 0,
            'completed_appointments' => $byStatus['completed'] ?? 0,
            'cancelled_appointments' => $byStatus['cancelled'] ?? 0,
            'today_appointments' => (clone $appointments)->whereDate('date', now()->toDateString())->count(),
            'revenue_total' => $revenue,
            'unpaid_invoices' => $unpaidCount,
            'recent_appointments' => (clone $appointments)
                ->with('patient:id,name,phone')
                ->latest('date')
                ->limit(8)
                ->get(['id', 'patient_id', 'date', 'time', 'status']),
        ]);
    }
}