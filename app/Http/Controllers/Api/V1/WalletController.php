<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletPin;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $walletService
    ) {}

    /**
     * GET /wallet/balance
     */
    public function balance(Request $request): JsonResponse
    {
        $wallet = $this->walletService->getOrCreateWallet($request->user());

        return response()->json([
            'message' => 'success',
            'wallet' => [
                'id' => $wallet->id,
                'balance' => $wallet->balance,
                'currency' => $wallet->currency,
            ],
        ]);
    }

    /**
     * GET /wallet/transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $wallet = $this->walletService->getOrCreateWallet($request->user());

        $transactions = $wallet->transactions()
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'message' => 'success',
            'transactions' => $transactions,
        ]);
    }

    /**
     * POST /wallet/recharge — Admin only
     * Accepts wallet_id OR user_id (auto-creates wallet if needed)
     */
    public function recharge(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, [User::ROLE_ADMIN, User::ROLE_DEV])) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'wallet_id' => 'required_without:user_id|exists:wallets,id',
            'user_id' => 'required_without:wallet_id|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        try {
            if ($request->has('user_id')) {
                $targetUser = User::findOrFail($request->input('user_id'));
                $wallet = $this->walletService->getOrCreateWallet($targetUser);
            } else {
                $wallet = Wallet::findOrFail($request->input('wallet_id'));
            }

            $wallet->credit(
                (float) $request->input('amount'),
                'recharge',
                null,
                "Recharge par admin #{$user->id}"
            );

            $wallet->refresh();

            return response()->json([
                'message' => 'Wallet rechargé avec succès.',
                'wallet' => [
                    'id' => $wallet->id,
                    'balance' => $wallet->balance,
                    'currency' => $wallet->currency,
                    'user' => $wallet->user ? [
                        'id' => $wallet->user->id,
                        'name' => $wallet->user->name,
                        'role' => $wallet->user->role,
                    ] : null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /wallet/treasury/generate — Dev only
     */
    public function treasuryGenerate(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== User::ROLE_DEV) {
            return response()->json(['message' => 'Non autorisé. Réservé au rôle Dev.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'admin_user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $adminUser = User::find($request->input('admin_user_id'));
        if ($adminUser->role !== User::ROLE_ADMIN) {
            return response()->json(['message' => 'L\'utilisateur cible doit être un admin.'], 422);
        }

        try {
            $wallet = $this->walletService->generateTreasury(
                (float) $request->input('amount'),
                $user->id,
                $adminUser->id
            );

            return response()->json([
                'message' => 'Trésorerie générée avec succès.',
                'wallet' => [
                    'id' => $wallet->id,
                    'balance' => $wallet->balance,
                    'currency' => $wallet->currency,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /wallet/pins/generate
     */
    public function generatePin(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, [User::ROLE_STOCKIST, User::ROLE_MEMBER, User::ROLE_DISTRIBUTOR])) {
            return response()->json(['message' => 'Non autorisé. Réservé aux stockists et membres.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        try {
            $wallet = $this->walletService->getOrCreateWallet($user);
            $pin = $this->walletService->generatePin($wallet, (float) $request->input('amount'));

            return response()->json([
                'message' => 'PIN généré avec succès.',
                'pin' => [
                    'code' => $pin->code,
                    'amount' => $pin->amount,
                    'expires_at' => $pin->expires_at->toIso8601String(),
                    'seller_id' => $pin->seller_id,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /wallet/pins/validate — public-style but needs context
     */
    public function validatePin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'seller_id' => 'required|exists:users,id',
            'pin_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $pin = $this->walletService->validatePin(
            (int) $request->input('seller_id'),
            $request->input('pin_code')
        );

        if (!$pin) {
            return response()->json([
                'message' => 'PIN invalide, expiré ou déjà utilisé.',
                'valid' => false,
            ], 404);
        }

        return response()->json([
            'message' => 'PIN valide.',
            'valid' => true,
            'pin' => [
                'amount' => $pin->amount,
                'expires_at' => $pin->expires_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /wallet/pins/redeem
     * Accepts either order_id OR registration_id (mutually exclusive)
     */
    public function redeemPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'seller_id' => 'required|exists:users,id',
            'pin_code' => 'required|string|size:6',
            'order_id' => 'required_without:registration_id|exists:orders,id',
            'registration_id' => 'required_without:order_id|exists:registrations,id',
            'order_total' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        try {
            $sellerId = (int) $request->input('seller_id');
            $pinCode = $request->input('pin_code');
            $total = (float) $request->input('order_total');

            if ($request->has('registration_id')) {
                // Registration flow
                $pin = $this->walletService->redeemPinForRegistration(
                    $sellerId,
                    $pinCode,
                    (int) $request->input('registration_id'),
                    $total
                );
            } else {
                // Order flow (existing)
                $pin = $this->walletService->redeemPin(
                    $sellerId,
                    $pinCode,
                    (int) $request->input('order_id'),
                    $total
                );
            }

            return response()->json([
                'message' => 'PIN utilisé avec succès.',
                'pin' => [
                    'code' => $pin->code,
                    'amount' => $pin->amount,
                    'amount_used' => $pin->amount_used,
                    'status' => $pin->status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /wallet/pins/{id}/refund — Admin only
     * Rembourse un PIN qui n'a pas pu être utilisé et recrédite le wallet du vendeur.
     */
    public function refundPin(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, [User::ROLE_ADMIN, User::ROLE_DEV])) {
            return response()->json(['message' => 'Non autorisé. Réservé aux admins.'], 403);
        }

        $pin = WalletPin::with('sellerWallet')->find($id);
        if (!$pin) {
            return response()->json(['message' => 'PIN introuvable.'], 404);
        }

        $reason = $request->input('reason', '');

        try {
            $pin = $this->walletService->refundPin($pin, $user->id, $reason);

            return response()->json([
                'message' => 'PIN remboursé avec succès. Le vendeur peut générer un nouveau PIN.',
                'pin' => [
                    'id' => $pin->id,
                    'code' => $pin->code,
                    'amount' => $pin->amount,
                    'status' => $pin->status,
                ],
                'wallet' => [
                    'id' => $pin->sellerWallet->id,
                    'balance' => $pin->sellerWallet->fresh()->balance,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /wallet/pins/all — Admin: list all PINs (for refund management)
     */
    public function allPins(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, [User::ROLE_ADMIN, User::ROLE_DEV])) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $query = WalletPin::with('seller:id,name,username,email')
            ->orderByDesc('created_at');

        if ($request->has('seller_id')) {
            $query->where('seller_id', $request->input('seller_id'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $pins = $query->paginate($request->input('per_page', 30));

        return response()->json([
            'message' => 'success',
            'pins' => $pins,
        ]);
    }

    /**
     * GET /wallet/pins/history
     */
    public function pinHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        $pins = WalletPin::where('seller_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'message' => 'success',
            'pins' => $pins,
        ]);
    }

    /**
     * GET /wallet/sellers — Public, filtered by country
     */
    public function sellers(Request $request): JsonResponse
    {
        $query = User::where('is_wallet_seller', true)
            ->whereIn('role', [User::ROLE_STOCKIST, User::ROLE_MEMBER, User::ROLE_DISTRIBUTOR]);

        if ($request->has('country_id')) {
            $query->where('country_id', $request->input('country_id'));
        }

        $sellers = $query->select([
            'id', 'name', 'username', 'email', 'phone',
            'wallet_seller_whatsapp', 'country_id', 'city',
        ])->with('country:id,name')->get();

        return response()->json([
            'message' => 'success',
            'sellers' => $sellers,
        ]);
    }

    /**
     * POST /wallet/sellers/update — Admin/Dev: toggle wallet seller status
     */
    public function updateSeller(Request $request): JsonResponse
    {
        $authUser = $request->user();
        if (!in_array($authUser->role, [User::ROLE_ADMIN, User::ROLE_DEV])) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'is_wallet_seller' => 'required|boolean',
            'wallet_seller_whatsapp' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $user = User::findOrFail($request->input('user_id'));

        if (!in_array($user->role, [User::ROLE_STOCKIST, User::ROLE_MEMBER, User::ROLE_DISTRIBUTOR])) {
            return response()->json(['message' => 'Seuls les stockists, membres et distributeurs peuvent être vendeurs.'], 422);
        }

        $user->is_wallet_seller = $request->input('is_wallet_seller');
        if ($request->has('wallet_seller_whatsapp')) {
            $user->wallet_seller_whatsapp = $request->input('wallet_seller_whatsapp');
        }
        $user->save();

        return response()->json([
            'message' => $user->is_wallet_seller ? 'Vendeur activé.' : 'Vendeur désactivé.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_wallet_seller' => $user->is_wallet_seller,
                'wallet_seller_whatsapp' => $user->wallet_seller_whatsapp,
            ],
        ]);
    }

    /**
     * GET /wallet/sellers/eligible — Admin/Dev: list users eligible to be sellers
     */
    public function eligibleSellers(Request $request): JsonResponse
    {
        $authUser = $request->user();
        if (!in_array($authUser->role, [User::ROLE_ADMIN, User::ROLE_DEV])) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $query = User::whereIn('role', [User::ROLE_STOCKIST, User::ROLE_MEMBER, User::ROLE_DISTRIBUTOR])
            ->select(['id', 'name', 'username', 'email', 'phone', 'role', 'is_wallet_seller', 'wallet_seller_whatsapp', 'country_id', 'city'])
            ->with('country:id,name');

        if ($request->has('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%")
                  ->orWhere('username', 'ilike', "%{$s}%");
            });
        }

        $users = $query->orderByDesc('is_wallet_seller')
            ->orderBy('name')
            ->paginate($request->input('per_page', 30));

        return response()->json([
            'message' => 'success',
            'users' => $users,
        ]);
    }

    /**
     * GET /wallet/all — Admin: list all wallets
     */
    public function allWallets(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, [User::ROLE_ADMIN, User::ROLE_DEV])) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $wallets = Wallet::with('user:id,name,username,email,role')
            ->orderByDesc('balance')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'message' => 'success',
            'wallets' => $wallets,
        ]);
    }

    /**
     * GET /wallet/transactions/all — Admin: global transaction audit log
     */
    public function allTransactions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, [User::ROLE_ADMIN, User::ROLE_DEV])) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $transactions = \App\Models\WalletTransaction::with('wallet.user:id,name,username,role')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 30));

        return response()->json([
            'message' => 'success',
            'transactions' => $transactions,
        ]);
    }
}
