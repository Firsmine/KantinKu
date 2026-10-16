<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Broadcasting\Mercure\CachingTokenProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $categories = Category::query()->withCount('products')->orderBy('name')->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Get categories success',
                'data' => $categories
            ], 200);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get categories',
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
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string'
        ]);
        try {
            $category = Category::create($data);
            return response()->json([
                'success' => true,
                'message' => 'Create category success',
                'data' => $category
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category.'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        try {
            $category->loadCount('products');
            return response()->json([
                'success' => true,
                'message' => 'Get 1 category success',
                'data' => $category
            ], 200);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get 1 category.'
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        if (!$request->user()?->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Action forbidden.'
            ]);
        }
        $data = $request->validate([
            'name' => ['string|required|max:255', Rule::unique('categories', 'name')->ignore($category->id)],
            'description' => 'nullable|string'
        ]);
        try {
            $category->update($data);
            return response()->json([
                'success' => true,
                'message' => 'Update category success',
                'data' => $category
            ], 201);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Category $category)
    {
        if (!$request->user()?->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Action forbidden.'
            ]);
        }
        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a category that has products'
            ], 409);
        }
        try {
            $category->delete();
            return response()->json([
                'success' => true,
                'message' => 'Delete category success',
            ], 200);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete a category.'
            ]);
        }
    }
}
