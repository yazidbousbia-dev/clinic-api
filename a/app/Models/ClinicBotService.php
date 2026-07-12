<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;

class ClinicBotService
{
    public const BOT_EMAIL = 'assistant@clinic.local';

    /**
     * The system user that sends automated replies. Created by a migration
     * (2026_07_10_000002_create_clinic_bot_user.php) — reuses the existing
     * 'secretary' role so no database schema change was needed.
     */
    public static function botUser(): ?User
    {
        return User::where('email', self::BOT_EMAIL)->first();
    }

    /**
     * Very simple keyword matching — no AI, no external API calls.
     * Returns a canned French reply for common questions, or null if nothing matches
     * (in which case the bot stays silent and waits for the doctor to answer).
     */
    public static function reply(Appointment $appointment, string $incomingMessage): ?string
    {
        $text = mb_strtolower($incomingMessage);

        $fee = (float) ($appointment->doctor->consultation_fee ?? 0);

        $rules = [
            [
                'keywords' => ['horaire', "heure d'ouverture", 'ouvert', 'ferme', 'fermé'],
                'reply' => "Nos horaires : Lundi–Samedi, 08h00–18h00. Fermé le dimanche.",
            ],
            [
                'keywords' => ['adresse', 'où êtes', 'ou etes', 'localisation', 'situé', 'situe'],
                'reply' => "Vous trouverez notre adresse et l'itinéraire sur la page d'accueil de la clinique.",
            ],
            [
                'keywords' => ['tarif', 'prix', 'coût', 'cout', 'combien ça coûte', 'combien coute'],
                'reply' => 'Le tarif de cette consultation est de ' . number_format($fee, 0, ',', ' ') . ' DA.',
            ],
            [
                'keywords' => ['annuler', 'annulation', 'reporter', 'déplacer', 'deplacer'],
                'reply' => "Pour annuler ou reporter, utilisez le bouton dans « Mes rendez-vous », ou contactez le secrétariat.",
            ],
            [
                'keywords' => ['retard', 'en retard'],
                'reply' => "Pas d'inquiétude — prévenez-nous simplement si vous avez plus de 15 minutes de retard.",
            ],
            [
                'keywords' => ['urgence', 'urgent'],
                'reply' => "En cas d'urgence médicale, contactez directement les services d'urgence — ce chat n'est pas surveillé en continu.",
            ],
            [
                'keywords' => ['bonjour', 'salut', 'bonsoir'],
                'reply' => 'Bonjour ! Votre message a bien été transmis, le médecin vous répondra dès que possible.',
            ],
            [
                'keywords' => ['merci'],
                'reply' => 'Avec plaisir, prenez soin de vous !',
            ],
        ];

        foreach ($rules as $rule) {
            foreach ($rule['keywords'] as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $rule['reply'];
                }
            }
        }

        return null;
    }
}
