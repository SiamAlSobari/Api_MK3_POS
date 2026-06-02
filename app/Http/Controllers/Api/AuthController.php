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
                [
                    'category' => 'Makanan',
                    'product' => 'Mie goreng aceh',
                    'price' => 3500,
                    'stock' => 40,
                    'image_url' => 'https://img.lazcdn.com/g/ff/kf/Seacb142468aa4280ae9cacb76d09eba5j.jpg_720x720q80.jpg'
                ],
                [
                    'category' => 'Kebutuhan Rumah Tangga',
                    'product' => 'Rinso',
                    'price' => 5500,
                    'stock' => 12,
                    'image_url' => 'https://filebroker-cdn.lazada.co.id/kf/S28abc871c92847f2ada9a633b5a9102dJ.jpg'
                ],
                [
                    'category' => 'Sembako',
                    'product' => 'Gas melon 3kg',
                    'price' => 20000,
                    'stock' => 5,
                    'image_url' => 'https://transisienergi.id/wp-content/uploads/2025/02/20250202_GAS-3-KG-LANGKA.jpg'
                ],
                [
                    'category' => 'Minuman',
                    'product' => 'Le mineral 600Ml',
                    'price' => 3000,
                    'stock' => 24,
                    'image_url' => 'https://p16-oec-sg.ibyteimg.com/tos-alisg-i-aphluv4xwc-sg/2e98aacc45d146bcaf873103352ca1d6~tplv-aphluv4xwc-white-pad-v1:500:500.jpeg'
                ],
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
                // 2. Buat Kategori (menggunakan firstOrCreate agar jika kategori sama tidak duplikat)
                $category = Category::firstOrCreate([
                    'name' => $data['category'],
                    'user_id' => $user->id,
                ], [
                    'isActive' => true,
                ]);

                // 3. Buat Produk 
                $product = Product::create([
                    'name' => $data['product'],
                    'price' => $data['price'],
                    'description' => $data['product'] . ' adalah produk contoh.',
                    'image_url' => $data['image_url'],
                    'category_id' => $category->id,
                    'is_active' => true,
                    'user_id' => $user->id,
                ]);

                // 4. Tambah Stok
                $stokAwal = $data['stock'];
                $product->stocks()->create([
                    'product_id' => $product->id,
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
