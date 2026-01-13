<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Notification::where('user_id', $user->id)
            ->orWhereNull('user_id'); // System-wide notifications

        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'status' => 200,
            'notifications' => $notifications
        ]);
    }

    /**
     * Display a listing of public notifications (broadcast).
     */
    public function publicIndex(Request $request)
    {
        $query = Notification::whereNull('user_id');

        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'status' => 200,
            'notifications' => $notifications
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function update(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);

        // Ensure user can only mark their own notifications
        if ($notification->user_id && $notification->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->only(['title', 'message', 'is_read', 'category', 'type']);

        // Default behavior if no data provided: mark as read
        if (empty($data)) {
            $notification->update(['is_read' => true]);
        } else {
            $notification->update($data);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Notification updated successfully',
            'notification' => $notification
        ]);
    }

    /**
     * Mark all notifications as read for the current user.
     */
    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'status' => 200,
            'message' => 'All notifications marked as read'
        ]);
    }

    /**
     * Display the specified notification.
     */
    public function show($id)
    {
        $notification = Notification::findOrFail($id);

        // Ensure user can only view their own notifications OR it's a broadcast
        if ($notification->user_id && $notification->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => 200,
            'notification' => $notification
        ]);
    }

    /**
     * Store a new notification (usually system-triggered).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'message' => 'required|string',
            'category' => 'required|string',
            'type' => 'required|string',
            'user_id' => 'nullable|exists:users,id',
            'metadata' => 'nullable|array',
        ]);

        $notification = Notification::create($validated);

        return response()->json([
            'status' => 201,
            'notification' => $notification
        ]);
    }

    /**
     * Remove the specified notification from storage.
     */
    public function destroy($id)
    {
        $notification = Notification::findOrFail($id);

        // Ensure user can only delete their own notifications OR it's a broadcast
        if ($notification->user_id && $notification->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Notification deleted successfully'
        ]);
    }
}
