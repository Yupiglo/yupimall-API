<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     * Role-based filtering: dev sees all, others don't see dev users
     */
    public function index(Request $request)
    {
        $page = (int) request()->input('page', 1);
        $limit = (int) request()->input('limit', 50);
        if ($limit <= 0) {
            $limit = 50;
        }

        $query = User::query();

        // Role-based filtering: non-dev users cannot see dev users
        $currentUser = $request->user();
        if ($currentUser && $currentUser->role !== User::ROLE_DEV) {
            $query->where('role', '!=', User::ROLE_DEV);
        }

        // Warehouse filtering: can only see people in their country or their subordinates
        if ($currentUser && $currentUser->role === User::ROLE_WAREHOUSE) {
            $query->where(function ($q) use ($currentUser) {
                $q->where('country', $currentUser->country)
                    ->orWhere('supervisor_id', $currentUser->id);
            });
        }

        // Filter by role if specified
        if (request()->has('role')) {
            $query->where('role', request()->input('role'));
        }

        // Filter by supervisor if specified
        if (request()->has('supervisor_id')) {
            $query->where('supervisor_id', request()->input('supervisor_id'));
        }

        // Search filter
        if (request()->has('search')) {
            $search = request()->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('username', 'LIKE', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'page' => $page,
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
            'message' => 'success',
            'getAllUsers' => $paginator->items(),
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     * Only dev users can create other dev users
     */
    public function store(Request $request)
    {
        $validRoles = [
            User::ROLE_DEV,
            User::ROLE_ADMIN,
            User::ROLE_WEBMASTER,
            User::ROLE_STOCKIST,
            User::ROLE_WAREHOUSE,
            User::ROLE_DELIVERY,
            User::ROLE_DISTRIBUTOR,
            User::ROLE_CONSUMER,
        ];

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'in:' . implode(',', $validRoles)],
            'phone' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'supervisor_id' => ['nullable', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        // Only dev users can create other dev users
        $currentUser = $request->user();
        $requestedRole = $request->input('role');
        if ($requestedRole === User::ROLE_DEV && (!$currentUser || $currentUser->role !== User::ROLE_DEV)) {
            return response()->json(['message' => 'Only dev users can create other dev users'], 403);
        }

        // Logical defaults for Warehouse creating users
        $country = $request->input('country');
        $supervisorId = $request->input('supervisor_id');
        if ($currentUser && $currentUser->role === User::ROLE_WAREHOUSE) {
            $country = $currentUser->country;
            $supervisorId = $currentUser->id;
        }

        $user = User::create([
            'name' => $request->input('name'),
            'username' => $request->input('username'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => $requestedRole,
            'phone' => $request->input('phone'),
            'country' => $country,
            'supervisor_id' => $supervisorId,
        ]);

        ActivityLogger::log(
            "User Created",
            "New user {$user->name} ({$user->role}) created by {$currentUser->name}",
            "info"
        );

        return response()->json([
            'message' => 'User created successfully',
            'addUser' => $user,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json([
            'message' => 'success',
            'user' => $user,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     * Non-dev users cannot update dev users
     */
    public function update(Request $request, string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User was not found'], 404);
        }

        // Non-dev users cannot update dev users
        $currentUser = $request->user();
        if ($user->role === User::ROLE_DEV && (!$currentUser || $currentUser->role !== User::ROLE_DEV)) {
            return response()->json(['message' => 'You do not have permission to update this user'], 403);
        }

        $validRoles = [
            User::ROLE_DEV,
            User::ROLE_ADMIN,
            User::ROLE_WEBMASTER,
            User::ROLE_STOCKIST,
            User::ROLE_WAREHOUSE,
            User::ROLE_DELIVERY,
            User::ROLE_DISTRIBUTOR,
            User::ROLE_CONSUMER,
        ];

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'role' => ['nullable', 'string', 'in:' . implode(',', $validRoles)],
            'phone' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'supervisor_id' => ['nullable', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        // Prevent non-dev from changing role to dev
        $newRole = $request->input('role');
        if ($newRole === User::ROLE_DEV && (!$currentUser || $currentUser->role !== User::ROLE_DEV)) {
            return response()->json(['message' => 'Only dev users can assign dev role'], 403);
        }

        $user->fill($request->only(['name', 'username', 'email', 'role', 'phone', 'country', 'supervisor_id']));
        $user->save();

        return response()->json([
            'message' => 'User updated successfully',
            'updateUser' => $user->fresh(),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     * Non-dev users cannot delete dev users
     */
    public function destroy(Request $request, string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User was not found'], 404);
        }

        // Non-dev users cannot delete dev users
        $currentUser = $request->user();
        if ($user->role === User::ROLE_DEV && (!$currentUser || $currentUser->role !== User::ROLE_DEV)) {
            return response()->json(['message' => 'You do not have permission to delete this user'], 403);
        }

        // Prevent users from deleting themselves
        if ($currentUser && $currentUser->id === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account'], 400);
        }

        $userName = $user->name;
        $user->delete();

        ActivityLogger::log(
            "User Deleted",
            "User {$userName} was deleted by {$currentUser->name}",
            "warning"
        );

        return response()->json(['message' => 'User deleted successfully'], 200);
    }

    public function changePassword(Request $request, string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User was not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $user->password = Hash::make($request->input('password'));
        $user->save();

        return response()->json([
            'message' => 'success',
            'changeUserPassword' => $user->fresh(),
        ], 201);
    }

    public function getAllUsersSql()
    {
        $users = User::all();
        return response()->json($users, 200);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'message' => 'success',
            'user' => $user,
        ], 200);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'gender' => ['nullable', 'in:M,F,O'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'image_url' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $user->fill($request->only([
            'name',
            'phone',
            'bio',
            'gender',
            'address',
            'city',
            'country',
            'image_url'
        ]));

        $user->save();

        ActivityLogger::log(
            "Profile Updated",
            "User {$user->name} updated their profile information",
            "info"
        );

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
        ], 200);
    }

    /**
     * Upload an avatar for the authenticated user.
     */
    public function uploadAvatar(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $folder = 'avatars';
            $dir = public_path('uploads/' . $folder);

            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            $name = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
            $file->move($dir, $name);

            $imageUrl = 'uploads/' . $folder . '/' . $name;

            // Delete old avatar if exists
            if ($user->image_url && File::exists(public_path($user->image_url))) {
                // Keep default or system images if any, but usually we can delete
                if (Str::contains($user->image_url, 'uploads/avatars')) {
                    File::delete(public_path($user->image_url));
                }
            }

            $user->image_url = $imageUrl;
            $user->save();

            ActivityLogger::log(
                "Avatar Updated",
                "User {$user->name} updated their profile picture",
                "info"
            );

            return response()->json([
                'message' => 'Avatar uploaded successfully',
                'image_url' => $imageUrl,
                'user' => $user->fresh()
            ], 200);
        }

        return response()->json(['message' => 'No image file provided'], 400);
    }
}
