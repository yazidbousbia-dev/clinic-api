<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    // GET /api/appointments — scoped automatically by role
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Appointment::with(['patient', 'doctor.user']);

        if ($user->role === 'patient') {
            $query->whereHas('patient', fn ($q) => $q->where('user_id', $user->id));
        } elseif ($user->role === 'doctor') {
            $query->whereHas('doctor', fn ($q) => $q->where('user_id', $user->id));
        }
        // admin & secretary see everything

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        return response()->json($query->orderBy('date')->orderBy('time')->paginate(20));
    }

    // POST /api/appointments — book a new appointment
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required_unless:user_role,patient|exists:patients,id',
            'doctor_id' => 'required|exists:doctors,id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $patientId = $request->patient_id;

        // if a patient is booking for themselves, force their own patient_id
        if ($user->role === 'patient') {
            $patientId = $user->patient->id;
        }

        // double-booking check (DB unique constraint is the real guard, this gives a clean error)
        $exists = Appointment::where('doctor_id', $request->doctor_id)
            ->where('date', $request->date)
            ->where('time', $request->time)
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This time slot is already booked'], 409);
        }

        $appointment = Appointment::create([
            'patient_id' => $patientId,
            'doctor_id' => $request->doctor_id,
            'date' => $request->date,
            'time' => $request->time,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json($appointment->load('patient', 'doctor.user'), 201);
    }

    public function show(Request $request, Appointment $appointment)
    {
        $this->authorizeAccess($request, $appointment);

        return response()->json($appointment->load('patient', 'doctor.user', 'consultation'));
    }

    // PUT /api/appointments/{id}/status — confirm/cancel/complete
    public function updateStatus(Request $request, Appointment $appointment)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(['pending', 'confirmed', 'completed', 'cancelled'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $appointment->update(['status' => $request->status]);

        return response()->json($appointment);
    }

    public function destroy(Request $request, Appointment $appointment)
    {
        $this->authorizeAccess($request, $appointment);
        $appointment->delete();

        return response()->json(['message' => 'Appointment cancelled']);
    }

    private function authorizeAccess(Request $request, Appointment $appointment): void
    {
        $user = $request->user();

        if ($user->role === 'patient' && $appointment->patient->user_id !== $user->id) {
            abort(403, 'Not your appointment');
        }

        if ($user->role === 'doctor' && $appointment->doctor->user_id !== $user->id) {
            abort(403, 'Not your appointment');
        }
    }
}
