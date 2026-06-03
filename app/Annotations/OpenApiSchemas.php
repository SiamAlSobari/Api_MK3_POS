<?php

namespace App\Annotations;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "POS API Documentation",
    description: "API documentation for Point of Sale application",
)]
#[OA\Server(
    url: "http://127.0.0.1:8000",
    description: "Local development server",
)]
#[OA\Server(
    url: "https://paskelompok2.online",
    description: "Production server",
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Enter your Sanctum token",
)]

// ===================== REQUEST SCHEMAS =====================

#[OA\Schema(
    schema: "AuthRegisterRequest",
    required: ["name", "email", "password"],
    properties: [
        new OA\Property(property: "name", type: "string", maxLength: 255, example: "John Doe"),
        new OA\Property(property: "email", type: "string", format: "email", maxLength: 255, example: "john@example.com"),
        new OA\Property(property: "password", type: "string", minLength: 6, example: "secret123"),
    ],
)]
#[OA\Schema(
    schema: "AuthLoginRequest",
    required: ["email", "password"],
    properties: [
        new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
        new OA\Property(property: "password", type: "string", example: "secret123"),
    ],
)]
#[OA\Schema(
    schema: "ProductStoreRequest",
    required: ["name", "price", "stock"],
    properties: [
        new OA\Property(property: "name", type: "string", maxLength: 255, example: "Mie Goreng Aceh"),
        new OA\Property(property: "price", type: "number", format: "float", example: 3500),
        new OA\Property(property: "description", type: "string", nullable: true, example: "Makanan khas Aceh"),
        new OA\Property(property: "stock", type: "integer", example: 40),
        new OA\Property(property: "image", type: "string", format: "binary", description: "Product image (max 2MB)"),
        new OA\Property(property: "category_id", type: "integer", nullable: true, example: 1),
    ],
)]
#[OA\Schema(
    schema: "ProductUpdateRequest",
    properties: [
        new OA\Property(property: "name", type: "string", maxLength: 255, example: "Mie Goreng Aceh"),
        new OA\Property(property: "price", type: "number", format: "float", example: 4000),
        new OA\Property(property: "description", type: "string", nullable: true, example: "Updated description"),
        new OA\Property(property: "stock", type: "integer", example: 50),
        new OA\Property(property: "image", type: "string", format: "binary", description: "Product image (max 2MB)"),
        new OA\Property(property: "category_id", type: "integer", nullable: true, example: 1),
    ],
)]
#[OA\Schema(
    schema: "CategoryStoreRequest",
    required: ["name"],
    properties: [
        new OA\Property(property: "name", type: "string", maxLength: 255, example: "Makanan"),
        new OA\Property(property: "description", type: "string", nullable: true, example: "Kategori makanan ringan dan berat"),
    ],
)]
#[OA\Schema(
    schema: "CategoryUpdateRequest",
    properties: [
        new OA\Property(property: "name", type: "string", maxLength: 255, example: "Makanan Ringan"),
        new OA\Property(property: "description", type: "string", nullable: true, example: "Kategori makanan ringan"),
    ],
)]
#[OA\Schema(
    schema: "CategoryStatusRequest",
    required: ["is_active"],
    properties: [
        new OA\Property(property: "is_active", type: "boolean", example: true),
    ],
)]
#[OA\Schema(
    schema: "TransactionStoreRequest",
    required: ["trx_type", "trx_date", "payment_method", "items"],
    properties: [
        new OA\Property(property: "trx_type", type: "string", enum: ["SALE", "PURCHASE", "ADJUSTMENT"], example: "SALE"),
        new OA\Property(property: "trx_date", type: "string", format: "date", example: "2026-06-03"),
        new OA\Property(property: "payment_method", type: "string", example: "CASH"),
        new OA\Property(
            property: "items",
            type: "array",
            items: new OA\Items(
                properties: [
                    new OA\Property(property: "product_id", type: "integer", example: 1),
                    new OA\Property(property: "quantity", type: "integer", example: 2),
                    new OA\Property(property: "unit_price", type: "number", format: "float", example: 3500),
                ],
                type: "object",
                required: ["product_id", "quantity", "unit_price"]
            )
        ),
    ],
)]
#[OA\Schema(
    schema: "BillingSubscribeRequest",
    required: ["plan_name"],
    properties: [
        new OA\Property(property: "plan_name", type: "string", enum: ["PRO"], example: "PRO"),
    ],
)]
#[OA\Schema(
    schema: "ProfileStoreRequest",
    properties: [
        new OA\Property(property: "bio", type: "string", nullable: true, example: "Pemilik toko kelontong"),
        new OA\Property(property: "image", type: "string", format: "binary", description: "Profile image"),
    ],
)]
#[OA\Schema(
    schema: "ProfileUpdateRequest",
    properties: [
        new OA\Property(property: "bio", type: "string", nullable: true, example: "Updated bio"),
        new OA\Property(property: "image", type: "string", format: "binary", description: "Profile image"),
    ],
)]
#[OA\Schema(
    schema: "RecommendationActionRequest",
    required: ["action_type"],
    properties: [
        new OA\Property(property: "action_type", type: "string", enum: ["DONE", "IGNORE"], example: "DONE"),
    ],
)]

