<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $product = Product::query()->with('category');

            if ($request->filled('category_id')) {
                $product->where('category_id', $request->category_id);
            }
            if ($request->filled('search')) {
                $product->where(
                    'name',
                    'like',
                    '%' . $request->search . '%'
                );
            }
            $products = $product->paginate(10);
            return response()->json([
                'success' => true,
                'message' => 'Get products successfully.',
                'data' => $products,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get products.',
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!$request->user()?->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Action forbidden.'
            ]);
        }
        $data = $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable'
        ]);
        try {
            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')
                    ->store('products', 'public');
            }

            $product = Product::create($data);
            $product->load('category');

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully.',
                'data' => $product,
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create product.',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        try {
            $product->with('categories');
            return response()->json([
                'success' => true,
                'message' => 'Get 1 product success',
                'data' => $product
            ], 200);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get 1 product.'
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        if (!$request->user()?->isAdmin()) {
            return $this->forbidden();
        }

        $data = $request->validate([
            'category_id' => 'sometimes|required|integer|exists:categories,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'image' => 'sometimes|nullable',
        ]);

        try {
            $oldImage = $product->image;
            $newImage = null;

            if ($request->hasFile('image')) {
                $newImage = $request->file('image')
                    ->store('products', 'public');

                $data['image'] = $newImage;
            }

            $product->update($data);

            if ($newImage && $oldImage) {
                Storage::disk('public')->delete($oldImage);
            }

            $product->load('category');

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully.',
                'data' => $product->fresh()->load('category'),
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update product.'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Product $product)
    {
        if (!$request->user()?->isAdmin()) {
            return $this->forbidden();
        }

        if ($product->orderItems()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a product used in an order.'
            ], 409);
        }

        try {
            $image = $product->image;
            $product->delete();

            if ($image) {
                Storage::disk('public')->delete($image);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully.',
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product.'
            ], 500);
        }
    }
    public function byCategory(Category $category)
    {
        try {
            $products = $category->products()
                ->with('category')
                ->orderBy('name')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Get category products successfully.',
                'data' => $products,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get category products.',
            ], 500);
        }
    }
    private function forbidden()
    {
        return response()->json([
            'success' => false,
            'message' => 'Only admins can manage products.',
        ], 403);
    }
}
