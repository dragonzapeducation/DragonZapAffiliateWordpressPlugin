# Affiliate API (v1)

This document describes the public affiliate API exposed under `/api/v1`. All endpoints are JSON-only and require a valid affiliate API key unless stated otherwise.

## Base URL & Versioning

- Base path: `/api/v1`
- Default host: use the affiliate API base domain provided for your account (e.g. `https://affiliate.example.com/api/v1`).
- Content type: send `Accept: application/json`. POST requests must also set `Content-Type: application/json`.

## Authentication

Affiliate requests are authenticated with an API key issued to a specific affiliate. Keys can be rotated or revoked from the affiliate dashboard.

Provide the **plain** API key using one of the following:

| Transport | Example |
| --- | --- |
| Authorization header | `Authorization: Bearer YOUR_PLAIN_KEY` |
| Custom header | `X-Affiliate-Api-Key: YOUR_PLAIN_KEY` |
| Query string | `?api_key=YOUR_PLAIN_KEY` (only for server-to-server calls over HTTPS) |

Failed authentication responses share the structure:

```json
{
  "success": false,
  "message": "API key is missing."
}
```

A successful authentication will resolve the affiliate and expose it as the acting user. Every authenticated request also updates the key's `last_used_at` timestamp.

Inactive affiliate accounts are rejected with `401` and the message `"Affiliate account is not active."` even when the provided API key is otherwise valid.

## Scopes

Some endpoints enforce scopes through the `affiliate.scopes` middleware. The supported scopes are defined in `App\Models\AffiliateApiKey::SUPPORTED_SCOPES`:

- `payouts.history.view`
- `payments.view`
- `webhooks.manage`
- `blogs.manage`
- `blogs.accounts.manage`
- `products.list`
- `products.view`
- `products.discounts.manage`
- `promotions.view`

> **Note:** The scopes listed for each endpoint are enforced. Additional scopes are reserved for future features.

## Endpoint Reference

### Health Check

- **Method & Path:** `GET /api/v1/test`
- **Scope:** none
- **Description:** Simple ping to verify that the API key is valid.

**Sample request:**

```bash
curl -X GET \
  -H "Authorization: Bearer YOUR_PLAIN_KEY" \
  https://example.com/api/v1/test
```

**Successful response (200):**

```json
{
  "success": true,
  "message": "Affiliate API key authenticated successfully.",
  "data": {
    "affiliate_id": 123,
    "scopes": [
      "products.list",
      "products.view"
    ],
    "restrictions": []
  }
}
```

The `restrictions` array enumerates scopes that require additional approval and are not yet available for the affiliate. At the moment the only scope that can appear here is `"blogs.manage"`.

**Error responses:** 401 when the key is missing or invalid.

---

### Categories

- **Method & Path:** `GET /api/v1/categories`
- **Scope:** `products.list`
- **Description:** Returns the list of active product categories ordered alphabetically.

**Successful response (200):**

```json
{
  "success": true,
  "data": {
    "categories": [
      {
        "name": "Design",
        "slug": "design"
      },
      {
        "name": "Development",
        "slug": "development"
      }
    ]
  }
}
```

The response omits categories that are missing a slug, have a blank slug, are inactive, or have been soft deleted.

---

### Products

#### List Products

- **Method & Path:** `GET /api/v1/products`
- **Scope:** `products.list`
- **Description:** Returns published storefront products ordered by newest first.
- **Query Parameters:**

| Name | Type | Default | Notes |
| --- | --- | --- | --- |
| `page` | integer | `1` | Standard Laravel pagination index. |
| `per_page` | integer | `15` | Range `1–100`. |
| `currency_code` | string | `USD` | Three-letter code; used when `currency_from_ip` is omitted or cannot be resolved. |
| `currency_from_ip` | string | — | Optional IPv4/IPv6 address used to infer currency. |
| `type` | string\|array | — | Filter by `model_type`; accepts full class names (e.g., `App\Models\Course`). |
| `category_slug` | string\|array | — | Filter by storefront category slug. Accepts a single slug, comma-separated string, or repeated query parameters. |
| `search` | string | — | Case-insensitive keyword filtering across product title, slug, description, and category name/slug. Whitespace-separated terms must all match. |