// ===================== RESPONSE SCHEMAS =====================

#[OA\Schema(
    schema: "User",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "John Doe"),
        new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ],
)]
#[OA\Schema(
    schema: "Product",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "Mie Goreng Aceh"),
        new OA\Property(property: "price", type: "number", format: "float", example: 3500),
        new OA\Property(property: "description", type: "string", example: "Makanan khas Aceh"),
        new OA\Property(property: "image_url", type: "string", nullable: true),
        new OA\Property(property: "category_id", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "is_active", type: "boolean", example: true),
        new OA\Property(property: "user_id", type: "integer", example: 1),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "category", ref: "#/components/schemas/Category"),
        new OA\Property(
            property: "stocks",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/Stock")
        ),
    ],
)]
#[OA\Schema(
    schema: "Stock",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "product_id", type: "integer", example: 1),
        new OA\Property(property: "stock_on_hand", type: "integer", example: 40),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ],
)]
#[OA\Schema(
    schema: "Category",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "Makanan"),
        new OA\Property(property: "description", type: "string", nullable: true),
        new OA\Property(property: "is_active", type: "boolean", example: true),
        new OA\Property(property: "user_id", type: "integer", example: 1),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(
            property: "products",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/Product")
        ),
    ],
)]
#[OA\Schema(
    schema: "Transaction",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "user_id", type: "integer", example: 1),
        new OA\Property(property: "trx_type", type: "string", enum: ["SALE", "PURCHASE", "ADJUSTMENT"]),
        new OA\Property(property: "trx_date", type: "string", format: "date", example: "2026-06-03"),
        new OA\Property(property: "payment_method", type: "string", example: "CASH"),
        new OA\Property(property: "paid_at", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "total_amount", type: "number", format: "float", example: 7000),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(
            property: "items",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/TransactionItem")
        ),
        new OA\Property(property: "user", ref: "#/components/schemas/User"),
    ],
)]
#[OA\Schema(
    schema: "TransactionItem",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "transaction_id", type: "integer", example: 1),
        new OA\Property(property: "product_id", type: "integer", example: 1),
        new OA\Property(property: "quantity", type: "integer", example: 2),
        new OA\Property(property: "unit_price", type: "number", format: "float", example: 3500),
        new OA\Property(property: "line_price", type: "number", format: "float", example: 7000),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "product", ref: "#/components/schemas/Product"),
    ],
)]
#[OA\Schema(
    schema: "Profile",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "user_id", type: "integer", example: 1),
        new OA\Property(property: "bio", type: "string", nullable: true, example: "Pemilik toko"),
        new OA\Property(property: "image_url", type: "string", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ],
)]
#[OA\Schema(
    schema: "Subscription",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "user_id", type: "integer", example: 1),
        new OA\Property(property: "plan_name", type: "string", example: "PRO"),
        new OA\Property(property: "price", type: "number", format: "float", example: 29000),
        new OA\Property(property: "duration_days", type: "integer", example: 30),
        new OA\Property(property: "start_date", type: "string", format: "date"),
        new OA\Property(property: "end_date", type: "string", format: "date"),
        new OA\Property(property: "status", type: "string", enum: ["PENDING", "ACTIVE", "EXPIRED"]),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ],
)]
#[OA\Schema(
    schema: "Payment",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "user_id", type: "integer", example: 1),
        new OA\Property(property: "subscription_id", type: "integer", example: 1),
        new OA\Property(property: "order_id", type: "string", example: "ordered-uuid"),
        new OA\Property(property: "gross_amount", type: "number", format: "float", example: 29000),
        new OA\Property(property: "payment_type", type: "string", example: "MIDTRANS"),
        new OA\Property(property: "transaction_time", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "status", type: "string", enum: ["PENDING", "SETTLEMENT", "EXPIRED", "CANCEL", "DENY"]),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ],
)]
#[OA\Schema(
    schema: "AiRun",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "user_id", type: "integer", example: 1),
        new OA\Property(property: "type_ai", type: "string", enum: ["STOCKS", "BUSY", "PORTFOLIO"]),
        new OA\Property(property: "status", type: "string", enum: ["COMPLETED", "FAILED"]),
        new OA\Property(property: "generated_at", type: "string", format: "date-time"),
        new OA\Property(property: "total_products", type: "integer", nullable: true),
        new OA\Property(property: "seasonal_insight", type: "string", nullable: true),
        new OA\Property(property: "error_message", type: "string", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ],
)]
#[OA\Schema(
    schema: "AiRecommendation",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "ai_run_id", type: "integer", example: 1),
        new OA\Property(property: "product_id", type: "integer", example: 1),
        new OA\Property(property: "product_name", type: "string"),
        new OA\Property(property: "current_stock", type: "integer"),
        new OA\Property(property: "recommed_restok_qty", type: "integer"),
        new OA\Property(property: "restock_min", type: "integer", nullable: true),
        new OA\Property(property: "restock_max", type: "integer", nullable: true),
        new OA\Property(property: "restock_label", type: "string", nullable: true),
        new OA\Property(property: "risk_level", type: "string", nullable: true),
        new OA\Property(property: "days_until_emty", type: "integer", nullable: true),
        new OA\Property(property: "risk_point", type: "number", format: "float"),
    ],
)]
#[OA\Schema(
    schema: "AiRecommendationAction",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "ai_recommendation_id", type: "integer", example: 1),
        new OA\Property(property: "action_type", type: "string", enum: ["DONE", "IGNORE"]),
        new OA\Property(property: "action_at", type: "string", format: "date-time"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ],
)]
#[OA\Schema(
    schema: "AiPortfolioInsight",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "ai_run_id", type: "integer", example: 1),
        new OA\Property(property: "insight", type: "string"),
        new OA\Property(property: "total_omset_minggu_ini", type: "number", format: "float"),
        new OA\Property(property: "total_transaksi", type: "integer"),
        new OA\Property(property: "rata_rata_transaksi_per_hari", type: "number", format: "float"),
        new OA\Property(property: "bintang_warung", type: "string", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
    ],
)]
#[OA\Schema(
    schema: "BusyHourDailyForecast",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "ai_run_id", type: "integer", example: 1),
        new OA\Property(property: "forecast_date", type: "string", format: "date"),
        new OA\Property(property: "day_name", type: "string"),
        new OA\Property(property: "is_weekend", type: "boolean"),
        new OA\Property(property: "peak_hour", type: "integer"),
        new OA\Property(property: "total_predicted_trx", type: "integer"),
        new OA\Property(property: "total_predicted_revenue", type: "number", format: "float"),
    ],
)]
#[OA\Schema(
    schema: "ReportData",
    properties: [
        new OA\Property(property: "total_pendapatan", type: "number", format: "float"),
        new OA\Property(property: "pendapatan_vs_sebelumnya", properties: [
            new OA\Property(property: "nilai_sebelumnya", type: "number", format: "float"),
            new OA\Property(property: "persentase_perubahan", type: "number", format: "float"),
        ], type: "object"),
        new OA\Property(property: "total_transaksi", type: "integer"),
        new OA\Property(property: "rata_rata_keranjang", type: "number", format: "float"),
        new OA\Property(property: "tren_penjualan", type: "array", items: new OA\Items(
            properties: [
                new OA\Property(property: "date", type: "string", format: "date"),
                new OA\Property(property: "total", type: "number", format: "float"),
            ],
            type: "object"
        )),
        new OA\Property(property: "produk_terlaris", type: "array", items: new OA\Items(
            properties: [
                new OA\Property(property: "name", type: "string"),
                new OA\Property(property: "total_quantity", type: "integer"),
            ],
            type: "object"
        )),
        new OA\Property(property: "transaksi_terakhir", type: "array", items: new OA\Items(ref: "#/components/schemas/Transaction")),
    ],
)]
#[OA\Schema(
    schema: "ReportResponse",
    properties: [
        new OA\Property(property: "message", type: "string"),
        new OA\Property(
            property: "data",
            properties: [
                new OA\Property(property: "hari_ini", ref: "#/components/schemas/ReportData"),
                new OA\Property(property: "minggu_ini", ref: "#/components/schemas/ReportData"),
                new OA\Property(property: "bulan_ini", ref: "#/components/schemas/ReportData"),
                new OA\Property(property: "tahun_ini", ref: "#/components/schemas/ReportData"),
                new OA\Property(property: "sepanjang_masa", ref: "#/components/schemas/ReportData"),
                new OA\Property(
                    property: "grafik_data",
                    properties: [
                        new OA\Property(property: "minggu_ini", properties: [
                            new OA\Property(property: "labels", type: "array", items: new OA\Items(type: "string")),
                            new OA\Property(property: "values", type: "array", items: new OA\Items(type: "number")),
                        ], type: "object"),
                        new OA\Property(property: "bulan_ini", properties: [
                            new OA\Property(property: "labels", type: "array", items: new OA\Items(type: "string")),
                            new OA\Property(property: "values", type: "array", items: new OA\Items(type: "number")),
                        ], type: "object"),
                        new OA\Property(property: "tahun_ini", properties: [
                            new OA\Property(property: "labels", type: "array", items: new OA\Items(type: "string")),
                            new OA\Property(property: "values", type: "array", items: new OA\Items(type: "number")),
                        ], type: "object"),
                    ],
                    type: "object"
                ),
            ],
            type: "object"
        ),
    ],
)]

