<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\ClinicBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    // GET /api/messages/conversations — inbox: one row per appointment that has (or can have) a chat
    public function conversations(Request $request)
    {
        $user = $request->user();

        $query = Appointment::with(['patient', 'doctor.user'])
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->whereNull('read_at')->where('sender_id', '!=', $user->id);
            }])
            ->with(['messages' => function ($q) {
                $q->latest()->limit(1);
            }]);

        if ($user->role === 'doctor') {
            $query->whereHas('doctor', fn ($q) => $q->where('user_id', $user->id));
        } elseif ($user->role === 'patient') {
            $query->whereHas('patient', fn ($q) => $q->where('user_id', $user->id));
        }
        // admin & secretary see every conversation

        $appointments = $query->get();

        $conversations = $appointments->map(function ($a) {
            $last = $a->messages->first();

            return [
                'appointment_id'   => $a->id,
                'date'             => $a->date,
                'time'             => $a->time,
                'status'           => $a->status,
                'patient_name'     => $a->patient->name ?? '—',
                'doctor_name'      => $a->doctor->user->name ?? '—',
                'doctor_speciality'=> $a->doctor->speciality ?? '',
                'last_message'     => $last->body ?? null,
                'last_message_at'  => $last->created_at ?? $a->created_at,
                'unread_count'     => $a->unread_count,
            ];
        })->sortByDesc('last_message_at')->values();

        return response()->json($conversations);
    }

    // GET /api/appointments/{appointment}/messages
    public function index(Request $request, Appointment $appointment)
    {
        $this->authorizeAccess($request, $appointment);

        $messages = $appointment->messages()->with('sender:id,name,role,email')->get();

        // mark messages sent by the other party as read
        $appointment->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json($messages);
    }

    // POST /api/appointments/{appointment}/messages
    public function store(Request $request, Appointment $appointment)
    {
        $this->authorizeAccess($request, $appointment);

        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sender = $request->user();

        $message = $appointment->messages()->create([
            'sender_id' => $sender->id,
            'body' => $request->body,
        ]);

        // Simple rule-based auto-reply — only triggers for patient messages, and
        // only when a keyword matches; otherwise the bot stays silent for the doctor.
        if ($sender->role === 'patient') {
            $botReply = ClinicBotService::reply($appointment, $request->body);
            $botUser = ClinicBotService::botUser();

            if ($botReply && $botUser) {
                $appointment->messages()->create([
                    'sender_id' => $botUser->id,
                    'body' => $botReply,
                ]);
            }
        }

        return response()->json($message->load('sender:id,name,role,email'), 201);
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

        if (!in_array($user->role, ['patient', 'doctor', 'admin', 'secretary'])) {
            abort(403);
        }
    }
}