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
     * Used for member registration via /be-member (with paid pack)
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
            'package_id' => 'sometimes|string|max:50',
            'package_price' => 'sometimes|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'password' => 'required|string|min:8',
            'sponsor_id' => 'nullable|string|max:50',
            'zip_code' => 'nullable|string|max:20',
            // requested_role is optional - defaults to 'member' for /be-member signups
            'requested_role' => 'sometimes|string|in:member,stockist,warehouse',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $data = $request->all();

        // Set default role to 'member' if not specified (for /be-member signups)
        if (!isset($data['requested_role']) || empty($data['requested_role'])) {
            $data['requested_role'] = 'member';
        }

        // Use package_id if provided, otherwise fallback to plan
        if (isset($data['package_id']) && !empty($data['package_id'])) {
            $data['plan'] = $data['package_id'];
        }

        // Payment status will be set to 'paid' after PIN redemption
        // For now, mark as pending - will be updated when PIN is redeemed
        // If payment_method is wallet or wallet_pin, keep as pending until PIN redemption
        $data['payment_status'] = 'pending';

        if (auth('sanctum')->check()) {
            $data['created_by'] = auth('sanctum')->id();
        }

        $registration = Registration::create($data);

        // Notify Admins and Devs
        $roleLabel = $data['requested_role'] === 'member' ? 'Membre' : ucfirst($data['requested_role']);
        $planLabel = strtoupper($registration->plan);

        $admins = \App\Models\User::whereIn('role', [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_DEV])->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::create([
                'title' => 'Nouvelle demande membre',
                'message' => "Demande d'inscription {$roleLabel} (Pack {$planLabel}) de {$registration->first_name} {$registration->last_name}. Paiement: {$data['payment_status']}.",
                'category' => 'system',
                'type' => 'info',
                'user_id' => $admin->id,
                'is_read' => false,
                'metadata' => [
                    'registration_id' => $registration->id,
                    'type' => 'new_member_registration',
                    'plan' => $registration->plan,
                    'payment_status' => $data['payment_status'],
                ]
            ]);
        }

        return response()->json([
            'status' => 201,
            'message' => 'Inscription enregistrée avec succès. Votre demande sera examinée par notre équipe.',
            'registration' => $registration,
            'payment_status' => $data['payment_status'],
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

            // Determine the role - use 'member' as default for /be-member signups
            $role = $registration->requested_role ?: \App\Models\User::ROLE_MEMBER;

            // Create User in local database
            $newUser = \App\Models\User::create([
                'name' => $registration->first_name . ' ' . $registration->last_name,
                'username' => $registration->username,
                'email' => $registration->email,
                'password' => $registration->password, // Password hashing handled by User model casts
                'role' => $role,
                'phone' => $registration->phone,
                'country_id' => $country ? $country->id : null,
                'city' => $registration->city,
                'address' => $registration->address,
            ]);

            // For members: Send email with credentials for external interface
            if ($role === 'member' || $role === \App\Models\User::ROLE_MEMBER) {
                // TODO: Send actual email with credentials
                // For now, log the action
                \Log::info("Member approved: {$registration->email}. Should send email with credentials for external member dashboard.");

                // Create notification for the approval
                // Note: The member's actual dashboard is external, we just store them locally for benefits
            }
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
