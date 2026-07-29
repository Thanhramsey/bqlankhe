<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Menu;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $identifier = trim($request->validated('identifier'));
        $user = User::query()->where('username', $identifier)->orWhere('identity_number', $identifier)->first();
        if (! $user || ! $user->is_active || ! Hash::check($request->password, $user->password)) {
            AuditLog::create(['user_id' => $user?->id, 'action' => 'LOGIN_FAILED', 'entity_type' => User::class, 'entity_id' => $user?->id, 'new_values' => ['identifier' => $identifier], 'ip_address' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Tài khoản, số CCCD hoặc mật khẩu không đúng.', 'data' => null], 422);
        }

        AuditLog::create(['user_id' => $user->id, 'action' => 'LOGIN', 'entity_type' => User::class, 'entity_id' => $user->id, 'new_values' => ['username' => $user->username], 'ip_address' => $request->ip()]);

        return response()->json(['success' => true, 'message' => 'Đăng nhập thành công.', 'data' => ['token' => $user->createToken('admin')->plainTextToken, 'user' => $this->profileData($user)]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => $this->profileData($request->user())]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['NAM', 'NU', 'KHAC'])],
            'identity_number' => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($user->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'current_password' => ['required_with:password', 'nullable', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required_with' => 'Vui lòng nhập mật khẩu hiện tại.',
            'current_password.current_password' => 'Mật khẩu hiện tại không đúng.',
            'password.min' => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
            'avatar.max' => 'Ảnh đại diện không được lớn hơn 2 MB.',
        ]);

        $removeAvatar = $request->boolean('remove_avatar');
        unset($data['current_password'], $data['password_confirmation'], $data['remove_avatar'], $data['avatar']);
        if (empty($data['password'])) unset($data['password']);

        if (($request->hasFile('avatar') || $removeAvatar) && $user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $data['avatar'] = null;
        }
        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);
        AuditLog::create(['user_id' => $user->id, 'action' => isset($data['password']) ? 'CHANGE_PASSWORD' : 'UPDATE_PROFILE', 'entity_type' => User::class, 'entity_id' => $user->id, 'new_values' => ['profile_updated' => true, 'avatar_changed' => $request->hasFile('avatar') || $removeAvatar], 'ip_address' => $request->ip()]);

        return response()->json(['success' => true, 'message' => 'Cập nhật thông tin cá nhân thành công.', 'data' => $this->profileData($user->fresh())]);
    }

    public function logout(Request $request): JsonResponse
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'LOGOUT', 'entity_type' => User::class, 'entity_id' => $request->user()->id, 'ip_address' => $request->ip()]);
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['success' => true, 'message' => 'Đã đăng xuất.', 'data' => null]);
    }

    private function profileData(User $user): array
    {
        $permissions = $user->permissions();

        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
            'gender' => $user->gender,
            'identity_number' => $user->identity_number,
            'address' => $user->address,
            'avatar_url' => $user->avatar ? Storage::disk('public')->url($user->avatar) : null,
            'permissions' => $permissions,
            'menus' => Menu::where('is_active', true)->where(fn ($q) => $q->whereNull('permission_code')->orWhereIn('permission_code', $permissions))->orderBy('sort_order')->get(),
        ];
    }
}
