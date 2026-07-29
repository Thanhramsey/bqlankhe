<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.manage') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->getKey();

        return [
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users')->ignore($userId)],
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['NAM', 'NU', 'KHAC'])],
            'phone' => ['nullable', 'string', 'max:20'],
            'identity_number' => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($userId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'collection_route_id' => ['nullable', 'integer', 'exists:collection_routes,id'],
            'route_ids' => ['nullable', 'array'],
            'route_ids.*' => ['integer', 'exists:collection_routes,id'],
            'menu_ids' => ['nullable', 'array'],
            'menu_ids.*' => ['integer', 'exists:menus,id'],
            'menu_access_custom' => ['required', 'boolean'],
            'password' => [$userId ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Vui lòng nhập tài khoản.',
            'username.unique' => 'Tài khoản đã tồn tại.',
            'email.unique' => 'Email đã tồn tại.',
            'identity_number.unique' => 'Số giấy tờ đã tồn tại.',
            'role_ids.required' => 'Vui lòng chọn ít nhất một vai trò.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'avatar.max' => 'Avatar không được lớn hơn 2 MB.',
        ];
    }
}