When both `currency_code` and `currency_from_ip` are present, the IP-derived currency is used if a match is found; otherwise the request falls back to the provided `currency_code` or `USD`.

Use the `search` parameter to narrow results to products whose content includes the provided keywords. Each word (split on whitespace) is applied as a required match across the searchable fields so you can combine multiple terms without losing specificity.

Products backed by courses expose an `affiliate_coupons` object alongside the usual fields. The object always includes the keys `low`, `mid`, and `high`, matching the configured tiers. Each key contains either coupon details (`code`, `percent_off`, `discount_decimal`, `new_price`, `expires_at`) or `null` when that tier is not currently available. `discount_decimal` is the percentage discount expressed as a decimal (e.g., `0.25` for 25%), `new_price` is the course price after the coupon is applied (rounded to two decimals), and `expires_at` is an ISO 8601 timestamp or `null`. Bundle children that reference a course share the same structure so affiliates can surface coupon codes wherever a course appears.

**Sample request:**

```bash
curl -G \
  -H "Authorization: Bearer YOUR_PLAIN_KEY" \
  --data-urlencode "per_page=5" \
  --data-urlencode "search=rocket" \
  --data-urlencode "currency_code=EUR" \
  https://example.com/api/v1/products
```

**Successful response (200):**

```json
{
  "success": true,
  "data": {
    "products": [
      {
        "id": 417,
        "title": "Complete Course",
        "description": "Masterclass overview...",
        "image": "https://cdn.example.com/product-417.png",
        "url": "https://example.com/course/complete-course?affiliate=123",
        "price": 199.99,
        "currency": "EUR",
        "type": "App\\Models\\Course",
        "affiliate_coupons": {
          "low": {
            "code": "AFFL-ROCKET15",
            "percent_off": 15,
            "discount_decimal": 0.15,
            "new_price": 169.99,
            "expires_at": "2025-06-30T23:59:59Z"
          },
          "mid": null,
          "high": null
        },
        "introduction_video_url": "https://videos.example.com/course-intro.mp4",
        "category": {
          "name": "Development",
          "slug": "development"
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 5,
      "total": 42,
      "last_page": 9,
      "next_page_url": "https://example.com/api/v1/products?page=2&per_page=5",
      "prev_page_url": null
    }
  }
}
```

If a product represents a bundle, each entry includes a `bundle_products` array describing the included items.

When the product corresponds to a video course (`App\\Models\\Course` morphing to `App\\Models\\VideoCourse`), the API also returns an `introduction_video_url` pointing to the publicly available introduction video, when present. Bundle child entries follow the same rule.

**Bundle response example:**

```json
{
  "success": true,
  "data": {
    "products": [
      {
        "id": 901,
        "title": "Designer Toolkit Bundle",
        "description": "Save on our most popular design courses.",
        "image": "https://cdn.example.com/products/901.png",
        "url": "https://example.com/bundles/designer-toolkit?affiliate=123",
        "price": 129.0,
        "currency": "USD",
        "type": "App\\Models\\ProductBundle",
        "category": {
          "name": "Design",
          "slug": "design"
        },
        "bundle_products": [
          {
            "id": 455,
            "title": "Advanced UI Design",
            "type": "App\\Models\\Course",
            "price": 79.0,
            "currency": "USD",
            "url": "https://example.com/courses/advanced-ui?affiliate=123",
            "affiliate_coupons": {
              "low": {
                "code": "AFFL-UIDESIGN",
                "percent_off": 12.5,
                "discount_decimal": 0.125,
                "new_price": 69.13,
                "expires_at": "2025-06-15T23:59:59Z"
              },
              "mid": {
                "code": "AFFM-UIDESIGN",
                "percent_off": 25,
                "discount_decimal": 0.25,
                "new_price": 59.25,
                "expires_at": null
              },
              "high": null
            },
            "introduction_video_url": "https://videos.example.com/ui-intro.mp4"
          },
          {
            "id": 512,
            "title": "Brand Identity Masterclass",
            "type": "App\\Models\\Course",
            "price": 69.0,
            "currency": "USD",
            "url": "https://example.com/courses/brand-identity?affiliate=123",
            "affiliate_coupons": {
              "low": null,
              "mid": {
                "code": "AFFM-BRAND",
                "percent_off": 22.5,
                "discount_decimal": 0.225,
                "new_price": 53.48,
                "expires_at": "2025-07-01T00:00:00Z"
              },
              "high": {
                "code": "AFFH-BRAND",
                "percent_off": 55,
                "discount_decimal": 0.55,
                "new_price": 31.05,
                "expires_at": null
              }
            },
            "introduction_video_url": "https://videos.example.com/brand-intro.mp4"
          }
        ]
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 1,
      "total": 1,
      "last_page": 1,
      "next_page_url": null,
      "prev_page_url": null
    }
  }
}
```

