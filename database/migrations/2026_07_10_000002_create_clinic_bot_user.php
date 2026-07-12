<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        // Reuses the existing 'secretary' role so no enum change is needed —
        // this account is only ever used as the sender of automated chat replies.
        User::firstOrCreate(
            ['email' => 'assistant@clinic.local'],
            [
                'name' => 'Assistant Clinique',
                'password' => Hash::make(bin2hex(random_bytes(16))),
                'role' => 'secretary',
            ]
        );
    }

    public function down(): void
    {
        User::where('email', 'assistant@clinic.local')->delete();
    }
};
