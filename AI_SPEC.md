# AI Integration API Specification

This document details the API endpoints related to the AI features in the application. All AI endpoints require the user to have an active `PRO_MAX` subscription.

## Base URL
`/api/ai` (assuming the routes are grouped under the `ai` prefix, as is standard for these controllers)

## Authentication & Authorization
- **Authentication**: Bearer Token (Sanctum/Passport)
- **Authorization**: Requires `PRO_MAX` subscription plan with an `ACTIVE` status and an unexpired `end_date`.
- **Unauthorized Response** (403 Forbidden):
  ```json
  {
      "success": false,
      "message": "This feature requires an active PRO_MAX subscription."
  }
  ```

---

## 1. Get Latest STOCKS AI Run

Retrieves the most recent AI run for stock recommendations associated with the authenticated user.

- **Endpoint**: `GET /runs/latest/stocks`
- **Method**: `GET`
- **Headers**:
  - `Authorization: Bearer {token}`
  - `Accept: application/json`

### Responses

**Success (200 OK)**
```json
{
    "success": true,
    "message": "Latest AI STOCKS run retrieved successfully",
    "data": {
        "id": 1,
        "user_id": 1,
        "type_ai": "STOCKS",
        "status": "COMPLETED",
        "generated_at": "2023-10-27T10:00:00.000000Z",
        "created_at": "2023-10-27T10:00:00.000000Z",
        "updated_at": "2023-10-27T10:00:00.000000Z",
        "ai_recommendations": [
            {
                "id": 1,
                "ai_run_id": 1,
                "product_id": 101,
                "current_stock": 5,
                "recommed_restok_qty": 20,
                "risk_level": "HIGH",
                "days_until_emty": 2,
                "estimated_emty_date": "2023-10-29",
                "risk": "Stockout Risk",
                "description": "Critical low stock, restock immediately.",
                "risk_point": 90,
                "product": {
                    "id": 101,
                    "name": "Product A",
                    "price": 10000
                },
                "ai_recommendation_actions": {
                    "id": 1,
                    "ai_recommendation_id": 1,
                    "action_type": "DONE",
                    "action_at": "2023-10-28T10:00:00.000000Z"
                }
            }
        ]
    }
}
```

**Not Found (404 Not Found)**
```json
{
    "success": false,
    "message": "No AI run found for STOCKS",
    "data": null
}
```

---

## 2. Trigger AI Stock Analysis

Triggers a new AI analysis for stock forecasting and restocking recommendations by sending the user's transactions to the external AI service.

- **Endpoint**: `POST /runs/analyze`
- **Method**: `POST`
- **Headers**:
  - `Authorization: Bearer {token}`
  - `Accept: application/json`

### Responses

**Success (200 OK)**
```json
{
    "success": true,
    "message": "AI run started successfully",
    "data": {
        "id": 2,
        "user_id": 1,
        "type_ai": "STOCKS",
        "status": "COMPLETED",
        "generated_at": "2023-10-28T10:00:00.000000Z",
        "created_at": "2023-10-28T10:00:00.000000Z",
        "updated_at": "2023-10-28T10:00:00.000000Z",
        "ai_recommendations": [
            {
                "id": 5,
                "ai_run_id": 2,
                "product_id": 102,
                "current_stock": 10,
                "recommed_restok_qty": 15,
                "risk_level": "MEDIUM",
                "days_until_emty": 5,
                "estimated_emty_date": "2023-11-02",
                "risk": "Moderate Risk",
                "description": "Consider restocking soon.",
                "risk_point": 60,
                "created_at": "2023-10-28T10:00:00.000000Z",
                "updated_at": "2023-10-28T10:00:00.000000Z"
            }
        ]
    }
}
```

**External API Error (e.g., 400 Bad Request, 500 Internal Server Error)**
```json
{
    "success": false,
    "message": "Failed to fetch AI recommendations"
}
```

**Internal Server Error (500 Internal Server Error)**
```json
{
    "success": false,
    "message": "An error occurred during AI analysis: [exception message]"
}
```

---

## 3. Update AI Recommendation Action

Updates or creates an action taken by the user on a specific AI recommendation.

- **Endpoint**: `PATCH /recommendations/{recommendationId}/action`
- **Method**: `PATCH`
- **Headers**:
  - `Authorization: Bearer {token}`
  - `Content-Type: application/json`
  - `Accept: application/json`

### Path Parameters
- `recommendationId` (integer, required): The ID of the `AiRecommendation`.

### Request Body
```json
{
    "action_type": "DONE" 
}
```
*Note: `action_type` must be either `DONE` or `IGNORE`.*

### Responses

**Success (200 OK)**
```json
{
    "success": true,
    "message": "Action updated successfully",
    "data": {
        "id": 1,
        "ai_recommendation_id": 1,
        "action_type": "DONE",
        "action_at": "2023-10-28T10:05:00.000000Z",
        "created_at": "2023-10-28T10:05:00.000000Z",
        "updated_at": "2023-10-28T10:05:00.000000Z"
    }
}
```

**Validation Error (422 Unprocessable Entity)**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "action_type": [
            "The action type field is required."
        ]
    }
}
```

**Not Found (404 Not Found)**
```json
{
    "success": false,
    "message": "AI recommendation not found"
}
```