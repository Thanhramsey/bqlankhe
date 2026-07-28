<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();
        if (! $user || ! $user->is_active || ! Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Email hoặc mật khẩu không đúng.', 'data' => null], 422);
        }

return response()->json(['success' => true, 'message' => 'Đăng nhập thành công.', 'data' => ['token' => $user->createToken('admin')->plainTextToken, 'user' => $this->profileData($user)]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => $this->profileData($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['success' => true, 'message' => 'Đã đăng xuất.', 'data' => null]);
    }

    private function profileData(User $user): array
    {
        $permissions = $user->permissions();

        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'permissions' => $permissions, 'menus' => Menu::where('is_active', true)->where(fn ($q) => $q->whereNull('permission_code')->orWhereIn('permission_code', $permissions))->orderBy('sort_order')->get()];
    }
}
