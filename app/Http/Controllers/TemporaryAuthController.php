<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\User;
use Filament\Facades\Filament;
use Hashids\Hashids;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class TemporaryAuthController extends Controller
{

    private function encodeUserId($id): string
    {
        $hashids = new Hashids(config('app.key'), 10); // 10 è la lunghezza minima
        return $hashids->encode($id);
    }

    private function decodeUserId($hash): ?int
    {
        $hashids = new Hashids(config('app.key'), 10);
        $decoded = $hashids->decode($hash);
        return $decoded[0] ?? null;
    }

    public function generateLoginLink(User $user, string $callback): string
    {
        $hashedId = $this->encodeUserId($user->id);

        return URL::temporarySignedRoute(
            'temp.login',
            now()->addMinutes(30),
            [
                'u' => $hashedId, // u invece di user
                'callback' => base64_encode($callback)
            ]
        );
    }

    public function handleLogin(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        try {

            // Decodifica l'ID utente dall'hash nell'URL firmato
            $hashids = new Hashids(config('app.key'), 10);
            $decoded = $hashids->decode($request->u);
            $userId = $decoded[0] ?? null;

            if (!$userId) {
                return response()->json([
                    'message' => 'Invalid user ID',
                    'status' => 'error'
                ], 400);
            }

            $user = User::findOrFail($userId);

            // Autentica l'utente
            Auth::loginUsingId($user->id);
            session()->regenerate();

            // Controlla se il dispositivo è già registrato
            $device = Device::where('user_id', $user->id)
                ->where('device_uuid', $request->device_uuid)
                ->first();

            if ($device) {
                return redirect()->route('device.registration.complete')->with([
                    'message' => 'Device already registered.',
                    'status' => 'info',
                ]);
            }

            // Registra il nuovo dispositivo
            Device::create([
                'device_name' => 'Default Device Name', // Puoi modificare questo valore in base alla richiesta
                'device_uuid' => $request->device_uuid,
                'user_id' => $user->id,
            ]);



            return response()->json([
                'message' => 'Device registered successfully.',
                'status' => 'success',
                'user' => $user,
                'device' => $device
            ]);


        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function handleCallback(Request $request)
    {
        $callback = base64_decode($request->callback);
        Auth::logout();
        return redirect($callback);
    }

}