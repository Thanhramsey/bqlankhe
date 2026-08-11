<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Material;
use App\Models\StockDocument;
use App\Models\StockTransaction;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function createTransaction(array $data, Request $request): StockTransaction
    {
        return DB::transaction(function () use ($data, $request) {
            $type = $data['type'];
            $transaction = StockTransaction::create([
                'warehouse_id' => $data['warehouse_id'], 'created_by' => $request->user()->id,
                'code' => $data['code'] ?? $this->nextCode($type), 'type' => $type,
                'transaction_date' => $data['transaction_date'], 'partner' => $data['partner'] ?? null,
                'reference_no' => $data['reference_no'] ?? null, 'note' => $data['note'] ?? null,
                'status' => 'COMPLETED', 'total_amount' => 0,
            ]);
            $total = 0;
            foreach ($data['items'] as $row) {
                $material = Material::findOrFail($row['material_id']);
                $quantity = round((float) $row['quantity'], 3);
                $price = round((float) ($row['unit_price'] ?? $material->price), 2);
                $stock = WarehouseStock::query()->lockForUpdate()->firstOrCreate(
                    ['warehouse_id' => $data['warehouse_id'], 'material_id' => $material->id],
                    ['quantity' => 0, 'average_price' => 0]
                );
                $oldQty = (float) $stock->quantity;
                if ($type === 'OUT' && $oldQty < $quantity) {
                    throw ValidationException::withMessages(['items' => "Vật tư {$material->name} chỉ còn {$oldQty} {$material->unit} trong kho."]);
                }
                if ($type === 'IN') {
                    $newQty = $oldQty + $quantity;
                    $stock->average_price = $newQty > 0 ? (($oldQty * (float) $stock->average_price) + ($quantity * $price)) / $newQty : 0;
                    $stock->quantity = $newQty;
                    $material->update(['price' => $price]);
                } else {
                    $stock->quantity = $oldQty - $quantity;
                }
                $stock->save();
                $amount = round($quantity * $price, 2); $total += $amount;
                $transaction->items()->create(['material_id' => $material->id, 'quantity' => $quantity, 'unit_price' => $price, 'amount' => $amount, 'note' => $row['note'] ?? null]);
            }
            foreach ($request->file('documents', []) as $file) {
                $path = $file->store('inventory/documents/'.now()->format('Y/m'), 'public');
                StockDocument::create(['stock_transaction_id' => $transaction->id, 'uploaded_by' => $request->user()->id, 'original_name' => $file->getClientOriginalName(), 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
            }
            $transaction->update(['total_amount' => $total]);
            AuditLog::create(['user_id'=>$request->user()->id,'action'=>$type==='IN'?'STOCK_IN':'STOCK_OUT','entity_type'=>StockTransaction::class,'entity_id'=>$transaction->id,'new_values'=>$transaction->load('items')->toArray(),'ip_address'=>$request->ip()]);
            return $transaction->load(['warehouse:id,code,name','creator:id,name','items.material:id,code,name,unit','documents']);
        });
    }

    public function updateTransaction(StockTransaction $transaction, array $data, Request $request): StockTransaction
    {
        return DB::transaction(function () use ($transaction, $data, $request) {
            $oldWarehouseId = (int) $transaction->warehouse_id;
            $oldMaterialIds = $transaction->items()->pluck('material_id')->map(fn ($id) => (int) $id)->all();
            $oldValues = $transaction->load('items')->toArray();

            $type = $data['type'];
            $transaction->update([
                'warehouse_id' => $data['warehouse_id'],
                'code' => $data['code'] ?? $transaction->code,
                'type' => $type,
                'transaction_date' => $data['transaction_date'],
                'partner' => $data['partner'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $transaction->items()->delete();
            $total = 0;
            foreach ($data['items'] as $row) {
                $material = Material::findOrFail($row['material_id']);
                $quantity = round((float) $row['quantity'], 3);
                $price = round((float) ($row['unit_price'] ?? $material->price), 2);
                if ($type === 'IN') $material->update(['price' => $price]);
                $amount = round($quantity * $price, 2);
                $total += $amount;
                $transaction->items()->create([
                    'material_id' => $material->id,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'amount' => $amount,
                    'note' => $row['note'] ?? null,
                ]);
            }

            foreach ($request->file('documents', []) as $file) {
                $path = $file->store('inventory/documents/'.now()->format('Y/m'), 'public');
                StockDocument::create([
                    'stock_transaction_id' => $transaction->id,
                    'uploaded_by' => $request->user()->id,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            $transaction->update(['total_amount' => $total]);

            $newWarehouseId = (int) $transaction->warehouse_id;
            $newMaterialIds = array_values(array_unique(array_map(fn ($row) => (int) $row['material_id'], $data['items'])));

            if ($newWarehouseId !== $oldWarehouseId) {
                $this->rebuildStocks($oldWarehouseId, $oldMaterialIds);
                $this->rebuildStocks($newWarehouseId, $newMaterialIds);
            } else {
                $this->rebuildStocks($newWarehouseId, array_values(array_unique(array_merge($oldMaterialIds, $newMaterialIds))));
            }

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'UPDATE',
                'entity_type' => StockTransaction::class,
                'entity_id' => $transaction->id,
                'old_values' => $oldValues,
                'new_values' => $transaction->load('items')->toArray(),
                'ip_address' => $request->ip(),
            ]);

            return $transaction->load(['warehouse:id,code,name','creator:id,name','items.material:id,code,name,unit','documents']);
        });
    }

    public function deleteTransaction(StockTransaction $transaction, Request $request): void
    {
        DB::transaction(function () use ($transaction, $request) {
            $warehouseId = (int) $transaction->warehouse_id;
            $materialIds = $transaction->items()->pluck('material_id')->map(fn ($id) => (int) $id)->all();
            $oldValues = $transaction->load(['items', 'documents'])->toArray();

            foreach ($transaction->documents as $document) {
                if ($document->path) Storage::disk('public')->delete($document->path);
            }
            $transaction->documents()->delete();
            $transaction->items()->delete();
            $transaction->delete();

            $this->rebuildStocks($warehouseId, $materialIds);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'DELETE',
                'entity_type' => StockTransaction::class,
                'entity_id' => $transaction->id,
                'old_values' => $oldValues,
                'new_values' => null,
                'ip_address' => $request->ip(),
            ]);
        });
    }

    private function rebuildStocks(int $warehouseId, array $materialIds): void
    {
        $materialIds = array_values(array_unique(array_filter(array_map('intval', $materialIds))));
        if (!$materialIds) return;

        WarehouseStock::query()->where('warehouse_id', $warehouseId)->whereIn('material_id', $materialIds)->delete();

        $state = [];
        foreach ($materialIds as $materialId) $state[$materialId] = ['quantity' => 0.0, 'average_price' => 0.0];

        $transactions = StockTransaction::query()
            ->with(['items' => fn ($query) => $query->whereIn('material_id', $materialIds)])
            ->where('status', 'COMPLETED')
            ->where('warehouse_id', $warehouseId)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        foreach ($transactions as $transaction) {
            foreach ($transaction->items as $item) {
                $materialId = (int) $item->material_id;
                if (!isset($state[$materialId])) $state[$materialId] = ['quantity' => 0.0, 'average_price' => 0.0];

                $qty = (float) $item->quantity;
                $price = (float) $item->unit_price;

                if ($transaction->type === 'IN') {
                    $oldQty = $state[$materialId]['quantity'];
                    $newQty = $oldQty + $qty;
                    $state[$materialId]['average_price'] = $newQty > 0
                        ? (($oldQty * $state[$materialId]['average_price']) + ($qty * $price)) / $newQty
                        : 0;
                    $state[$materialId]['quantity'] = $newQty;
                } else {
                    if ($state[$materialId]['quantity'] < $qty) {
                        throw ValidationException::withMessages([
                            'items' => 'Không thể cập nhật phiếu vì dữ liệu tồn kho không hợp lệ sau khi chỉnh sửa.',
                        ]);
                    }
                    $state[$materialId]['quantity'] -= $qty;
                }
            }
        }

        $now = now();
        $rows = [];
        foreach ($state as $materialId => $values) {
            if ($values['quantity'] <= 0) continue;
            $rows[] = [
                'warehouse_id' => $warehouseId,
                'material_id' => $materialId,
                'quantity' => round($values['quantity'], 3),
                'average_price' => round($values['average_price'], 2),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) WarehouseStock::query()->insert($rows);
    }

    private function nextCode(string $type): string
    {
        $prefix = $type === 'IN' ? 'PN' : 'PX';
        return $prefix.now()->format('YmdHis').str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
    }
}
