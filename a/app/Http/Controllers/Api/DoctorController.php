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
}
