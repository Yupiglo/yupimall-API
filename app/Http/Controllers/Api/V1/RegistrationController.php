<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Registration;
use Illuminate\Support\Facades\Validator;

class RegistrationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth('sanctum')->user();
        $query = Registration::query();

        if ($user && $user->role === \App\Models\User::ROLE_WAREHOUSE) {
            $query->where('requested_role', \App\Models\User::ROLE_STOCKIST);
        }

        $registrations = $query->orderBy('created_at', 'desc')->get();
        return response()->json([
            'status' => 200,
            'registrations' => $registrations,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:registrations,username|unique:users,username',
            'email' => 'required|email|max:255|unique:registrations,email|unique:users,email',
            'phone' => 'required|string|max:50',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'plan' => 'required|string|max:50',
            'payment_method' => 'required|string|max:50',
            'password' => 'required|string|min:8',
            'requested_role' => 'sometimes|string|in:stockist,warehouse',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $data = $request->all();
        if (auth('sanctum')->check()) {
            $data['created_by'] = auth('sanctum')->id();
        }

        $registration = Registration::create($data);

        // Notify Admins and Devs
        $admins = \App\Models\User::whereIn('role', [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_DEV])->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::create([
                'title' => 'Nouvelle inscription',
                'message' => 'Une nouvelle inscription (' . $registration->requested_role . ') de ' . $registration->first_name . ' ' . $registration->last_name . ' a été reçue.',
                'category' => 'system',
                'type' => 'info',
                'user_id' => $admin->id,
                'is_read' => false,
                'metadata' => [
                    'registration_id' => $registration->id,
                    'type' => 'new_registration'
                ]
            ]);
        }

        return response()->json([
            'status' => 201,
            'message' => 'Inscription enregistrée avec succès. Elle sera examinée par notre équipe.',
            'registration' => $registration,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $registration = Registration::find($id);
        if (!$registration) {
            return response()->json(['status' => 404, 'message' => 'Registration not found'], 404);
        }
        return response()->json(['status' => 200, 'registration' => $registration]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $registration = Registration::find($id);
        if (!$registration) {
            return response()->json(['status' => 404, 'message' => 'Registration not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255|unique:registrations,username,' . $id . '|unique:users,username',
            'email' => 'sometimes|email|max:255|unique:registrations,email,' . $id . '|unique:users,email',
            'phone' => 'sometimes|string|max:50',
            'address' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'country' => 'sometimes|string|max:255',
            'plan' => 'sometimes|string|max:50',
            'payment_method' => 'sometimes|string|max:50',
            'status' => 'sometimes|string|in:pending,approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $user = auth('sanctum')->user();

        if ($request->status === 'approved' && $registration->status !== 'approved') {
            // Permission Check: Warehouse-created registrations require Admin/Dev approval
            if ($registration->created_by) {
                $creator = \App\Models\User::find($registration->created_by);
                if ($creator && $creator->role === \App\Models\User::ROLE_WAREHOUSE) {
                    if (!$user->isAdmin()) {
                        return response()->json([
                            'status' => 403,
                            'message' => 'Les inscriptions créées par le Warehouse doivent être validées par un Admin.'
                        ], 403);
                    }
                }
            }

            // Permission Check: Warehouse registrations require Admin/Dev approval
            if ($registration->requested_role === \App\Models\User::ROLE_WAREHOUSE) {
                if (!$user->isAdmin()) {
                    return response()->json([
                        'status' => 403,
                        'message' => 'Les inscriptions pour le rôle Warehouse doivent être validées par un Admin.'
                    ], 403);
                }
            }

            $country = \App\Models\Country::where('name', $registration->country)->first();

            // Create User
            \App\Models\User::create([
                'name' => $registration->first_name . ' ' . $registration->last_name,
                'username' => $registration->username,
                'email' => $registration->email,
                'password' => $registration->password, // Password hashing handled by User model casts
                'role' => $registration->requested_role ?: \App\Models\User::ROLE_STOCKIST,
                'phone' => $registration->phone,
                'country_id' => $country ? $country->id : null,
                'city' => $registration->city,
                'address' => $registration->address,
            ]);
        }

        $registration->update($request->all());

        return response()->json([
            'status' => 200,
            'message' => 'Inscription mise à jour avec succès.',
            'registration' => $registration,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $registration = Registration::find($id);
        if (!$registration) {
            return response()->json(['status' => 404, 'message' => 'Registration not found'], 404);
        }
        $registration->delete();
        return response()->json(['status' => 200, 'message' => 'Registration deleted']);
    }
}
