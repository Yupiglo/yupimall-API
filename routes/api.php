<?php

use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\AdminStatsController;
use App\Http\Controllers\Api\V1\BannerController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SubcategoryController;
use App\Http\Controllers\Api\V1\SendEmailController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WishlistController;
use App\Http\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Api\V1\StockEntryController;
use App\Http\Controllers\Api\V1\StockExitController;
use App\Http\Controllers\Api\V1\CountryController;
use App\Http\Controllers\Api\V1\RegistrationController;
use App\Http\Controllers\Api\V1\OperationalStatsController;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 200, 'responseMsg' => 'OK']);
    });

    Route::get('/public/notifications', [\App\Http\Controllers\Api\V1\NotificationController::class, 'publicIndex']);

    // Registration (public)
    Route::post('/registrations', [RegistrationController::class, 'store']);

    Route::prefix('auth')->group(function () {
        Route::post('/signin', [AuthController::class, 'signin']);
        Route::post('/signup', [RegistrationController::class, 'store']); // Alias for member registration
        Route::post('/loginWithYupi', [AuthController::class, 'loginWithYupi']);
        Route::post('/register-from-order', [AuthController::class, 'registerFromOrder']); // Guest conversion
        Route::post('/validateSession', [AuthController::class, 'validateSession'])->middleware('auth:sanctum');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::post('/refresh-token', function () {
            return response()->json(['message' => 'Not implemented in Sanctum, tokens are long-lived or handled via re-auth'], 200);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [UserController::class, 'me']);
        Route::put('/me', [UserController::class, 'updateProfile']);
        Route::post('/me/avatar', [UserController::class, 'uploadAvatar']);

        // Alias routes for frontend compatibility
        Route::prefix('users')->group(function () {
            Route::get('/profile', [UserController::class, 'me']);
            Route::put('/profile', [UserController::class, 'updateProfile']);
        });

        // Logs
        Route::get('/admin/logs', [\App\Http\Controllers\Api\V1\LogController::class, 'auditLogs']);
        Route::get('/dev/logs', [\App\Http\Controllers\Api\V1\LogController::class, 'systemLogs']);

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\NotificationController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\NotificationController::class, 'store']);
            Route::patch('/{id}/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'update']);
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\NotificationController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\NotificationController::class, 'destroy']);
            Route::post('/mark-all-read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllAsRead']);
        });

        // Delivery Management
        Route::prefix('delivery')->group(function () {
            Route::get('/personnel', [DeliveryController::class, 'personnel']);
            Route::get('/active', [DeliveryController::class, 'activeDeliveries']);
            Route::get('/stats', [DeliveryController::class, 'stats']);
            Route::post('/assign/{orderId}', [DeliveryController::class, 'assignDeliveryPerson']);
            Route::patch('/status/{orderId}', [DeliveryController::class, 'updateStatus']);
        });

        // Stock Entries Management
        Route::prefix('stock/entries')->group(function () {
            Route::get('/', [StockEntryController::class, 'index']);
            Route::get('/stats', [StockEntryController::class, 'stats']);
            Route::post('/', [StockEntryController::class, 'store']);
            Route::get('/{stockEntry}', [StockEntryController::class, 'show']);
            Route::put('/{stockEntry}', [StockEntryController::class, 'update']);
            Route::delete('/{stockEntry}', [StockEntryController::class, 'destroy']);
        });

        // Stock Exits Management
        Route::prefix('stock/exits')->group(function () {
            Route::get('/', [StockExitController::class, 'index']);
            Route::get('/stats', [StockExitController::class, 'stats']);
            Route::get('/reasons', [StockExitController::class, 'reasons']);
            Route::post('/', [StockExitController::class, 'store']);
            Route::get('/{stockExit}', [StockExitController::class, 'show']);
            Route::put('/{stockExit}', [StockExitController::class, 'update']);
            Route::delete('/{stockExit}', [StockExitController::class, 'destroy']);
        });

        Route::get('/operational-stats', [OperationalStatsController::class, 'index']);
    });

    Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
        Route::get('/stats', [AdminStatsController::class, 'index']);
        Route::get('/sales-by-country', [AdminStatsController::class, 'salesByCountry']);
    });

    Route::get('/settings', [SettingsController::class, 'index']);
    Route::get('/settings/general', [SettingsController::class, 'general']);
    Route::get('/countries', [CountryController::class, 'index']);

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/reorder', [ProductController::class, 'reorder'])->middleware('auth:sanctum');
        Route::post('/shuffle', [ProductController::class, 'shuffle'])->middleware('auth:sanctum');
        Route::post('/', [ProductController::class, 'store'])->middleware('auth:sanctum');

        Route::get('/special', [ProductController::class, 'special']);
        Route::get('/filter', [ProductController::class, 'filter']);
        Route::get('/category/{category}', [ProductController::class, 'byCategory']);

        Route::get('/{id}', [ProductController::class, 'show']);
        Route::put('/{id}', [ProductController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('/{id}', [ProductController::class, 'destroy'])->middleware('auth:sanctum');
    });

    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store'])->middleware('auth:sanctum');
        Route::put('/{id}', [CategoryController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('/{id}', [CategoryController::class, 'destroy'])->middleware('auth:sanctum');

        Route::prefix('{categoryId}/subcategories')->group(function () {
            Route::get('/', [SubcategoryController::class, 'index']);
            Route::post('/', [SubcategoryController::class, 'store'])->middleware('auth:sanctum');
            Route::put('/{id}', [SubcategoryController::class, 'update'])->middleware('auth:sanctum');
            Route::delete('/{id}', [SubcategoryController::class, 'destroy'])->middleware('auth:sanctum');
        });
    });

    Route::prefix('subcategories')->group(function () {
        Route::get('/', [SubcategoryController::class, 'index']);
        Route::post('/', [SubcategoryController::class, 'store'])->middleware('auth:sanctum');
        Route::put('/{id}', [SubcategoryController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('/{id}', [SubcategoryController::class, 'destroy'])->middleware('auth:sanctum');
    });

    Route::prefix('brands')->group(function () {
        Route::get('/', [BrandController::class, 'index']);
        Route::post('/', [BrandController::class, 'store'])->middleware('auth:sanctum');
        Route::put('/{id}', [BrandController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('/{id}', [BrandController::class, 'destroy'])->middleware('auth:sanctum');
    });

    Route::prefix('banner')->group(function () {
        Route::get('/', [BannerController::class, 'index']);
        Route::post('/', [BannerController::class, 'store'])->middleware('auth:sanctum');
        Route::put('/{id}', [BannerController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('/{id}', [BannerController::class, 'destroy'])->middleware('auth:sanctum');
    });

    Route::prefix('coupons')->group(function () {
        Route::get('/', [CouponController::class, 'index']);
        Route::post('/', [CouponController::class, 'store'])->middleware('auth:sanctum');
        Route::get('/{id}', [CouponController::class, 'show']);
        Route::put('/{id}', [CouponController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('/{id}', [CouponController::class, 'destroy'])->middleware('auth:sanctum');
    });

    Route::prefix('carts')->group(function () {
        Route::get('/', [CartController::class, 'show'])->middleware('auth:sanctum');
        Route::post('/', [CartController::class, 'store'])->middleware('auth:sanctum');
        Route::post('/apply-coupon', [CartController::class, 'applyCoupon'])->middleware('auth:sanctum');
        Route::delete('/{id}', [CartController::class, 'destroy'])->middleware('auth:sanctum');
        Route::put('/{id}', [CartController::class, 'update'])->middleware('auth:sanctum');
    });

    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'show'])->middleware('auth:sanctum');
        Route::get('/all', [OrderController::class, 'index'])->middleware('auth:sanctum');
        Route::get('/track/{code}', [OrderController::class, 'track']); // Public tracking
        Route::post('/guest', [OrderController::class, 'storeGuest']); // Guest order (no auth)
        Route::get('/search/{code}', [OrderController::class, 'searchByCode'])->middleware('auth:sanctum');
        Route::get('/{id}', [OrderController::class, 'showOne'])->middleware('auth:sanctum');
        Route::post('/checkOut/{id}', [OrderController::class, 'checkOut'])->middleware('auth:sanctum');
        Route::post('/user-cart', [OrderController::class, 'storeFromUserCart'])->middleware('auth:sanctum');
        Route::post('/{id}', [OrderController::class, 'store'])->middleware('auth:sanctum');
        Route::put('/{id}', [OrderController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('/{id}', [OrderController::class, 'destroy'])->middleware('auth:sanctum');
        Route::post('/{id}/upload-proof', [OrderController::class, 'uploadProof'])->middleware('auth:sanctum');
    });

    Route::prefix('webhooks')->group(function () {
        Route::post('/moneroo', [WebhookController::class, 'moneroo']);
        Route::post('/axazara', [WebhookController::class, 'axazara']);
    });

    Route::prefix('wishlist')->group(function () {
        Route::get('/', [WishlistController::class, 'index'])->middleware('auth:sanctum');
        Route::patch('/', [WishlistController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('/', [WishlistController::class, 'destroy'])->middleware('auth:sanctum');
    });

    Route::prefix('review')->group(function () {
        Route::get('/', [ReviewController::class, 'index']);
        Route::post('/', [ReviewController::class, 'store'])->middleware('auth:sanctum');
        Route::get('/{id}', [ReviewController::class, 'show']);
        Route::put('/{id}', [ReviewController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('/{id}', [ReviewController::class, 'destroy'])->middleware('auth:sanctum');
    });

    Route::prefix('address')->group(function () {
        Route::get('/', [AddressController::class, 'index'])->middleware('auth:sanctum');
        Route::patch('/', [AddressController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('/', [AddressController::class, 'destroy'])->middleware('auth:sanctum');
    });

    Route::prefix('blogs')->group(function () {
        Route::get('/', [PostController::class, 'index']);
        Route::post('/', [PostController::class, 'store'])->middleware('auth:sanctum');
        Route::get('/{id}', [PostController::class, 'show']);
        Route::put('/{id}', [PostController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('/{id}', [PostController::class, 'destroy'])->middleware('auth:sanctum');
    });

    Route::prefix('users')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/getalluser', [UserController::class, 'getAllUsersSql']);

        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::patch('/{id}', [UserController::class, 'changePassword']);
    });

    Route::prefix('registrations')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [RegistrationController::class, 'index']);
        Route::get('/{id}', [RegistrationController::class, 'show']);
        Route::put('/{id}', [RegistrationController::class, 'update']);
        Route::delete('/{id}', [RegistrationController::class, 'destroy']);
    });

    Route::prefix('sendemail')->group(function () {
        Route::post('/', [SendEmailController::class, 'store']);
    });
});
