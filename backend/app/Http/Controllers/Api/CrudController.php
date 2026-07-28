<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CollectionRoute;
use App\Models\Employee;
use App\Models\Household;
use App\Models\HouseholdService;
use App\Models\Neighborhood;
use App\Models\Province;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrudController extends Controller
{
    private array $map = ['users' => User::class, 'provinces' => Province::class, 'wards' => Ward::class, 'neighborhoods' => Neighborhood::class, 'routes' => CollectionRoute::class, 'employees' => Employee::class, 'households' => Household::class, 'services' => Service::class, 'household-services' => HouseholdService::class, 'settings' => SystemSetting::class];

    private function model(string $resource): Model
    {
        abort_unless(isset($this->map[$resource]), 404);

        return new ($this->map[$resource]);
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        $this->authorizeResource($request, $resource, 'view');
        $model = $this->model($resource);
        $q = $model->newQuery();
        if ($request->boolean('with_deleted') && in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model))) $q->withTrashed();
        if ($request->filled('search')) {
            $term = '%'.$request->search.'%';
            $q->where(function ($x) use ($term, $model) {
                foreach (array_intersect($model->getFillable(), ['code', 'name', 'owner_name', 'address', 'email', 'phone']) as $col) {
                    $x->orWhere($col, 'like', $term);
                }
            });
        } if ($resource === 'households') {
            $q->with(['route', 'services.service']);
        } elseif ($resource === 'wards') { $q->with('province:id,code,name');
        } elseif ($resource === 'neighborhoods') { $q->with('ward.province:id,code,name');
        } elseif ($resource === 'routes') { $q->with(['neighborhood.ward.province', 'users:id,username,name']);
        } elseif ($resource === 'employees') {
            $q->with(['route', 'routes:id,code,name']);
        }$data = $q->latest('id')->paginate(min((int) $request->input('per_page', 15), 100));

        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => $data]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $this->authorizeResource($request, $resource, 'create');
        $model = $this->model($resource);
        $data = $this->validateData($request, $resource);
        if ($resource === 'users') {
            $data['password'] = $data['password'] ?? 'ChangeMe123!';
        }
        $userIds = $data['user_ids'] ?? null; unset($data['user_ids']);
        $routeIds = $data['route_ids'] ?? null; unset($data['route_ids']);
        $item = $model->newQuery()->create($data);
        if ($resource === 'routes') $item->users()->sync($userIds ?? []);
        if ($resource === 'employees' && $routeIds !== null) $item->routes()->sync($routeIds);
        $this->log($request, 'CREATE', $item);

        return response()->json(['success' => true, 'message' => 'Đã tạo dữ liệu.', 'data' => $item], 201);
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        $this->authorizeResource($request, $resource, 'update');
        $item = $this->model($resource)->newQuery()->findOrFail($id);
        $old = $item->toArray();
        $data = $this->validateData($request, $resource, $id);
        if ($resource === 'users' && empty($data['password'])) {
            unset($data['password']);
        }
        $userIds = $data['user_ids'] ?? null; unset($data['user_ids']);
        $routeIds = $data['route_ids'] ?? null; unset($data['route_ids']);
        $item->update($data);
        if ($resource === 'routes' && $userIds !== null) $item->users()->sync($userIds);
        if ($resource === 'employees' && $routeIds !== null) $item->routes()->sync($routeIds);
        $this->log($request, 'UPDATE', $item, $old);

        return response()->json(['success' => true, 'message' => 'Đã cập nhật dữ liệu.', 'data' => $item]);
    }

    public function destroy(Request $request, string $resource, int $id): JsonResponse
    {
        $this->authorizeResource($request, $resource, 'delete');
        $item = $this->model($resource)->newQuery()->findOrFail($id);
        $this->log($request, 'DELETE', $item, $item->toArray());
        $item->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa dữ liệu.', 'data' => null]);
    }

    public function restore(Request $request, string $resource, int $id): JsonResponse
    {
        $this->authorizeResource($request, $resource, 'update');
        $item = $this->model($resource)->newQuery()->withTrashed()->findOrFail($id);
        abort_unless(method_exists($item, 'restore'), 422, 'Dữ liệu này không hỗ trợ khôi phục.');
        $item->restore(); $this->log($request, 'RESTORE', $item);
        return response()->json(['success' => true, 'message' => 'Đã khôi phục dữ liệu.', 'data' => $item]);
    }

    private function validateData(Request $r, string $resource, ?int $id = null): array
    {
        return match ($resource) {
            'provinces' => $r->validate(['code' => ['required','max:20',Rule::unique('provinces')->ignore($id)], 'name' => 'required|max:255']),
            'wards' => $r->validate(['province_id' => 'required|exists:provinces,id', 'code' => ['required','max:20',Rule::unique('wards')->where('province_id',$r->province_id)->ignore($id)], 'name' => 'required|max:255']),
            'neighborhoods' => $r->validate(['ward_id' => 'required|exists:wards,id', 'code' => ['required','max:30',Rule::unique('neighborhoods')->where('ward_id',$r->ward_id)->ignore($id)], 'name' => 'required|max:255']),
            'users' => $r->validate(['name' => 'required|string|max:255', 'email' => ['required', 'email', Rule::unique('users')->ignore($id)], 'phone' => 'nullable|string|max:20', 'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'], 'is_active' => 'boolean']),
            'routes' => $r->validate(['neighborhood_id' => 'required|exists:neighborhoods,id', 'code' => ['required', 'max:50', Rule::unique('collection_routes')->ignore($id)], 'name' => 'required|max:255', 'description' => 'nullable|string', 'user_ids' => 'nullable|array', 'user_ids.*' => 'exists:users,id', 'is_active' => 'boolean']),
            'employees' => $r->validate(['code' => ['required', 'max:50', Rule::unique('employees')->ignore($id)], 'name' => 'required|max:255', 'phone' => 'nullable|max:20', 'address' => 'nullable|max:255', 'collection_route_id' => 'nullable|exists:collection_routes,id', 'route_ids' => 'nullable|array', 'route_ids.*' => 'exists:collection_routes,id', 'user_id' => 'nullable|exists:users,id', 'is_active' => 'boolean']),
            'households' => $r->validate(['code' => ['required', 'max:50', Rule::unique('households')->ignore($id)], 'owner_name' => 'required|max:255', 'phone' => 'nullable|max:20', 'address' => 'required|max:255', 'ward' => 'required|max:100', 'note' => 'nullable|string', 'collection_route_id' => 'nullable|exists:collection_routes,id', 'is_active' => 'boolean']),
            'services' => $r->validate(['code' => ['required', 'max:50', Rule::unique('services')->ignore($id)], 'name' => 'required|max:255', 'monthly_price' => 'required|numeric|min:0', 'description' => 'nullable|string', 'is_active' => 'boolean']),
            'household-services' => $r->validate(['household_id' => 'required|exists:households,id', 'service_id' => 'required|exists:services,id', 'monthly_price' => 'required|numeric|min:0', 'started_at' => 'required|date', 'ended_at' => 'nullable|date|after_or_equal:started_at', 'is_active' => 'boolean']),
            'settings' => $r->validate(['key' => ['required', 'max:100', Rule::unique('system_settings')->ignore($id)], 'value' => 'nullable|string', 'type' => 'required|in:string,number,boolean,json', 'group' => 'required|max:100', 'description' => 'nullable|max:255']),default => []
        };
    }

    private function authorizeResource(Request $request, string $resource, string $action): void
    {
        $module = match ($resource) {
            'household-services' => 'households',
            'provinces', 'wards', 'neighborhoods' => 'routes',
            default => $resource,
        };
        $user = $request->user();
        abort_unless(
            $user->hasPermission($module.'.manage') || $user->hasPermission($module.'.'.$action),
            403,
            'Bạn không có quyền thực hiện thao tác này.'
        );
    }

    private function log(Request $r, string $action, Model $item, ?array $old = null): void
    {
        AuditLog::create(['user_id' => $r->user()->id, 'action' => $action, 'entity_type' => $item::class, 'entity_id' => $item->getKey(), 'old_values' => $old, 'new_values' => $action === 'DELETE' ? null : $item->toArray(), 'ip_address' => $r->ip()]);
    }
}
