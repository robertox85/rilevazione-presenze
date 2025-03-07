<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;

class UserService
{

    /**
     * Recupera l'utente con la sua location dalla richiesta
     * @throws \Exception
     */
    public function getUserWithLocation(Request $request): User
    {
        $userId = $request->user()->id;
        $user = User::with('location')->find($userId);

        if (!$user || !$user->location) {
            throw new \Exception('User not found or missing location.');
        }

        return $user;
    }

}