Requests with unsupported currency codes respond with `422` and `{"success": false, "message": "Unsupported currency, use USD, GBP, EUR, INR, BGN"}`.

#### Retrieve Product

- **Method & Path:** `GET /api/v1/products/{product}`
- **Scope:** `products.view`
- **Path Parameter:** `product` – numeric product ID.
- **Description:** Returns a single published product. Unpublished IDs return `404` (`{"success": false, "message": "Product not found."}`).

**Sample request:**

```bash
curl -X GET \
  -H "Authorization: Bearer YOUR_PLAIN_KEY" \
  "https://example.com/api/v1/products/417?currency_from_ip=8.8.8.8"
```

**Successful response (200):**

```json
{
  "success": true,
  "data": {
    "product": {
      "id": 417,
      "title": "Complete Course",
      "description": "Masterclass overview...",
      "image": "https://cdn.example.com/product-417.png",
      "url": "https://example.com/course/complete-course?affiliate=123",
      "price": 215.49,
      "currency": "GBP",
      "type": "App\\Models\\Course",
      "affiliate_coupons": {
        "low": {
          "code": "AFFL-COURSE",
          "percent_off": 12.5,
          "discount_decimal": 0.125,
          "new_price": 188.55,
          "expires_at": "2025-06-20T23:59:59Z"
        },
        "mid": {
          "code": "AFFM-COURSE",
          "percent_off": 25,
          "discount_decimal": 0.25,
          "new_price": 161.62,
          "expires_at": null
        },
        "high": null
      },
      "introduction_video_url": "https://videos.example.com/course-intro.mp4"
    }
  }
}
```

Bundle payloads again include `bundle_products` with nested items.

Error cases mirror the list endpoint (422 for unsupported currencies, 401 for missing authentication). Unsupported currency requests return `{"success": false, "message": "Unsupported currency, use USD, GBP, EUR, INR, BGN"}`.

---

### Promotions

- **Method & Path:** `GET /api/v1/promotions`
- **Scope:** `promotions.view`
- **Description:** Returns the bundles that are currently available as promotions along with details of any active percentage-based site-wide promotion.
- **Query Parameters:**

| Name | Type | Default | Notes |
| --- | --- | --- | --- |
| `currency_code` | string | `USD` | Three-letter ISO code; used when `currency_from_ip` is omitted or no match is found. |
| `currency_from_ip` | string | — | Optional IP address used to infer the shopper's currency. Overrides `currency_code` when resolvable. |

Bundle entries mirror the fields returned by the products endpoint, including `bundle_products` with nested items. The `percentage_promotion` field is `null` when there is no active percentage promotion.

**Successful response (200):**

