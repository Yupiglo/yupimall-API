<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;

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
                'image_url' => $user->image_url,
                'avatar_url' => $user->avatar_url,
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

    /**
     * Register a new user from an existing guest order
     * Converts a guest order to a user account
     */
    public function registerFromOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tracking_code' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 400);
        }

        // Find the order by tracking code
        $order = Order::where('tracking_code', $request->input('tracking_code'))->first();

        if (!$order) {
            return response()->json([
                'message' => 'Commande introuvable avec ce code de suivi',
            ], 404);
        }

        // Check if order is already linked to a user
        if ($order->user_id) {
            return response()->json([
                'message' => 'Cette commande est déjà associée à un compte',
            ], 400);
        }

        // Check if email matches order email
        if ($order->shipping_email !== $request->input('email')) {
            return response()->json([
                'message' => 'L\'email ne correspond pas à celui de la commande',
            ], 400);
        }

        // Check if user already exists with this email
        $existingUser = User::where('email', $request->input('email'))->first();
        if ($existingUser) {
            return response()->json([
                'message' => 'Un compte existe déjà avec cet email. Veuillez vous connecter.',
            ], 400);
        }

        // Create the user
        $user = User::create([
            'name' => $request->input('name') ?? $order->shipping_name,
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => User::ROLE_CONSUMER,
            'phone' => $order->shipping_phone,
            'address' => $order->shipping_street,
            'city' => $order->shipping_city,
        ]);

        // Link the order to the new user
        $order->update(['user_id' => $user->id]);

        // Link other orders with same email
        Order::where('shipping_email', $request->input('email'))
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        // Create token
        $token = $user->createToken('api')->plainTextToken;

        ActivityLogger::log(
            "User Registered from Order",
            "Guest converted to user: {$user->name} ({$user->email}) from order #{$order->tracking_code}",
            "success",
            ['user_id' => $user->id, 'order_id' => $order->id]
        );

        return response()->json([
            'message' => 'Compte créé avec succès',
            'token' => $token,
            'user' => [
                '_id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }
    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'status' => 200,
            'message' => 'Logged out successfully',
        ], 200);
    }
}

