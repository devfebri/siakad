<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;

/**
 * Klien OAuth Passport milik E-Learning sekolah. Aplikasi E-Learning adalah pihak tepercaya milik sekolah itu sendiri,
 * jadi layar "izinkan aplikasi?" dilewati: guru/siswa yang sudah masuk langsung diteruskan ke E-Learning.
 */
class KlienOauth extends Client
{
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return true;
    }
}