```json
{
  "success": true,
  "data": {
    "bundles": [
      {
        "id": 901,
        "title": "Designer Toolkit Bundle",
        "description": "Save on our most popular design courses.",
        "image": "https://cdn.example.com/products/901.png",
        "url": "https://example.com/bundles/designer-toolkit?affiliate=123",
        "price": 129.0,
        "currency": "USD",
        "type": "App\\Models\\ProductBundle",
        "bundle_products": [
          {
            "id": 455,
            "title": "Advanced UI Design",
            "type": "App\\Models\\Course",
            "price": 79.0,
            "currency": "USD",
            "url": "https://example.com/courses/advanced-ui?affiliate=123",
            "affiliate_coupons": {
              "low": {
                "code": "AFFL-UIDESIGN",
                "percent_off": 12.5,
                "discount_decimal": 0.125,
                "new_price": 69.13,
                "expires_at": "2025-06-15T23:59:59Z"
              },
              "mid": {
                "code": "AFFM-UIDESIGN",
                "percent_off": 25,
                "discount_decimal": 0.25,
                "new_price": 59.25,
                "expires_at": null
              },
              "high": null
            }
          }
        ]
      }
    ],
    "percentage_promotion": {
      "id": 17,
      "title": "Spring Savings",
      "text": "25% off all courses",
      "type": "Percentage Promotion",
      "percentage_off": 25,
      "ends_at": "2025-03-10T17:00:00Z"
    }
  }
}
```

Requests with unsupported currencies behave the same as the products endpoints and respond with `422`.

---

### Blog Profiles

List existing profiles for the affiliate account.

- **Method & Path:** `GET /api/v1/blog-profiles`
- **Scope:** `blogs.accounts.manage`
- **Description:** Returns the blog profiles associated with the authenticated affiliate.

**Sample response (200):**

```json
{
  "success": true,
  "data": {
    "profiles": [
      {
        "id": 12,
        "name": "My Affiliate Blog",
        "identifier": "affiliate-blog-1"
      }
    ]
  }
}
```

Create a new profile to publish blogs under a custom author identity.

- **Method & Path:** `POST /api/v1/blog-profiles`
- **Scope:** `blogs.accounts.manage`
- **Description:** Creates a blog profile tied to the authenticated affiliate.
- **Request Body:**

| Field | Type | Rules |
| --- | --- | --- |
| `name` | string | required, max 255 characters |
| `identifier` | string | required, max 255, lowercase alpha-numeric/`-`/`_`, unique per system |

**Sample request:**

```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_PLAIN_KEY" \
  -H "Content-Type: application/json" \
  -d '{
        "name": "My Affiliate Blog",
        "identifier": "affiliate-blog-1"
      }' \
  https://example.com/api/v1/blog-profiles
```

**Successful response (201):**

```json
{
  "success": true,
  "data": {
    "profile": {
      "id": 12,
      "name": "My Affiliate Blog",
      "identifier": "affiliate-blog-1"
    }
  }
}
```

Validation failures respond with `422` and include an `errors` object keyed by field name. Requests authenticated with a valid key but without an associated affiliate receive `401` with `{"success": false, "message": "Affiliate not resolved for the request."}`.

---

### Blogs

- **Method & Path:** `POST /api/v1/blogs`
- **Scope:** `blogs.manage`
- **Description:** Creates a draft blog post owned by the affiliate's user.
- **Required Fields:**

| Field | Type | Rules |
| --- | --- | --- |
| `title` | string | required, trimmed, max 255 |
| `content` | string | required, validated to disallow inline JavaScript |
| `category_slug` | string | required, must exist in `categories.slug` |
| `blog_profile_id` | integer | required, must exist and belong to the affiliate |

If the affiliate user relationship is missing, the API returns `422` with `message: "Affiliate user account is unavailable."`

**Sample request:**

```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_PLAIN_KEY" \
  -H "Content-Type: application/json" \
  -d '{
        "title": "Spring Updates",
        "content": "<p>Latest platform news...</p>",
        "category_slug": 'low-level-programming',
        "blog_profile_id": 12
      }' \
  https://example.com/api/v1/blogs
```

**Successful response (201):**

```json
{
  "success": true,
  "data": {
    "blog": {
      "id": 55,
      "title": "Spring Updates",
      "slug": "spring-updates",
      "published": false
    }
  }
}
```

Validation failures respond with `422` and include field-specific errors. If the affiliate hasn't been approved for blog management, the API replies with `403` and `{"success": false, "message": "Blog management scopes require approval."}`.

#### Update Blog

