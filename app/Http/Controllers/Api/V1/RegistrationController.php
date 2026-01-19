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
        $registrations = Registration::orderBy('created_at', 'desc')->get();
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $registration = Registration::create($request->all());

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

        $registration->update($request->all());

        return response()->json([
            'status' => 200,
            'message' => 'Registration updated successfully',
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
