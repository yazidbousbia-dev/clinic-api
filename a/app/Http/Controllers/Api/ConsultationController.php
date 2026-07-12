<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    public function index()
    {
        $consultations = Consultation::with([
            'appointment.patient',
            'appointment.doctor.user'
        ])->latest()->get();

        return response()->json($consultations);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'appointment_id'  => 'required|exists:appointments,id',
            'diagnosis'       => 'nullable|string',
            'prescription'    => 'nullable|string',
            'doctor_notes'    => 'nullable|string',
            'next_visit_date' => 'nullable|date',
        ]);

        $consultation = Consultation::create($data);
        return response()->json($consultation->load('appointment.patient'), 201);
    }

    public function show($id)
    {
        $consultation = Consultation::with([
            'appointment.patient',
            'appointment.doctor.user'
        ])->findOrFail($id);

        return response()->json($consultation);
    }

    public function update(Request $request, $id)
    {
        $consultation = Consultation::findOrFail($id);

        $data = $request->validate([
            'diagnosis'       => 'nullable|string',
            'prescription'    => 'nullable|string',
            'doctor_notes'    => 'nullable|string',
            'next_visit_date' => 'nullable|date',
        ]);

        $consultation->update($data);
        return response()->json($consultation);
    }

    public function destroy($id)
    {
        Consultation::findOrFail($id)->delete();
        return response()->json(['message' => 'Consultation deleted']);
    }
}
