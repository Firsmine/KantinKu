<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Throwable;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $product = Product::query()->with('category')->orderBy('name')->paginate(10);

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
            return response()->json([
                'success' => true,
                'message' => 'Get products successfully.',
                'data' => $query->orderBy('name')->paginate(10),
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
                'errors' => ['code' => 'INTERNAL_SERVER_ERROR'],
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        try {
            $product->loadCount('categories');
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        //
    }
}
