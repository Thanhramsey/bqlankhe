<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUserRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\CollectionRoute;
use App\Models\Role;
use App\Models\Menu;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $service) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);
        $users = User::query()
            ->with(['roles:id,name,code', 'collectionRoutes:id,code,name', 'menus:id,name,path,parent_id'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search')->trim().'%';
                $query->where(fn ($q) => $q->where('username', 'like', $term)
                    ->orWhere('name', 'like', $term)->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)->orWhere('identity_number', 'like', $term));
            })
            ->latest('id')->paginate(min($request->integer('per_page', 15), 100));

        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => [
            ...$users->toArray(), 'data' => UserResource::collection($users->getCollection())->resolve(),
        ]]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => [
            'roles' => Role::query()->select('id', 'name', 'code')->orderBy('name')->get(),
            'routes' => CollectionRoute::query()->select('id', 'code', 'name')->where('is_active', true)->orderBy('name')->get(),
            'menus' => Menu::query()->with('parent:id,name,icon,sort_order')->where('is_active', true)
                ->whereNotNull('permission_code')->orderBy('sort_order')->get(['id','parent_id','name','path','icon','permission_code','sort_order']),
        ]]);
    }

    public function store(SaveUserRequest $request): JsonResponse
    {
        $user = $this->service->create($request->validated(), $request->file('avatar'));
        $this->audit($request, 'CREATE', $user);

        return response()->json(['success' => true, 'message' => 'Đã thêm người dùng.', 'data' => new UserResource($user)], 201);
    }

    public function update(SaveUserRequest $request, User $user): JsonResponse
    {
        $old = $user->load('roles')->toArray();
        $user = $this->service->update($user, $request->validated(), $request->file('avatar'));
        $this->audit($request, 'UPDATE', $user, $old);

        return response()->json(['success' => true, 'message' => 'Đã cập nhật người dùng.', 'data' => new UserResource($user)]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);
        abort_if($request->user()->is($user), 422, 'Bạn không thể xóa tài khoản đang đăng nhập.');
        $old = $user->load('roles')->toArray();
        $this->service->delete($user);
        $this->audit($request, 'DELETE', $user, $old);

        return response()->json(['success' => true, 'message' => 'Đã xóa người dùng.', 'data' => null]);
    }

    private function audit(Request $request, string $action, User $user, ?array $old = null): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action,
            'entity_type' => User::class, 'entity_id' => $user->id, 'old_values' => $old,
            'new_values' => $action === 'DELETE' ? null : $user->toArray(), 'ip_address' => $request->ip()]);
    }
}
