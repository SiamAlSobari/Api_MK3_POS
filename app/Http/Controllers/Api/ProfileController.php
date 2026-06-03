<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    /**
     * Check if the user has an active PRO subscription.
     */
    private function checkPro($user): bool
    {
        return \App\Models\Subscription::where("user_id", $user->id)
            ->where("plan_name", "PRO")
            ->where("status", "ACTIVE")
            ->whereDate("end_date", ">=", \Carbon\Carbon::today())
            ->exists();
    }

    #[OA\Get(
        path: "/profile",
        summary: "Get authenticated user profile",
        tags: ["Profile"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Profile retrieved", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", ref: "#/components/schemas/Profile"),
                ],
                type: "object"
            )),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $profile = $request
            ->user()
            ->profile()
            ->firstOrCreate(
                [],
                [
                    "image_url" =>
                        "https://www.google.com/url?sa=t&source=web&rct=j&url=https%3A%2F%2Fwww.magnific.com%2Ffree-photos-vectors%2Fplaceholder&ved=0CBcQjRxqFwoTCPDg1uKyyZQDFQAAAAAdAAAAABA8&opi=89978449",
                ],
            );

        $profileData = $profile->toArray();
        $profileData["ai_portfolio"] = null;

        if ($this->checkPro($request->user())) {
            $profileData["ai_portfolio"] = \App\Models\AiRun::where(
                "user_id",
                $request->user()->id,
            )
                ->where("type_ai", "PORTFOLIO")
                ->orderBy("created_at", "desc")
                ->with("portfolioInsight")
                ->first();
        }

        return response()->json([
            "message" => "Profile retrieved successfully.",
            "data" => $profileData,
        ]);
    }

    #[OA\Post(
        path: "/profile",
        summary: "Create user profile",
        tags: ["Profile"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(ref: "#/components/schemas/ProfileStoreRequest")
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Profile created", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", ref: "#/components/schemas/Profile"),
                ],
                type: "object"
            )),
            new OA\Response(response: 409, description: "Profile already exists"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            "bio" => ["nullable", "string"],
            "image" => ["nullable", "image"],
        ]);

        // If profile already exists, return it, otherwise create
        $profile = $request->user()->profile;

        if ($profile) {
            return response()->json(
                [
                    "message" =>
                        "Profile already exists. Use PUT/PATCH to update.",
                    "data" => $profile,
                ],
                409,
            );
        }

        $image_url =
            "https://www.google.com/url?sa=t&source=web&rct=j&url=https%3A%2F%2Fwww.magnific.com%2Ffree-photos-vectors%2Fplaceholder&ved=0CBcQjRxqFwoTCPDg1uKyyZQDFQAAAAAdAAAAABA8&opi=89978449";

        if ($request->hasFile("image")) {
            try {
                $file = $request->file("image");

                $cloudName = env("CLOUDINARY_CLOUD_NAME");
                $apiKey = env("CLOUDINARY_API_KEY");
                $apiSecret = env("CLOUDINARY_API_SECRET");

                $response = Http::asMultipart()
                    ->withBasicAuth($apiKey, $apiSecret)
                    ->post(
                        "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload",
                        [
                            "file" => fopen($file->getRealPath(), "r"),
                            "folder" => "pos_profiles",
                        ],
                    );

                if ($response->successful()) {
                    $image_url = $response->json()["secure_url"];
                } else {
                    return response()->json(
                        [
                            "error" => "Upload Cloudinary Gagal",
                            "detail" => $response->json(),
                        ],
                        500,
                    );
                }
            } catch (\Exception $e) {
                return response()->json(["error" => $e->getMessage()], 500);
            }
        }

        $profile = $request
            ->user()
            ->profile()
            ->create([
                "bio" => $data["bio"] ?? null,
                "image_url" => $image_url,
            ]);

        return response()->json(
            [
                "message" => "Profile created successfully.",
                "data" => $profile,
            ],
            201,
        );
    }

    #[OA\Put(
        path: "/profile",
        summary: "Update user profile (also accepts PATCH)",
        tags: ["Profile"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(ref: "#/components/schemas/ProfileUpdateRequest")
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Profile updated", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", ref: "#/components/schemas/Profile"),
                ],
                type: "object"
            )),
        ]
    )]
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            "bio" => ["nullable", "string"],
            "image" => ["nullable", "image"],
        ]);

        $profile = $request
            ->user()
            ->profile()
            ->firstOrCreate(
                [],
                [
                    "image_url" =>
                        "https://www.google.com/url?sa=t&source=web&rct=j&url=https%3A%2F%2Fwww.magnific.com%2Ffree-photos-vectors%2Fplaceholder&ved=0CBcQjRxqFwoTCPDg1uKyyZQDFQAAAAAdAAAAABA8&opi=89978449",
                ],
            );

        $updateData = [];
        if (array_key_exists("bio", $data)) {
            $updateData["bio"] = $data["bio"];
        }

        if ($request->hasFile("image")) {
            try {
                $file = $request->file("image");

                $cloudName = env("CLOUDINARY_CLOUD_NAME");
                $apiKey = env("CLOUDINARY_API_KEY");
                $apiSecret = env("CLOUDINARY_API_SECRET");

                $response = Http::asMultipart()
                    ->withBasicAuth($apiKey, $apiSecret)
                    ->post(
                        "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload",
                        [
                            "file" => fopen($file->getRealPath(), "r"),
                            "folder" => "pos_profiles",
                        ],
                    );

                if ($response->successful()) {
                    $updateData["image_url"] = $response->json()["secure_url"];
                } else {
                    return response()->json(
                        [
                            "error" => "Upload Cloudinary Gagal",
                            "detail" => $response->json(),
                        ],
                        500,
                    );
                }
            } catch (\Exception $e) {
                return response()->json(["error" => $e->getMessage()], 500);
            }
        }

        $profile->update($updateData);

        return response()->json([
            "message" => "Profile updated successfully.",
            "data" => $profile->fresh(),
        ]);
    }

    #[OA\Delete(
        path: "/profile",
        summary: "Delete / reset user profile",
        tags: ["Profile"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Profile deleted", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string", example: "Profile deleted / reset successfully."),
                ],
                type: "object"
            )),
        ]
    )]
    public function destroy(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;

        if ($profile) {
            $profile->delete();
        }

        return response()->json([
            "message" => "Profile deleted / reset successfully.",
        ]);
    }
}
