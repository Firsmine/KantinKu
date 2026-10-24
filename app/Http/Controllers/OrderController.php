<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $order = Order::query()->with(['user:id,name,username', 'items.product.category']);

            if (!$request->user()->isAdmin()) {
                $order->where('user_id', $request->user()->id);
            }

            $orders = $order->latest()->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Get orders successful.',
                'data' => $orders
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get orders.',
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
        if ($request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Admins cannot checkout as customers.',
                'errors' => ['code' => 'FORBIDDEN'],
            ], 403);
        }

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|distinct|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        try {
            $order = DB::transaction(function () use ($request, $data) {
                $preparedItems = [];
                $totalCents = 0;

                foreach ($data['items'] as $item) {
                    $product = Product::query()
                        ->lockForUpdate()
                        ->findOrFail($item['product_id']);
                    $quantity = $item['quantity'];
                    if ($product->stock < $quantity) {
                        throw ValidationException::withMessages([
                            'items' => [
                                "Insufficient stock for {$product->name}.",
                            ],
                        ]);
                    }
                    $priceCents = (int) round(
                        (float) $product->price * 100
                    );
                    $subtotalCents = $priceCents * $quantity;
                    $totalCents += $subtotalCents;
                    $preparedItems[] = [
                        'product' => $product,
                        'quantity' => $quantity,
                        'price_cents' => $priceCents,
                        'subtotal_cents' => $subtotalCents,
                    ];
                }
                $order = $request->user()->orders()->create([
                    'total_price' => $totalCents / 100,
                    'status' => 'pending',
                ]);

                foreach ($preparedItems as $item) {
                    $order->items()->create([
                        'product_id' => $item['product']->id,
                        'quantity' => $item['quantity'],
                        'price' => $item['price_cents'] / 100,
                        'subtotal' => $item['subtotal_cents'] / 100,
                    ]);

                    $item['product']->decrement(
                        'stock',
                        $item['quantity']
                    );
                }
                return $order;
            });

            $order->load(['items.product.category']);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully.',
                'data' => $order,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Checkout validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order.'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Order $order)
    {
        if (
            !$request->user()->isAdmin()
            && $order->user_id !== $request->user()->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden'
            ], 403);
        }
        try {
            $order->load([
                'user:id,name,username',
                'items.product.category',
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Get order successful.',
                'data' => $order,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to show an order.'
            ], 500);
        }
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        if (!$isAdmin && $order->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.'
            ], 403);
        }
        $data = $request->validate([
            'status' => [
                'required',
                'in:pending,processing,completed,cancelled',
            ],
        ]);
        $newStatus = $data['status'];

        if (!$isAdmin) {
            if (
                $order->status !== 'pending'
                || $newStatus !== 'cancelled'
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customers can only cancel pending orders.',
                    'errors' => ['code' => 'INVALID_STATUS_CHANGE'],
                ], 422);
            }
        } else {
            $allowed = match ($order->status) {
                'pending' => ['processing', 'cancelled'],
                'processing' => ['completed'],
                default => [],
            };

            if (!in_array($newStatus, $allowed, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This status transition is not allowed.',
                ], 422);
            }
        }
        try {
            DB::transaction(function () use ($order, $newStatus) {

                $lockedOrder = Order::query()
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                if ($lockedOrder->status !== $order->status) {
                    throw ValidationException::withMessages([
                        'status' => ['The order status has changed. Reload and try again.'],
                    ]);
                }

                if ($newStatus === 'cancelled') {
                    foreach ($lockedOrder->items as $item) {
                        Product::query()
                            ->whereKey($item->product_id)
                            ->lockForUpdate()
                            ->firstOrFail()
                            ->increment('stock', $item->quantity);
                    }
                }
                $lockedOrder->update(['status' => $newStatus]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully.',
                'data' => $order->fresh()->load('items.product'),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not update order status.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status.'
            ], 500);
        }
    }

    public function myOrders(Request $request)
    {
        try {
            $orders = $request->user()
                ->orders()
                ->with('items.product.category')
                ->latest()
                ->paginate(10);
            return response()->json([
                'success' => true,
                'message' => 'Get your orders successfully.',
                'data' => $orders,
            ], 200);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get your orders.'
            ], 500);
        }
    }

    public function adminIndex(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.'
            ], 403);
        }
        $data = $request->validate([
            'status' => [
                'sometimes',
                'in:pending,processing,completed,cancelled',
            ],
        ]);
        try {
            $order = Order::query()
                ->with(['user:id,name,username', 'items.product']);

            if (isset($data['status'])) {
                $query->where('status', $data['status']);
            }
            $orders = $order->latest()->paginate(10);
            return response()->json([
                'success' => true,
                'message' => 'Get all orders successfully.',
                'data' => $orders,
            ], 200);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get all orders.'
            ], 500);
        }
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }
}
