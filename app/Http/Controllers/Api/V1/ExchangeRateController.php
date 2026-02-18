<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExchangeRateController extends Controller
{
    /**
     * GET /exchange-rates — Public
     */
    public function index(): JsonResponse
    {
        $rates = ExchangeRate::active()
            ->orderBy('from_currency')
            ->get();

        return response()->json([
            'message' => 'success',
            'rates' => $rates,
        ]);
    }

    /**
     * POST /exchange-rates — Admin/Dev only
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, [User::ROLE_ADMIN, User::ROLE_DEV])) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'from_currency' => 'required|string|size:3',
            'to_currency' => 'string|size:3',
            'rate' => 'required|numeric|min:0.000001',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $fromCurrency = strtoupper($request->input('from_currency'));
        $toCurrency = strtoupper($request->input('to_currency', 'USD'));

        ExchangeRate::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $rate = ExchangeRate::create([
            'from_currency' => $fromCurrency,
            'to_currency' => $toCurrency,
            'rate' => $request->input('rate'),
            'set_by' => $user->id,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Taux de change configuré avec succès.',
            'rate' => $rate,
        ], 201);
    }
}
