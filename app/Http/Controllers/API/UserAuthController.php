<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Log;
class UserAuthController extends Controller
{

    public function register(Request $request): JsonResponse
    {

        try {
            $request->validate([
                'name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string',
            ]);

            $data = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ];

            $user = User::create($data);
            $user->assignRole('Employee');

            return response()->json(
                [
                    'message' => 'User created successfully.',
                    'user_id' => $user->id,
                ], 201
            );

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Registration failed.' . $e->getMessage()], 500);
        }

    }

    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
                // optional device uuid
                'device_uuid' => [
                    'nullable',
                    'regex:/^[0-9a-fA-F]{16}$/'
                ],
                'device_name' => 'nullable|string|max:255',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'User not found.'
                ], 401);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Invalid Credentials.'
                ], 401);
            }

            $device = $user->getDevice();

            if (!$user->active) {
                return response()->json([
                    'message' => 'User is not active.'
                ], 403);
            }

            $token = $user->createToken('my-app-token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful.',
                'token' => $token,
                'user' => $user,
                'device' => $device,
                'location' => $user->location,
            ], 200);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Login failed.' . $e->getMessage()], 500);
        }
    }


    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $location = $user->location;
        $timezone = $location->timezone ?? 'UTC';
        $nowInLocationTimezone = Carbon::now($timezone)->startOfMinute();

        return response()->json([
            'user' => $user,
            'now' => $nowInLocationTimezone->toDateTimeString(),
        ], 200);
    }

    public function logout(Request $request): JsonResponse

    {

        // Revoke all tokens...

        $request->user()->tokens()->delete();

        // // Revoke the current token

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'You have been successfully logged out.'], 200);

    }






}
