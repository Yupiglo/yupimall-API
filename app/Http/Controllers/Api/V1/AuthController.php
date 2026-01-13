<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function signin(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = $data['username'];

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'status' => 401,
                'responseMsg' => 'Invalid username/email or password',
            ], 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'status' => 200,
            'responseMsg' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'username' => $user->name, // matches Node behavior that returns name as username
                'email' => $user->email,
                'token' => $token,
                'role' => $user->role ?? 'consumer',
                'country' => $user->country,
                'supervisor_id' => $user->supervisor_id,
            ],
        ], 200);
    }

    // Kept for backward compatibility with the Node API naming.
    public function loginWithYupi(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = $data['username'];

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid username or password',
            ], 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'token' => $token,
                'Name' => $user->name,
                'Email' => $user->email,
                'SerialNo' => (string) $user->id,
                'Currency' => 'USD',
                'Role' => $user->role ?? 'consumer',
                'Country' => $user->country,
                'SupervisorId' => $user->supervisor_id,
            ],
        ], 200);
    }

    public function validateSession(Request $request)
    {
        // Node endpoint expects email in body, but effectively validates token.
        $data = $request->validate([
            'email' => ['required', 'string'],
        ]);

        if (trim($data['email']) === '') {
            return response()->json([
                'status' => 401,
                'responseMsg' => 'All Parameters are required!',
            ], 401);
        }

        $user = $request->user();

        return response()->json([
            'status' => 200,
            'responseMsg' => 'Token is valid',
            'userId' => $user?->id,
        ], 200);
    }
}