- **Method & Path:** `PUT /api/v1/blogs/{blog}` or `PATCH /api/v1/blogs/{blog}`
- **Scope:** `blogs.manage`
- **Description:** Updates a blog post owned by the affiliate's user. Any update automatically sets `published` back to `false` so the Dragon Zap team can review the changes.
- **Path Parameter:** `blog` – blog ID belonging to the affiliate user.
- **Request Body:** Provide at least one of the following fields.

| Field | Type | Rules |
| --- | --- | --- |
| `title` | string | optional, trimmed, max 255 |
| `content` | string | optional, validated with the same no-JavaScript rule |
| `category_slug` | string  | optional, must exist in `categories.slug` |
| `blog_profile_id` | integer | optional, must belong to the affiliate |

**Sample request:**

```bash
curl -X PATCH \
  -H "Authorization: Bearer YOUR_PLAIN_KEY" \
  -H "Content-Type: application/json" \
  -d '{
        "title": "Spring Updates (Revised)",
        "content": "<p>Updated highlights...</p>"
      }' \
  https://example.com/api/v1/blogs/55
```

**Successful response (200):**

```json
{
  "success": true,
  "data": {
    "blog": {
      "id": 55,
      "title": "Spring Updates (Revised)",
      "slug": "spring-updates",
      "published": false
    }
  }
}
```

Validation failures return `422` with field errors, including an additional `payload` error when no updatable fields are provided. Updating a blog that doesn't belong to the affiliate results in `404` with `{"success": false, "message": "Blog not found."}`.

---

### Webhooks

All webhook management routes require the `webhooks.manage` scope.

#### List Webhooks

- **Method & Path:** `GET /api/v1/webhooks`
- **Description:** Lists the affiliate's webhooks and returns the supported event types.

**Sample response (200):**

```json
{
  "success": true,
  "data": {
    "webhooks": [
      {
        "id": 9,
        "event": "product.published",
        "url": "https://hooks.example.com/affiliate-product",
        "created_at": "2024-06-07T10:15:21.000000Z",
        "updated_at": "2024-06-07T10:15:21.000000Z"
      }
    ],
    "supported_events": [
      "product.published",
      "product.updated",
      "product.unpublished",
      "blog.published",
      "blog.unpublished",
      "promotion.created"
    ]
  }
}
```

Requests without the scope return `403` with `message: "Insufficient scope."`

#### Create Webhook

- **Method & Path:** `POST /api/v1/webhooks`
- **Description:** Registers a new webhook for the affiliate.
- **Request Body:**

| Field | Type | Rules |
| --- | --- | --- |
| `event` | string | required, one of `product.published`, `product.updated`, `product.unpublished`, `blog.published`, `blog.unpublished`, `promotion.created` |
| `url` | string | required, valid URL, max 2048 characters, unique per affiliate/event |

> **Note:** `blog.published` and `blog.unpublished` fire only when Dragon Zap reviews an affiliate-authored blog in the backend. `promotion.created` fires when a new public promotion is published and includes the promotion payload described below.

**Sample request:**

```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_PLAIN_KEY" \
  -H "Content-Type: application/json" \
  -d '{
        "event": "product.updated",
        "url": "https://hooks.example.com/product-updated"
      }' \
  https://example.com/api/v1/webhooks
```

**Successful response (201):**

```json
{
  "success": true,
  "data": {
    "id": 10,
    "event": "product.updated",
    "url": "https://hooks.example.com/product-updated",
    "secret": "TBX9uEM0oGx4vOu1DaNd0L0kNhj4y4pyrPZQ2ZlXR7d9I3dO1HS5kM2cZB7wQ5s6",
    "created_at": "2024-06-07T10:20:00.000000Z",
    "updated_at": "2024-06-07T10:20:00.000000Z"
  }
}
```

The `secret` value is only returned at creation time. Store it securely—it is not exposed in subsequent list responses or through any other endpoint.

Validation errors respond with `422` and list the failing fields. If the request lacks an associated affiliate, the API responds with `401` and `{"success": false, "message": "Affiliate not resolved for the request."}`.

#### Delete Webhook

