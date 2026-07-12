<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PatientController extends Controller
{
    // GET /api/patients — admin, secretary see everyone; doctor sees only their own patients
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Patient::query();

        if ($user->role === 'doctor') {
            $query->whereHas('appointments.doctor', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate(15));
    }

    // GET /api/patients/{id}
    public function show(Request $request, Patient $patient)
    {
        $this->authorizeAccess($request, $patient);

        return response()->json($patient->load('appointments.doctor.user'));
    }

    // POST /api/patients — admin/secretary create a walk-in patient record
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'blood_type' => 'nullable|string|max:5',
            'address' => 'nullable|string',
            'allergies' => 'nullable|string',
            'chronic_conditions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $patient = Patient::create($validator->validated());

        return response()->json($patient, 201);
    }

    // PUT /api/patients/{id}
    public function update(Request $request, Patient $patient)
    {
        $this->authorizeAccess($request, $patient);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => 'nullable|email',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'blood_type' => 'nullable|string|max:5',
            'address' => 'nullable|string',
            'allergies' => 'nullable|string',
            'chronic_conditions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $patient->update($validator->validated());

        return response()->json($patient);
    }

    // DELETE /api/patients/{id} — admin only (enforced in routes)
    public function destroy(Patient $patient)
    {
        $patient->delete();

        return response()->json(['message' => 'Patient deleted']);
    }

    /**
     * Patients can only view/edit their own record.
     * Staff (admin/doctor/secretary) can access any record.
     */
    private function authorizeAccess(Request $request, Patient $patient): void
    {
        $user = $request->user();

        if ($user->role === 'patient' && $patient->user_id !== $user->id) {
            abort(403, 'You can only access your own record');
        }
    }
}