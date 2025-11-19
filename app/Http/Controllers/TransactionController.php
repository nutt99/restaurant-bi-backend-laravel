<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\Menu;
use App\Models\User;

class TransactionController extends Controller
{
    // GET /api/transactions
    public function index()
    {
        $transactions = Transaction::with(['user', 'items.menu'])
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    // GET /api/transactions/{id}
    public function show($id)
    {
        $transaction = Transaction::with(['user', 'items.menu'])->find($id);

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $transaction]);
    }

    // POST /api/transactions
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',      
            'items.*.menu_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                
                $menuIds = collect($validated['items'])->pluck('menu_id');
                $menus = Menu::whereIn('id', $menuIds)->get();

                $total = 0;
                $transactionItemsData = [];

                foreach ($validated['items'] as $item) {
                    $menu = $menus->firstWhere('id', $item['menu_id']);
                    
                    $subtotal = $menu->price * $item['quantity'];
                    $total += $subtotal;

                    $transactionItemsData[] = [
                        'menu_id' => $item['menu_id'],
                        'quantity' => $item['quantity'],
                        'subtotal' => $subtotal,
                    ];
                }

                $transaction = Transaction::create([
                    'user_id' => $validated['user_id'],
                    'total' => $total,
                    'date' => now()
                ]);

                $transaction->items()->createMany($transactionItemsData);

                $transaction->load('items.menu');

                return response()->json(['success' => true, 'data' => $transaction], 201);
            });

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to create transaction: ' . $e->getMessage()], 500);
        }
    }

    // DELETE /api/transactions/{id}
    public function destroy($id)
    {
        $transaction = Transaction::find($id);

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        $transaction->delete();

        return response()->json(['success' => true, 'message' => 'Transaction deleted successfully']);
    }
}