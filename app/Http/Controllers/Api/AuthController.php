<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        // Jalankan fungsi seed terpisah
        $this->seedInitialUserData($user);

        return response()->json([
            'message' => 'Register berhasil.',
            'user' => $user,
        ], 201);
    }

    private function seedInitialUserData(User $user): void
    {
        DB::transaction(function () use ($user) {
            $initialData = [
                ['category' => 'Kebutuhan Rumah Tangga', 'product' => 'Sabun cuci piring', 'price' => 15000, 'stock' => 12],
                ['category' => 'Makanan', 'product' => 'Mi instant', 'price' => 3000, 'stock' => 40], // 1 dus mi instan
                ['category' => 'Minuman', 'product' => 'Air mineral 600ml', 'price' => 3500, 'stock' => 24], // 1 dus air mineral
                ['category' => 'Sembako', 'product' => 'Beras 5 kg', 'price' => 70000, 'stock' => 5], // Cukup 5 karung
            ];

            // 1. Buat Transaksi Induk
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'trx_type' => 'PURCHASE',
                'trx_date' => now(),
                'payment_method' => 'CASH',
                'paid_at' => now(),
                'total_amount' => 0,
            ]);

            $totalAmount = 0;

            foreach ($initialData as $data) {
                // 2. Buat Kategori
                $category = Category::create([
                    'name' => $data['category'],
                    'isActive' => true,
                    'user_id' => $user->id,
                ]);

                // 3. Buat Produk 
                $product = Product::create([
                    'name' => $data['product'],
                    'price' => $data['price'],
                    'description' => $data['product'] . ' adalah produk contoh.',
                    'image_url' => 'https://placehold.co/400x400?text=' . urlencode($data['product']),
                    'category_id' => $category->id,
                    'is_active' => true,
                    'user_id' => $user->id,
                ]);

                // 4. Tambah Stok
                $stokAwal = $data['stock'];
                $product->stocks()->create([
                    'stock_on_hand' => $stokAwal,
                ]);

                // 5. Catat Item Transaksi
                $linePrice = $stokAwal * $product->price;
                $totalAmount += $linePrice;

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'quantity' => $stokAwal,
                    'unit_price' => $product->price,
                    'line_price' => $linePrice,
                ]);
            }

            // 6. Update Total Amount Transaksi
            $transaction->update(['total_amount' => $totalAmount]);
        });
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }
    
    public function checkSession(Request $request): JsonResponse
    {
        if ($request->user()) {
            return response()->json([
                'message' => 'Session valid.',
                'user' => $request->user(),
            ]);
        } else {
            return response()->json([
                'message' => 'Session tidak valid.',
            ], 401);
        }
    }
}
