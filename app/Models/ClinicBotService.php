<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;

class ClinicBotService
{
    public const BOT_EMAIL = 'assistant@clinic.local';

    public static function botUser(): ?User
    {
        // FIX: cache le résultat pour ne pas faire 2 requêtes par message
        static $cached = false;
        if ($cached === false) {
            $cached = User::where('email', self::BOT_EMAIL)->first();
        }
        return $cached;
    }

    public static function reply(Appointment $appointment, string $incomingMessage): ?string
    {
        // FIX: charger la relation doctor si absente pour éviter null pointer sur consultation_fee
        $appointment->loadMissing('doctor');

        $text = mb_strtolower($incomingMessage);
        $fee  = (float) ($appointment->doctor?->consultation_fee ?? 0);

        $rules = [
            [
                'keywords' => ['horaire', "heure d'ouverture", 'ouvert', 'ferme', 'fermé'],
                'reply'    => "Nos horaires : Lundi–Samedi, 08h00–18h00. Fermé le dimanche.",
            ],
            [
                'keywords' => ['adresse', 'où êtes', 'ou etes', 'localisation', 'situé', 'situe'],
                'reply'    => "Vous trouverez notre adresse sur la page d'accueil de la clinique.",
            ],
            [
                'keywords' => ['tarif', 'prix', 'coût', 'cout', 'combien'],
                'reply'    => 'Le tarif de cette consultation est de ' . number_format($fee, 0, ',', ' ') . ' DA.',
            ],
            [
                'keywords' => ['annuler', 'annulation', 'reporter', 'déplacer', 'deplacer'],
                'reply'    => "Pour annuler ou reporter, utilisez le bouton dans « Mes rendez-vous ».",
            ],
            [
                'keywords' => ['retard', 'en retard'],
                'reply'    => "Pas d'inquiétude — prévenez-nous si vous avez plus de 15 minutes de retard.",
            ],
            [
                'keywords' => ['urgence', 'urgent'],
                'reply'    => "En cas d'urgence, contactez directement les services d'urgence (15 ou 21).",
            ],
            [
                'keywords' => ['bonjour', 'salut', 'bonsoir', 'salam'],
                'reply'    => "Bonjour ! Votre message a bien été transmis, le médecin vous répondra dès que possible.",
            ],
            [
                'keywords' => ['merci', 'شكرا'],
                'reply'    => "Avec plaisir, prenez soin de vous !",
            ],
        ];

        foreach ($rules as $rule) {
            foreach ($rule['keywords'] as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $rule['reply'];
                }
            }
        }

        return null; // pas de reply automatique → silence, le médecin répond
    }
}