<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CollectionRoute;
use App\Models\Employee;
use App\Models\Household;
use App\Models\HouseholdService;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrudController extends Controller
{
    private array $map = ['users' => User::class, 'routes' => CollectionRoute::class, 'employees' => Employee::class, 'households' => Household::class, 'services' => Service::class, 'household-services' => HouseholdService::class, 'settings' => SystemSetting::class];

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
        if ($request->filled('search')) {
            $term = '%'.$request->search.'%';
            $q->where(function ($x) use ($term, $model) {
                foreach (array_intersect($model->getFillable(), ['code', 'name', 'owner_name', 'address', 'email', 'phone']) as $col) {
                    $x->orWhere($col, 'like', $term);
                }
            });
        } if ($resource === 'households') {
            $q->with(['route', 'services.service']);
        }if ($resource === 'employees') {
            $q->with('route');
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
        }$item = $model->newQuery()->create($data);
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
        }$item->update($data);
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

    private function validateData(Request $r, string $resource, ?int $id = null): array
    {
        return match ($resource) {
            'users' => $r->validate(['name' => 'required|string|max:255', 'email' => ['required', 'email', Rule::unique('users')->ignore($id)], 'phone' => 'nullable|string|max:20', 'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'], 'is_active' => 'boolean']),
            'routes' => $r->validate(['code' => ['required', 'max:50', Rule::unique('collection_routes')->ignore($id)], 'name' => 'required|max:255', 'description' => 'nullable|string', 'is_active' => 'boolean']),
            'employees' => $r->validate(['code' => ['required', 'max:50', Rule::unique('employees')->ignore($id)], 'name' => 'required|max:255', 'phone' => 'nullable|max:20', 'address' => 'nullable|max:255', 'collection_route_id' => 'nullable|exists:collection_routes,id', 'user_id' => 'nullable|exists:users,id', 'is_active' => 'boolean']),
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
