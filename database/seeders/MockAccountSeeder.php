<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Subscription;
use App\Models\AiRun;
use App\Models\BusyHourDailyForecast;
use App\Models\BusyHourHourlyPrediction;
use App\Models\BusyHourProductPrediction;
use App\Models\AiRecommendation;
use App\Models\AiRecommendationAction;
use App\Models\AiPortfolioInsight;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MockAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = 'mock.merchant@pos.com';
        $password = 'password';

        $this->command->info("Starting Mock Account Seeder for email: {$email}...");

        // 1. CLEANUP EXISTING MOCK USER DATA TO AVOID FAILED INTEGRITY CONSTRAINTS
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            $this->command->info("Existing mock user found. Cleaning up all old data to ensure a fresh start...");

            DB::transaction(function () use ($existingUser) {
                // Delete AI predictions
                $aiRunIds = AiRun::where('user_id', $existingUser->id)->pluck('id');
                if ($aiRunIds->isNotEmpty()) {
                    $dailyForecastIds = BusyHourDailyForecast::whereIn('ai_run_id', $aiRunIds)->pluck('id');
                    if ($dailyForecastIds->isNotEmpty()) {
                        $hourlyIds = BusyHourHourlyPrediction::whereIn('daily_forecast_id', $dailyForecastIds)->pluck('id');
                        BusyHourProductPrediction::whereIn('hourly_prediction_id', $hourlyIds)->delete();
                        BusyHourHourlyPrediction::whereIn('daily_forecast_id', $dailyForecastIds)->delete();
                        BusyHourDailyForecast::whereIn('ai_run_id', $aiRunIds)->delete();
                    }
                    AiRecommendationAction::whereIn('ai_recommendation_id', function ($q) use ($aiRunIds) {
                        $q->select('id')->from('ai_recommendations')->whereIn('ai_run_id', $aiRunIds);
                    })->delete();
                    AiRecommendation::whereIn('ai_run_id', $aiRunIds)->delete();
                    AiPortfolioInsight::whereIn('ai_run_id', $aiRunIds)->delete();
                    AiRun::where('user_id', $existingUser->id)->delete();
                }

                // Delete Transactions
                $transactionIds = Transaction::where('user_id', $existingUser->id)->pluck('id');
                TransactionItem::whereIn('transaction_id', $transactionIds)->delete();
                Transaction::where('user_id', $existingUser->id)->forceDelete(); // force delete soft deleted transactions too

                // Delete Stocks, Products, Categories
                $productIds = Product::where('user_id', $existingUser->id)->pluck('id');
                Stock::whereIn('product_id', $productIds)->forceDelete();
                Product::where('user_id', $existingUser->id)->forceDelete();
                Category::where('user_id', $existingUser->id)->delete();

                // Delete Subscriptions & Profile
                Subscription::where('user_id', $existingUser->id)->forceDelete();
                if ($existingUser->profile) {
                    $existingUser->profile()->delete();
                }

                // Finally delete user
                $existingUser->delete();
            });

            $this->command->info("Cleanup completed.");
        }

        // 2. CREATE MOCK USER
        $user = User::create([
            'name' => 'Mock POS Merchant',
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->command->info("Mock user created successfully!");

        // 3. SEED PRO SUBSCRIPTION (To enable AI features out-of-the-box)
        Subscription::create([
            'user_id' => $user->id,
            'plan_name' => 'PRO',
            'price' => 99000.00,
            'duration_days' => 365,
            'start_date' => Carbon::now()->subMonths(3)->toDateString(),
            'end_date' => Carbon::now()->addMonths(9)->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $this->command->info("Active PRO subscription assigned to mock user!");

        // 4. SEED REALISTIC POS CATEGORIES
        $categoriesData = [
            ['name' => 'Makanan Berat', 'description' => 'Menu hidangan utama/makanan berat khas Indonesia'],
            ['name' => 'Cemilan & Snack', 'description' => 'Makanan ringan dan jajanan pasar'],
            ['name' => 'Minuman Dingin', 'description' => 'Minuman segar, es, dan jus'],
            ['name' => 'Kopi & Teh', 'description' => 'Aneka racikan kopi pilihan dan teh'],
            ['name' => 'Dessert', 'description' => 'Pencuci mulut manis dan kue'],
            ['name' => 'Sembako', 'description' => 'Bahan makanan pokok dan kebutuhan dapur'],
            ['name' => 'Kebutuhan Mandi & Cuci', 'description' => 'Sabun, sampo, deterjen, dan pembersih'],
        ];

        $categories = [];
        foreach ($categoriesData as $c) {
            $categories[] = Category::create([
                'user_id' => $user->id,
                'name' => $c['name'],
                'is_active' => true,
            ]);
        }

        $this->command->info("Created " . count($categories) . " categories.");

        // 5. SEED CURATED PRODUCTS WITH STOCK
        $productsByCat = [
            'Makanan Berat' => [
                ['name' => 'Nasi Goreng Spesial', 'price' => 18000.00, 'desc' => 'Nasi goreng dengan telur mata sapi, suwiran ayam, dan kerupuk.'],
                ['name' => 'Nasi Goreng Gila', 'price' => 22000.00, 'desc' => 'Nasi goreng ekstra pedas dengan campuran sosis, bakso, dan telur dadar.'],
                ['name' => 'Mie Goreng Jawa', 'price' => 15000.00, 'desc' => 'Mie goreng khas Jawa dengan bumbu kemiri dan sayuran segar.'],
                ['name' => 'Mie Rebus Nyemek', 'price' => 16000.00, 'desc' => 'Mie rebus khas dengan kuah kental gurih pedas.'],
                ['name' => 'Ayam Goreng Lalapan', 'price' => 20000.00, 'desc' => 'Ayam goreng kremes disajikan dengan sambal korek dan lalapan.'],
                ['name' => 'Ayam Bakar Taliwang', 'price' => 24000.00, 'desc' => 'Ayam bakar bumbu Taliwang khas Lombok pedas menggigit.'],
                ['name' => 'Nasi Campur Bali', 'price' => 25000.00, 'desc' => 'Nasi campur dengan sate lilit, ayam sisit, sayur lawar, dan sambal matah.'],
                ['name' => 'Sate Ayam Madura', 'price' => 22000.00, 'desc' => '10 tusuk sate ayam disajikan dengan bumbu kacang kental dan lontong.'],
                ['name' => 'Bakso Sapi Urat', 'price' => 18000.00, 'desc' => 'Bakso urat sapi besar disajikan dengan kuah kaldu sapi hangat.'],
                ['name' => 'Mie Ayam Pangsit', 'price' => 15000.00, 'desc' => 'Mie ayam gurih dengan potongan ayam kecap dan pangsit basah.'],
            ],
            'Cemilan & Snack' => [
                ['name' => 'Kentang Goreng Crispy', 'price' => 12000.00, 'desc' => 'Kentang goreng renyah disajikan dengan saus sambal dan mayones.'],
                ['name' => 'Tempe Mendoan Hangat', 'price' => 10000.00, 'desc' => '5 pcs tempe mendoan lebar disajikan dengan cocolan kecap cabe rawit.'],
                ['name' => 'Pisang Goreng Keju', 'price' => 12000.00, 'desc' => 'Pisang goreng manis topping parutan keju dan susu kental manis.'],
                ['name' => 'Cireng Bumbu Rujak', 'price' => 10000.00, 'desc' => 'Cireng kenyal renyah disajikan dengan bumbu rujak pedas manis.'],
                ['name' => 'Roti Bakar Cokelat Keju', 'price' => 14000.00, 'desc' => 'Roti bakar isi cokelat premium dan parutan keju melimpah.'],
                ['name' => 'Dimsum Ayam Premium', 'price' => 18000.00, 'desc' => '4 pcs dimsum kukus hangat dengan saus asam pedas manis.'],
                ['name' => 'Singkong Keju Mekar', 'price' => 12000.00, 'desc' => 'Singkong goreng gurih lembut bertabur keju parut.'],
            ],
            'Minuman Dingin' => [
                ['name' => 'Es Teh Manis Jumbo', 'price' => 5000.00, 'desc' => 'Es teh manis segar ukuran cup besar.'],
                ['name' => 'Es Jeruk Peras Murni', 'price' => 8000.00, 'desc' => 'Es jeruk dari perasan jeruk asli tanpa pemanis buatan.'],
                ['name' => 'Es Campur Special', 'price' => 12000.00, 'desc' => 'Campuran buah segar, cincau, kolang-kaling, sirup coco pandan dan susu.'],
                ['name' => 'Es Teler Alpukat Nangka', 'price' => 14000.00, 'desc' => 'Es teler dengan isian alpukat mentega, kelapa muda, nangka, dan sirup.'],
                ['name' => 'Es Kelapa Muda Gula Merah', 'price' => 10000.00, 'desc' => 'Es kelapa muda segar disajikan dengan sirup gula merah asli.'],
                ['name' => 'Jus Alpukat Kocok', 'price' => 15000.00, 'desc' => 'Jus alpukat kental dengan lumuran cokelat kental di pinggiran gelas.'],
                ['name' => 'Jus Mangga Arumanis', 'price' => 12000.00, 'desc' => 'Jus mangga kental manis dari mangga arumanis segar.'],
                ['name' => 'Soda Gembira Nostalgia', 'price' => 10000.00, 'desc' => 'Perpaduan sirup coco pandan, susu kental manis, dan soda fanta putih.'],
            ],
            'Kopi & Teh' => [
                ['name' => 'Kopi Susu Gula Aren Ice', 'price' => 15000.00, 'desc' => 'Espresso blend, susu segar, dan sirup gula aren murni dingin.'],
                ['name' => 'Kopi Hitam Tubruk Arabika', 'price' => 8000.00, 'desc' => 'Seduhan kopi hitam Arabika nusantara mantap hangat.'],
                ['name' => 'Hot Cappuccino Creamy', 'price' => 18000.00, 'desc' => 'Kombinasi espresso seimbang dengan steamed milk dan foam tebal.'],
                ['name' => 'Ice Cafe Latte', 'price' => 20000.00, 'desc' => 'Espresso dengan susu UHT dingin segar.'],
                ['name' => 'Matcha Latte Ice', 'price' => 22000.00, 'desc' => 'Teh matcha Jepang premium dipadu susu segar dingin.'],
                ['name' => 'Es Teh Tarik Malaya', 'price' => 12000.00, 'desc' => 'Teh hitam pekat dicampur susu kental manis dengan teknik tarik.'],
                ['name' => 'Thai Tea Original Ice', 'price' => 12000.00, 'desc' => 'Thai tea racikan teh Thailand otentik dicampur susu evaporasi.'],
            ],
            'Dessert' => [
                ['name' => 'Butter Croissant Premium', 'price' => 15000.00, 'desc' => 'Croissant mentega renyah di luar dan lembut berlapis di dalam.'],
                ['name' => 'Brownies Panggang Fudgy', 'price' => 18000.00, 'desc' => 'Potongan fudgy brownies cokelat panggang dengan taburan almond.'],
                ['name' => 'Pancake Strawberry Ice Cream', 'price' => 16000.00, 'desc' => 'Dua pancake ditumpuk dengan saus strawberry dan 1 scoop es krim vanilla.'],
                ['name' => 'Waffle Chocolate Melt', 'price' => 18000.00, 'desc' => 'Waffle renyah disiram saus cokelat Belgian cair.'],
                ['name' => 'Gelato Vanilla Bean', 'price' => 15000.00, 'desc' => 'Es krim gelato rasa vanilla premium asli.'],
            ],
            'Sembako' => [
                ['name' => 'Beras Pandan Wangi 5kg', 'price' => 78000.00, 'desc' => 'Beras premium Pandan Wangi pulen dan wangi alami.'],
                ['name' => 'Minyak Goreng Bimoli 2L', 'price' => 38000.00, 'desc' => 'Minyak goreng kelapa sawit murni berkualitas tinggi.'],
                ['name' => 'Gula Pasir Gulaku 1kg', 'price' => 16500.00, 'desc' => 'Gula pasir tebu kristal bersih manis alami.'],
                ['name' => 'Indomie Goreng Spesial', 'price' => 3500.00, 'desc' => 'Mie instan goreng favorit nusantara rasa original.'],
                ['name' => 'Indomie Kari Ayam Kuah', 'price' => 3500.00, 'desc' => 'Mie instan kuah rasa kari ayam yang gurih mantap.'],
                ['name' => 'Telur Ayam Negeri 1kg', 'price' => 28000.00, 'desc' => '1 kg telur ayam ras segar berukuran rata-rata.'],
                ['name' => 'Susu UHT Ultra Milk 1L', 'price' => 18500.00, 'desc' => 'Susu cair segar UHT full cream sehat bergizi.'],
                ['name' => 'Kecap Manis Bango 520ml', 'price' => 24000.00, 'desc' => 'Kecap manis kental dari kedelai hitam berkualitas.'],
            ],
            'Kebutuhan Mandi & Cuci' => [
                ['name' => 'Sabun Mandi Lifebuoy Red 4s', 'price' => 18000.00, 'desc' => 'Sabun mandi batang antibakteri isi 4 pcs.'],
                ['name' => 'Shampoo Clear Men Cool 160ml', 'price' => 26000.00, 'desc' => 'Shampoo anti ketombe khusus pria sensasi dingin.'],
                ['name' => 'Pasta Gigi Pepsodent 190g', 'price' => 14000.00, 'desc' => 'Pasta gigi pelindung gigi berlubang pencegah karang.'],
                ['name' => 'Sabun Mama Lemon Jeruk 780ml', 'price' => 13500.00, 'desc' => 'Sabun cairan pencuci piring aroma jeruk nipis.'],
                ['name' => 'Deterjen Rinso Bubuk 800g', 'price' => 29500.00, 'desc' => 'Deterjen bubuk anti noda handal harum tahan lama.'],
            ],
        ];

        $products = [];
        $totalProducts = 0;
        foreach ($productsByCat as $catName => $pList) {
            $category = collect($categories)->firstWhere('name', $catName);
            foreach ($pList as $p) {
                $product = Product::create([
                    'user_id' => $user->id,
                    'category_id' => $category->id,
                    'name' => $p['name'],
                    'price' => $p['price'],
                    'description' => $p['desc'],
                    'image_url' => 'https://via.placeholder.com/150',
                    'is_active' => true,
                ]);

                // Create stock for product
                Stock::create([
                    'product_id' => $product->id,
                    'stock_on_hand' => rand(20, 250),
                ]);

                $products[] = $product;
                $totalProducts++;
            }
        }

        $this->command->info("Created {$totalProducts} products with stocks!");

        // 6. GENERATE REALISTIC TRANSACTION HISTORY (LAST 30 DAYS)
        $this->command->info("Generating realistic transaction history over the last 30 days...");
        
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();
        
        // Disable Eloquent model events & database query logs to maximize seeding performance
        DB::connection()->unsetEventDispatcher();
        DB::disableQueryLog();

        $transactionsBatch = [];
        $transactionItemsBatch = [];
        
        $transactionCount = 0;
        
        // We will seed using chunks in a single DB transaction to keep it blazing fast!
        DB::transaction(function () use ($startDate, $endDate, $products, $user, &$transactionCount) {
            $days = $startDate->diffInDays($endDate);
            
            for ($d = 0; $d <= $days; $d++) {
                $currentDate = (clone $startDate)->addDays($d);
                $isWeekend = $currentDate->isWeekend();
                
                // Determine number of transactions for the day (higher on weekends)
                $numTransactions = $isWeekend ? rand(5, 10) : rand(2, 5);
                
                for ($t = 0; $t < $numTransactions; $t++) {
                    // Generate time according to realistic business peaks (lunch: 11-13, dinner: 17-20)
                    $hourRand = rand(1, 100);
                    if ($hourRand <= 5) {
                        $hour = rand(8, 10); // Morning quiet
                    } elseif ($hourRand <= 40) {
                        $hour = rand(11, 13); // Lunch Peak (35%)
                    } elseif ($hourRand <= 50) {
                        $hour = rand(14, 16); // Afternoon quiet (10%)
                    } elseif ($hourRand <= 90) {
                        $hour = rand(17, 20); // Dinner Peak (40%)
                    } else {
                        $hour = rand(21, 22); // Night quiet (10%)
                    }
                    
                    $minute = rand(0, 59);
                    $second = rand(0, 59);
                    
                    $trxDateTime = (clone $currentDate)->setTime($hour, $minute, $second);
                    
                    // Transaction details
                    $trxType = 'SALE';
                    // Very rarely we have purchases or adjustments
                    $typeRand = rand(1, 100);
                    if ($typeRand == 99) {
                        $trxType = 'PURCHASE';
                    } elseif ($typeRand == 100) {
                        $trxType = 'ADJUSTMENT';
                    }
                    
                    // Payment method distribution
                    $payRand = rand(1, 100);
                    $paymentMethod = 'CASH';
                    if ($payRand > 50 && $payRand <= 90) {
                        $paymentMethod = 'QRIS';
                    } elseif ($payRand > 90) {
                        $paymentMethod = 'TRANSFER';
                    }
                    
                    // Create Transaction
                    // Since we are inside DB::transaction and using direct DB insertion, it is very fast.
                    // However, we need the auto-increment ID, so we use insertGetId
                    $trxId = DB::table('transactions')->insertGetId([
                        'user_id' => $user->id,
                        'trx_type' => $trxType,
                        'trx_date' => $currentDate->toDateString(),
                        'payment_method' => $paymentMethod,
                        'paid_at' => $trxDateTime->toDateTimeString(),
                        'total_amount' => 0.00, // Will update this right after adding items
                        'created_at' => $trxDateTime,
                        'updated_at' => $trxDateTime,
                    ]);
                    
                    // Randomize number of products bought in this transaction (1 to 5)
                    $itemRand = rand(1, 100);
                    if ($itemRand <= 40) {
                        $numItems = 1;
                    } elseif ($itemRand <= 75) {
                        $numItems = 2;
                    } elseif ($itemRand <= 90) {
                        $numItems = 3;
                    } elseif ($itemRand <= 97) {
                        $numItems = 4;
                    } else {
                        $numItems = 5;
                    }
                    
                    // Choose distinct random products
                    $randomProducts = collect($products)->random(min($numItems, count($products)));
                    
                    $totalAmount = 0;
                    $itemsToInsert = [];
                    
                    foreach ($randomProducts as $prod) {
                        // Quantity
                        $qtyRand = rand(1, 100);
                        if ($qtyRand <= 75) {
                            $quantity = 1;
                        } elseif ($qtyRand <= 92) {
                            $quantity = 2;
                        } elseif ($qtyRand <= 98) {
                            $quantity = 3;
                        } else {
                            $quantity = rand(4, 6);
                        }
                        
                        $unitPrice = $prod->price;
                        $linePrice = $quantity * $unitPrice;
                        $totalAmount += $linePrice;
                        
                        $itemsToInsert[] = [
                            'transaction_id' => $trxId,
                            'product_id' => $prod->id,
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'line_price' => $linePrice,
                            'created_at' => $trxDateTime,
                            'updated_at' => $trxDateTime,
                        ];
                    }
                    
                    // Bulk insert items for this transaction
                    DB::table('transaction_items')->insert($itemsToInsert);
                    
                    // Update transaction total amount
                    DB::table('transactions')->where('id', $trxId)->update([
                        'total_amount' => $totalAmount
                    ]);
                    
                    $transactionCount++;
                }
            }
        });

        $this->command->info("Successfully seeded {$transactionCount} transactions and associated items!");
        $this->command->info("Mock seeder completed successfully!");
        $this->command->info("Credentials: email = '{$email}', password = '{$password}'");
    }
}
