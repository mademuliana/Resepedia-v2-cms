<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $creds = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
            'device_name' => ['nullable','string'],
        ]);

        if (! auth()->attempt(['email'=>$creds['email'], 'password'=>$creds['password']])) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        $user = $request->user();
        $token = $user->createToken($creds['device_name'] ?? 'API')->plainTextToken;

        return response()->json([
            'token'   => $token,
            'user'    => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role ?? 'admin',
                'company_id' => $user->company_id,
            ],
        ]);
    }

    public function issue(Request $request)
    {
        $request->validate(['name' => ['nullable','string']]);
        $token = $request->user()->createToken($request->input('name','API'))->plainTextToken;
        return response()->json(['token' => $token]);
    }

    public function revoke(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['ok' => true]);
    }
}
