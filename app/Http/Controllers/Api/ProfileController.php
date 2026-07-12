<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    // Get current user profile
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    // Update current user profile
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'phone'    => 'sometimes|nullable|string|max:20',
            'password' => 'sometimes|nullable|string|min:6|confirmed',
        ]);

        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Handle avatar upload (base64)
        if ($request->has('avatar')) {
            $avatarData = $request->avatar;
            if (preg_match('/^data:image\/(\w+);base64,/', $avatarData, $matches)) {
                $ext      = $matches[1];
                $imgData  = substr($avatarData, strpos($avatarData, ',') + 1);
                $imgData  = base64_decode($imgData);
                $filename = 'avatars/' . $user->id . '.' . $ext;
                Storage::disk('public')->put($filename, $imgData);
                $data['avatar'] = Storage::url($filename);
            }
        }

        $user->update($data);
        return response()->json($user->fresh());
    }

    // Admin: get all users
    public function allUsers(Request $request)
    {
        $users = User::latest()->get();
        return response()->json($users);
    }

    // Admin: update any user
    public function adminUpdate(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|email|unique:users,email,' . $id,
            'phone'    => 'sometimes|nullable|string|max:20',
            'role'     => 'sometimes|in:admin,doctor,secretary,patient',
            'password' => 'sometimes|nullable|string|min:6',
        ]);

        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Handle avatar upload (base64) — same format as the self-service profile update
        if ($request->has('avatar')) {
            $avatarData = $request->avatar;
            if (preg_match('/^data:image\/(\w+);base64,/', $avatarData, $matches)) {
                $ext      = $matches[1];
                $imgData  = substr($avatarData, strpos($avatarData, ',') + 1);
                $imgData  = base64_decode($imgData);
                $filename = 'avatars/' . $user->id . '.' . $ext;
                Storage::disk('public')->put($filename, $imgData);
                $data['avatar'] = Storage::url($filename);
            }
        }

        $user->update($data);
        return response()->json($user->fresh());
    }

    // Admin: delete user
    public function adminDelete($id)
    {
        $user = User::findOrFail($id);
        $user->tokens()->delete();
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }
}