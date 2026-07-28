<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserService
{
    public function create(array $data, ?UploadedFile $avatar): User
    {
        return DB::transaction(function () use ($data, $avatar) {
            $roleIds = Arr::pull($data, 'role_ids');
            $routeIds = Arr::pull($data, 'route_ids', []);
            Arr::forget($data, ['password_confirmation', 'remove_avatar']);
            if ($avatar) {
                $data['avatar'] = $avatar->store('avatars', 'public');
            }
            $user = User::create($data);
            $user->roles()->sync($roleIds);
            $user->collectionRoutes()->sync($routeIds);

            return $user->load(['roles:id,name,code', 'collectionRoutes:id,code,name']);
        });
    }

    public function update(User $user, array $data, ?UploadedFile $avatar): User
    {
        return DB::transaction(function () use ($user, $data, $avatar) {
            $roleIds = Arr::pull($data, 'role_ids');
            $routeIds = Arr::pull($data, 'route_ids', []);
            $removeAvatar = (bool) Arr::pull($data, 'remove_avatar', false);
            Arr::forget($data, 'password_confirmation');
            if (empty($data['password'])) {
                unset($data['password']);
            }
            if (($avatar || $removeAvatar) && $user->avatar) {
                Storage::disk('public')->delete($user->avatar);
                $data['avatar'] = null;
            }
            if ($avatar) {
                $data['avatar'] = $avatar->store('avatars', 'public');
            }
            $user->update($data);
            $user->roles()->sync($roleIds);
            $user->collectionRoutes()->sync($routeIds);

            return $user->load(['roles:id,name,code', 'collectionRoutes:id,code,name']);
        });
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->roles()->detach();
            $user->delete();
        });
    }
}
