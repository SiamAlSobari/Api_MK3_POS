<?php

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\AiRun;
use App\Models\AiRecommendation;
use App\Models\AiSeasonalRecommendation;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seasonal recommendation relationship and backward compatible appends work correctly', function () {
    $user = User::factory()->create();

    $category = Category::create([
        'name' => 'Beverage',
        'user_id' => $user->id,
    ]);

    $product = Product::create([
        'name' => 'Ice Coffee',
        'price' => 15000,
        'category_id' => $category->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $aiRun = AiRun::create([
        'user_id' => $user->id,
        'type_ai' => 'STOCKS',
        'status' => 'COMPLETED',
        'generated_at' => now(),
    ]);

    $recommendation = AiRecommendation::create([
        'ai_run_id' => $aiRun->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_price' => $product->price,
        'current_stock' => 10,
        'avg_daily_sales' => 2,
        'recommed_restok_qty' => 20,
        'risk_level' => 'MEDIUM',
    ]);

    $seasonal = AiSeasonalRecommendation::create([
        'ai_recommendation_id' => $recommendation->id,
        'min' => 15,
        'max' => 25,
        'label' => 'Restock 15-25 for Eid',
        'holiday' => 'Eid',
        'reason' => 'High demand during Eid',
    ]);

    // Assert relations work
    expect($recommendation->seasonalRecommendation)->not->toBeNull();
    expect($recommendation->seasonalRecommendation->min)->toBe(15);
    expect($recommendation->seasonalRecommendation->holiday)->toBe('Eid');
    
    expect($seasonal->aiRecommendation->id)->toBe($recommendation->id);

    // Assert appends for backwards compatibility
    expect($recommendation->seasonal_min)->toBe(15);
    expect($recommendation->seasonal_max)->toBe(25);
    expect($recommendation->seasonal_label)->toBe('Restock 15-25 for Eid');
    expect($recommendation->seasonal_holiday)->toBe('Eid');
    expect($recommendation->seasonal_reason)->toBe('High demand during Eid');

    // Assert serialization
    $array = $recommendation->toArray();
    expect($array['seasonal_min'])->toBe(15);
    expect($array['seasonal_holiday'])->toBe('Eid');
    expect($array['seasonal_reason'])->toBe('High demand during Eid');
});

test('latest stocks endpoint includes seasonal recommendation relationship and backward compatible fields', function () {
    $user = User::factory()->create();

    // Grant PRO subscription
    Subscription::create([
        'user_id' => $user->id,
        'plan_name' => 'PRO',
        'status' => 'ACTIVE',
        'price' => 99000,
        'duration_days' => 30,
        'start_date' => now(),
        'end_date' => now()->addMonth(),
    ]);

    $category = Category::create([
        'name' => 'Beverage',
        'user_id' => $user->id,
    ]);

    $product = Product::create([
        'name' => 'Ice Coffee',
        'price' => 15000,
        'category_id' => $category->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $aiRun = AiRun::create([
        'user_id' => $user->id,
        'type_ai' => 'STOCKS',
        'status' => 'COMPLETED',
        'generated_at' => now(),
    ]);

    $recommendation = AiRecommendation::create([
        'ai_run_id' => $aiRun->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_price' => $product->price,
        'current_stock' => 10,
        'avg_daily_sales' => 2,
        'recommed_restok_qty' => 20,
        'risk_level' => 'MEDIUM',
    ]);

    $seasonal = AiSeasonalRecommendation::create([
        'ai_recommendation_id' => $recommendation->id,
        'min' => 15,
        'max' => 25,
        'label' => 'Restock 15-25 for Eid',
        'holiday' => 'Eid',
        'reason' => 'High demand during Eid',
    ]);

    // Act as user and call latestStocks endpoint
    $response = $this->actingAs($user)
        ->getJson('/api/ai/runs/latest/stocks');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.ai_recommendations.0.seasonal_min', 15)
        ->assertJsonPath('data.ai_recommendations.0.seasonal_holiday', 'Eid')
        ->assertJsonPath('data.ai_recommendations.0.seasonal_recommendation.min', 15);
});
