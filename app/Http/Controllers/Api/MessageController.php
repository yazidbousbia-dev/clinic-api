<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\ClinicBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    // GET /api/messages/conversations
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
            // FIX: cherche le patient lié à ce user_id
            $query->whereHas('patient', fn ($q) => $q->where('user_id', $user->id));
        }

        $appointments = $query->get();

        $conversations = $appointments->map(function ($a) {
            $last = $a->messages->first();
            return [
                'appointment_id'    => $a->id,
                'date'              => $a->date,
                'time'              => $a->time,
                'status'            => $a->status,
                'patient_name'      => $a->patient->name ?? '—',
                'doctor_name'       => $a->doctor->user->name ?? '—',
                'doctor_speciality' => $a->doctor->speciality ?? '',
                'last_message'      => $last->body ?? null,
                'last_message_at'   => $last->created_at ?? $a->created_at,
                'unread_count'      => $a->unread_count,
            ];
        })->sortByDesc('last_message_at')->values();

        return response()->json($conversations);
    }

    // GET /api/appointments/{appointment}/messages
    public function index(Request $request, Appointment $appointment)
    {
        $this->authorizeAccess($request, $appointment);

        $messages = $appointment->messages()->with('sender:id,name,role,email')->get();

        $appointment->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json($messages);
    }

    // POST /api/appointments/{appointment}/messages
    public function store(Request $request, Appointment $appointment)
    {
        // FIX 1 — Autorisation avant tout
        $this->authorizeAccess($request, $appointment);

        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sender = $request->user();

        // FIX 2 — Créer le message du patient en premier, retourner 201 immédiatement
        $message = $appointment->messages()->create([
            'sender_id' => $sender->id,
            'body'      => $request->body,
        ]);

        // FIX 3 — Bot reply: seulement si patient ET botUser existe
        // On ne bloque JAMAIS la réponse 201 à cause du bot
        if ($sender->role === 'patient') {
            try {
                $botUser  = ClinicBotService::botUser();
                $botReply = ClinicBotService::reply($appointment, $request->body);

                if ($botReply && $botUser) {
                    $appointment->messages()->create([
                        'sender_id' => $botUser->id,
                        'body'      => $botReply,
                    ]);
                }
            } catch (\Throwable $e) {
                // FIX 4 — Si le bot échoue, on log l'erreur mais on ne bloque pas le patient
                \Log::warning('ClinicBot reply failed: ' . $e->getMessage());
            }
        }

        return response()->json($message->load('sender:id,name,role,email'), 201);
    }

    /**
     * FIX 5 — authorizeAccess corrigé:
     *
     * Avant: $appointment->patient->user_id !== $user->id
     *   → Si patient n'a pas de user_id (ajouté manuellement), null !== id → 403
     *   → Si la relation patient n'est pas chargée → null pointer
     *
     * Après: on charge la relation proprement et on vérifie avec optional()
     */
    private function authorizeAccess(Request $request, Appointment $appointment): void
    {
        $user = $request->user();

        // Charger les relations si elles ne le sont pas
        $appointment->loadMissing(['patient', 'doctor']);

        if ($user->role === 'patient') {
            $patient = $appointment->patient;

            // FIX: si patient n'a pas de user_id → il n'a pas de compte → 403
            if (!$patient || $patient->user_id === null || $patient->user_id !== $user->id) {
                abort(403, 'Accès refusé — ce rendez-vous ne vous appartient pas');
            }
        }

        if ($user->role === 'doctor') {
            $doctor = $appointment->doctor;

            if (!$doctor || $doctor->user_id !== $user->id) {
                abort(403, 'Accès refusé — ce rendez-vous ne vous appartient pas');
            }
        }

        // admin et secretary ont accès à tout
        if (!in_array($user->role, ['patient', 'doctor', 'admin', 'secretary'])) {
            abort(403, 'Rôle non autorisé');
        }
    }
}