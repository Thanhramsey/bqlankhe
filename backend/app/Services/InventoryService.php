<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Material;
use App\Models\StockDocument;
use App\Models\StockTransaction;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    private function nextCode(string $type): string
    {
        $prefix = $type === 'IN' ? 'PN' : 'PX';
        return $prefix.now()->format('YmdHis').str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
    }
}
