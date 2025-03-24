<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\SupplierProduct;
use Illuminate\Validation\ValidationException;
use Exception;
use Throwable;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index()
    {
        try {
            $inventories = Inventory::with(['product' => function($query) {
                $query->select('id', 'inventory_id', 'name', 'price', 'brand_name', 'profit', 'seller_price');
            }])->get();

            $formattedInventories = $inventories->map(function ($inventory) {
                $status = $this->determineStatus($inventory->quantity);
                return [
                    'id' => $inventory->id,
                    'quantity' => $inventory->quantity,
                    'restock_date_time' => $inventory->restock_date_time,
                    'added_stock_amount' => $inventory->added_stock_amount,
                    'location' => $inventory->location,
                    'status' => $status,
                    'name' => $inventory->product ? $inventory->product->name : null,
                    'price' => $inventory->product ? $inventory->product->price : 0,
                    'profit' => $inventory->product ? $inventory->product->profit : 0,
                    'brand_name' => $inventory->product ? $inventory->product->brand_name : null,
                    'total_value' => $inventory->product ? ($inventory->quantity * $inventory->product->price) : 0,
                    'total_profit' => $inventory->product ? ($inventory->quantity * $inventory->product->profit) : 0,
                ];
            });

            return response()->json($formattedInventories);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve inventory', 
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function store(Request $request)
    {
        try {
            $request->validate([
                'quantity' => 'required|numeric',
                'location' => 'required|string',
                'status' => 'required|string|in:In Stock,Low Stock,Out Of Stock',
            ]);

            $inventory = new Inventory();
            $inventory->quantity = $request->input('quantity');
            $inventory->restock_date_time = now();
            $inventory->added_stock_amount = $request->input('added_stock_amount', $inventory->quantity);
            $inventory->location = $request->input('location');
            $inventory->status = $request->input('status'); // Use the exact status from frontend
            $inventory->save();
            $this->updateStatus();
            return response()->json($inventory);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Validation failed', 'messages' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create inventory', 'message' => $e->getMessage()], 500);
        }
    }
    public function show($id)
    {
        try {
            $inventory = Inventory::findOrFail($id);
            return response()->json($inventory);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Inventory not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to retrieve inventory', 'message' => $e->getMessage()], 500);
        }
    }
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $inventory = Inventory::findOrFail($id);

            $validated = $request->validate([
                'quantity' => 'required|numeric|min:0',
                'location' => 'required|string',
                'status' => 'required|string|in:In Stock,Low Stock,Out Of Stock',
                'added_stock_amount' => 'numeric|min:0',
                'restock_date_time' => 'required|date',
            ]);

            $inventory->quantity = $validated['quantity'];
            $inventory->location = $validated['location'];
            $inventory->status = $validated['status'];
            
            // Update restock info only if new stock is added
            if (!empty($validated['added_stock_amount']) && $validated['added_stock_amount'] > 0) {
                $inventory->restock_date_time = $validated['restock_date_time'];
                $inventory->added_stock_amount = $validated['added_stock_amount'];
            }

            $inventory->save();
            
            DB::commit();
            return response()->json($inventory);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Inventory not found'], 404);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update inventory: ' . $e->getMessage()], 500);
        }
    }
    public function destroy($id)
    {
        try {
            $inventory = Inventory::findOrFail($id);
            $inventory->delete();
            return response()->json(['message' => 'Inventory deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Inventory not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to delete inventory', 'message' => $e->getMessage()], 500);
        }
    }

    public function lowStock()
    {
        try {
            $supplierList = collect();
            $inventories = Inventory::where('quantity', '<', 20)->get();
            $inventoryData = $inventories->keyBy('id');
            $products = Product::whereIn('inventory_id', $inventories->pluck('id'))->get()->keyBy('id');
            if ($products->isNotEmpty()) {
                $supplierProduct = SupplierProduct::whereIn('product_id', $products->keys())
                    ->with('supplier', 'product')
                    ->get();
                foreach ($supplierProduct as $item) {
                    $inventory = $inventoryData->get($item->product->inventory_id);
                    $lowStockDetail = new LowStockDetail(
                        $item->supplier->id,
                        $item->product->id,
                        $item->supplier->name,
                        $item->supplier->email,
                        $item->product->name,
                        $inventory->quantity,
                        $inventory->location,
                        $inventory->status
                    );
                    $supplierList->push($lowStockDetail);
                }
            }
            return response()->json($supplierList);
        } catch (Exception | Throwable $e) {
            return response()->json([
                'error' => 'Failed to retrieve low stock inventory',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function outOfStock()
    {
        try {
            $inventories = Inventory::where('quantity', 0)->get();
            return response()->json($inventories);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to retrieve out of stock inventory', 'message' => $e->getMessage()], 500);
        }
    }

    public function inStock()
    {
        try {
            $inventories = Inventory::where('quantity', '>', 0)->get();
            return response()->json($inventories);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to retrieve in stock inventory', 'message' => $e->getMessage()], 500);
        }
    }
    public function updateStatus(){
        $inventories = Inventory::all();
        $inventories->map(function ($inventory) {
            if ($inventory->quantity == 0) {
                $inventory->status = 'Out Of Stock';
                $inventory->save();
            } elseif ($inventory->quantity < 5) {
                $inventory->status = 'Low Stock';
                $inventory->save();
            } else {
                $inventory->status = 'In Stock';
                $inventory->save();
            }
        });
    }
    private function determineStatus($quantity)
    {
        if ($quantity == 0) {
            return 'Out Of Stock';
        } elseif ($quantity < 20) {
            return 'Low Stock';
        }
        return 'In Stock';
    }

    public function exportData()
    {
        try {
            $inventories = Inventory::with(['product' => function($query) {
                $query->select(
                    'id',
                    'inventory_id',
                    'supplier_id',
                    'admin_id',
                    'name',
                    'brand_name',
                    'description',
                    'bar_code',
                    'size',
                    'color',
                    'price',
                    'seller_price',
                    'discount',
                    'tax',
                    'profit'
                );
            }])->get();

            $totalValue = 0;
            $totalProfit = 0;

            $formattedData = $inventories->map(function ($inventory) use (&$totalValue, &$totalProfit) {
                $status = $this->determineStatus($inventory->quantity);
                $itemValue = ($inventory->product?->price ?? 0) * $inventory->quantity;
                $itemProfit = ($inventory->product?->profit ?? 0) * $inventory->quantity;
                
                $totalValue += $itemValue;
                $totalProfit += $itemProfit;

                return [
                    'id' => $inventory->id,
                    'product' => [
                        'id' => $inventory->product?->id,
                        'name' => $inventory->product?->name,
                        'brand_name' => $inventory->product?->brand_name,
                        'description' => $inventory->product?->description,
                        'bar_code' => $inventory->product?->bar_code,
                        'size' => $inventory->product?->size,
                        'color' => $inventory->product?->color,
                        'price' => $inventory->product?->price,
                        'seller_price' => $inventory->product?->seller_price,
                        'discount' => $inventory->product?->discount,
                        'tax' => $inventory->product?->tax,
                        'profit' => $inventory->product?->profit,
                        'supplier_id' => $inventory->product?->supplier_id,
                        'admin_id' => $inventory->product?->admin_id,
                    ],
                    'quantity' => $inventory->quantity,
                    'location' => $inventory->location,
                    'status' => $status,
                    'added_stock_amount' => $inventory->added_stock_amount,
                    'restock_date_time' => $inventory->restock_date_time,
                    'created_at' => $inventory->created_at,
                    'updated_at' => $inventory->updated_at,
                    'total_value' => $itemValue,
                    'total_profit' => $itemProfit
                ];
            });

            return response()->json([
                'data' => $formattedData,
                'summary' => [
                    'total_value' => $totalValue,
                    'total_profit' => $totalProfit
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to export inventory data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
class LowStockDetail
{
    public $supplier_id;
    public $product_id;
    public $supplier_name;
    public $supplier_email;
    public $product_name;
    public $quantity;
    public $location;
    public $status;
    public function __construct($supplier_id, $product_id, $supplier_name, $supplier_email, $product_name, $quantity, $location, $status)
    {
        $this->supplier_id = $supplier_id;
        $this->product_id = $product_id;
        $this->supplier_name = $supplier_name;
        $this->supplier_email = $supplier_email;
        $this->product_name = $product_name;
        $this->quantity = $quantity;
        $this->location = $location;
        $this->status = $status;
    }
}
