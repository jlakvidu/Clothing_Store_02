<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\SupplierProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Models\Inventory;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $products = Product::with('inventory', 'admin')->get();
            return $this->successResponse('Product retrieved successfully', $products);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
    public function show(string $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            return $this->successResponse('Product retrieved successfully', $product);
        } catch (Exception $e) {
            return $this->errorResponse('Product not found', 404);
        }
    }
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'supplier_id' => 'required|exists:suppliers,id',
                'seller_price' => 'required|numeric|min:0',
                'discount' => 'required|numeric|min:0',
                'price' => 'required|numeric|min:0',
                'brand_name' => 'required|string|max:255',
                'tax' => 'required|numeric|min:0',
                'size' => 'required|string',
                'color' => 'required|string',
                'description' => 'required|string',
                'bar_code' => 'required|string|unique:products',
                'inventory_id' => 'required|exists:inventories,id',
                'admin_id' => 'required|exists:admins,id',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors(), 422);
            }

            // Check if inventory exists
            $inventory = Inventory::find($request->inventory_id);
            if (!$inventory) {
                return $this->errorResponse('Invalid inventory ID. Please enter a valid inventory ID.', 404);
            }

            // Check for duplicate bar code
            if (Product::where('bar_code', $request->bar_code)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A product with this bar code already exists'
                ], 409);
            }

            DB::beginTransaction();

            // Calculate profit
            $profit = $request->price - $request->seller_price;
            
            // Create product with existing inventory ID
            $product = Product::create([
                'name' => $request->name,
                'price' => $request->price,
                'seller_price' => $request->seller_price,
                'profit' => $profit,
                'discount' => $request->discount ?? 0,
                'tax' => $request->tax,
                'size' => $request->size,
                'color' => $request->color,
                'description' => $request->description,
                'bar_code' => $request->bar_code,
                'brand_name' => $request->brand_name,
                'inventory_id' => $request->inventory_id,
                'supplier_id' => $request->supplier_id,
                'admin_id' => $request->admin_id
            ]);

            // Create supplier product relationship
            SupplierProduct::create([
                'product_id' => $product->id,
                'supplier_id' => $request->supplier_id
            ]);

            DB::commit();

            // Load the inventory relationship
            $product->load('inventory');

            return $this->successResponse('Product created successfully', $product, 201);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return $this->errorResponse('Product not found', 404);
            }
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'supplier' => 'sometimes|string|max:255',
                'price' => 'sometimes|numeric|min:0',
                'seller_price' => 'sometimes|numeric|min:0',
                'discount' => 'sometimes|numeric|min:0',
                'brand_name' => 'sometimes|string|max:255',
                'tax' => 'sometimes|numeric|min:0',
                'size' => 'sometimes|string',
                'color' => 'sometimes|string',
                'description' => 'sometimes|string',
                'bar_code' => 'sometimes|string|unique:products,bar_code,' . $product->id,
                'status' => 'sometimes|in:In Stock,Out Of Stock',
                'inventory_id' => 'sometimes|exists:inventories,id',
                'admin_id' => 'sometimes|exists:admins,id',
            ]);
            if ($validator->fails()) {
                return $this->errorResponse($validator->errors(), 422);
            }
            $profit = $request->price - $request->seller_price;
            $product->update($validator->validated() + ['profit' => $profit]);
            return $this->successResponse('Product updated successfully', $product);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to update product', 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return $this->errorResponse('Product not found', 404);
            }
            $product->delete();
            return $this->successResponse('Product deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Failed to delete product', 500);
        }
    }

    public function getByInventoryId($inventoryId)
    {
        try {
            $product = Product::with(['supplier' => function($query) {
                $query->select('id', 'name', 'email', 'contact'); // Add any other supplier fields you need
            }])
            ->where('inventory_id', $inventoryId)
            ->firstOrFail();

            // Format the product data with supplier details
            $formattedProduct = array_merge($product->toArray(), [
                'supplierDetails' => $product->supplier ? [
                    'name' => $product->supplier->name,
                    'email' => $product->supplier->email,
                    'contact' => $product->supplier->contact
                ] : null
            ]);

            return response()->json($formattedProduct);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No product found for this inventory ID'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch product: ' . $e->getMessage()
            ], 500);
        }
    }

    // response functions

    private function successResponse(string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    private function errorResponse(mixed $error, int $code): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $error,
        ], $code);
    }
}