- **Method & Path:** `DELETE /api/v1/webhooks/{webhook}`
- **Description:** Deletes a webhook owned by the affiliate.
- **Path Parameter:** `webhook` – webhook ID. Only webhooks belonging to the authenticated affiliate can be removed.

**Sample request:**

```bash
curl -X DELETE \
  -H "Authorization: Bearer YOUR_PLAIN_KEY" \
  https://example.com/api/v1/webhooks/10
```

**Successful response (200):**

```json
{
  "success": true
}
```

If the webhook does not belong to the affiliate, the API returns `404` with `message: "Webhook not found."`

---

### Webhook Event Payloads

When a subscribed event fires, the API delivers a JSON payload via POST to your configured URL. Timestamps use ISO 8601 format and each payload includes the `event`, `webhook_id`, and `occurred_at` keys so you can deduplicate deliveries.

Every delivery includes an `X-Affiliate-Webhook-Key` header with the webhook's secret so receivers can verify the request's origin.

**Example `product.updated` payload:**

```json
{
  "event": "product.updated",
  "webhook_id": 9,
  "occurred_at": "2024-06-07T10:21:15.482Z",
  "product": {
    "id": 417,
    "title": "Complete Course",
    "description": "Updated course description.",
    "image_url": "https://cdn.example.com/images/complete-course.png",
    "type": "Course",
    "published": true,
    "price": {
      "amount": 215.49,
      "currency": "USD"
    },
    "teachers": [
      {
        "id": 12,
        "name": "Jamie Rivera"
      }
    ]
  }
}
```

**Example `blog.published` payload:**

```json
{
  "event": "blog.published",
  "webhook_id": 12,
  "occurred_at": "2024-06-07T11:02:43.018Z",
  "blog": {
    "id": 58,
    "title": "Spring Updates",
    "slug": "spring-updates",
    "url": "https://example.com/blog/spring-updates",
    "published": true,
    "updated_at": "2024-06-07T11:02:42.000000Z",
    "profile": {
      "id": 12,
      "name": "My Affiliate Blog",
      "identifier": "affiliate-blog-1"
    },
    "author": {
      "id": 204,
      "name": "Taylor Gray",
      "email": "taylor@example.com"
    }
  }
}
```

**Example `blog.unpublished` payload:**

```json
{
  "event": "blog.unpublished",
  "webhook_id": 12,
  "occurred_at": "2024-06-09T15:47:03.551Z",
  "blog": {
    "id": 58,
    "title": "Spring Updates",
    "slug": "spring-updates",
    "url": "https://example.com/blog/spring-updates",
    "published": false,
    "unpublished_reason": "Content requires updates",
    "updated_at": "2024-06-09T15:47:02.000000Z",
    "profile": {
      "id": 12,
      "name": "My Affiliate Blog",
      "identifier": "affiliate-blog-1"
    },
    "author": {
      "id": 204,
      "name": "Taylor Gray",
      "email": "taylor@example.com"
    }
  }
}
```

**Example `promotion.created` payload:**

```json
{
  "event": "promotion.created",
  "webhook_id": 15,
  "occurred_at": "2025-02-04T12:05:36.214Z",
  "promotion": {
    "id": 42,
    "title": "Winter Warmers",
    "text": "20% off all bundles",
    "ends_at": "2025-02-18T23:59:59Z",
    "type": "Percentage Promotion",
    "details": {
      "percentage_off": 20
    }
  }
}
```

Payloads may include additional fields over time; consumers should ignore unknown keys.

---

## Error Handling Summary

- **401 Unauthorized:** missing or invalid API key (`{"success": false, "message": "API key is missing."}` or `"API key is invalid."`) or inactive affiliate account (`{"success": false, "message": "Affiliate account is not active."}`).
- **403 Forbidden:** authenticated but lacking scope (`{"success": false, "message": "Insufficient scope."}`).
- **404 Not Found:** resource does not exist or is not owned by the affiliate.
- **422 Unprocessable Entity:** validation error; payload includes an `errors` object or a descriptive `message`.

For simple connectivity checks, call `GET /api/v1/test` with your API key. The endpoint confirms authentication, echoes the scopes assigned to the key, and lists any approval-gated scopes you still need to request.