// ===================== CLOSURE-BASED ENDPOINTS (defined in routes/api.php) =====================

#[OA\Get(
    path: "/health",
    summary: "Check API health",
    tags: ["Health"],
    responses: [
        new OA\Response(response: 200, description: "API is healthy", content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "ok"),
                new OA\Property(property: "message", type: "string", example: "API is healthy"),
                new OA\Property(property: "timestamp", type: "string", format: "date-time"),
            ],
            type: "object"
        )),
    ]
)]
#[OA\Get(
    path: "/user",
    summary: "Get authenticated user",
    tags: ["Auth"],
    security: [["bearerAuth" => []]],
    responses: [
        new OA\Response(response: 200, description: "Current user data", content: new OA\JsonContent(ref: "#/components/schemas/User")),
        new OA\Response(response: 401, description: "Unauthenticated"),
    ]
)]

// ===================== SCHEDULED ARTISAN COMMANDS (routes/console.php) =====================
// These are not HTTP endpoints but scheduled Artisan commands:
// 1. ai:analyze-stocks       - Daily at 01:00 - AI stock analysis
// 2. ai:analyze-busy-hours   - Daily at 16:00 - AI busy hour analysis
// 3. ai:generate-portfolios  - Daily (internal 7-day filter) - Weekly portfolio generation

// OpenAPI Tag definitions
#[OA\Tag(name: "Auth", description: "Authentication endpoints (register, login, session)")]
#[OA\Tag(name: "Products", description: "Product CRUD operations")]
#[OA\Tag(name: "Categories", description: "Category CRUD operations")]
#[OA\Tag(name: "Transactions", description: "Transaction management")]
#[OA\Tag(name: "Reports", description: "Sales reports and analytics")]
#[OA\Tag(name: "Profile", description: "User profile management")]
#[OA\Tag(name: "Billing", description: "Subscription and payment management")]
#[OA\Tag(name: "AI", description: "AI-powered analytics features")]
#[OA\Tag(name: "Health", description: "API health check")]
class OpenApiSchemas
{
}
