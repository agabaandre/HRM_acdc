# Africa CDC Staff Tracker API Documentation

## Overview

The Staff Tracker API provides access to various data endpoints from the Africa CDC staff management system. All endpoints require HTTP Basic Authentication using your CodeIgniter application credentials.

**Base URL:** `https://cbp.africacdc.org/staff/share`

**Version:** 1.0.0

> 📖 **For general project documentation**, see the [Main Project README](../../../README.md) which provides an overview of the entire Africa CDC Central Business Platform (CBP) system, including the Staff Portal, APM module, and Finance module.

---

## Authentication

All API endpoints require **HTTP Basic Authentication**. Use your CodeIgniter application email and password.

### Authentication Header Format

```
Authorization: Basic base64(username:password)
```

### Example using cURL

```bash
curl -u "your_email@example.com:your_password" \
  https://cbp.africacdc.org/staff/share/service_requests
```

### Example using JavaScript (fetch)

```javascript
const username = 'your_email@example.com';
const password = 'your_password';
const credentials = btoa(`${username}:${password}`);

fetch('https://cbp.africacdc.org/staff/share/service_requests', {
  headers: {
    'Authorization': `Basic ${credentials}`
  }
})
.then(response => response.json())
.then(data => console.log(data));
```

---

## Response Format

All endpoints return JSON responses in the following format:

### Success Response

```json
{
  "success": true,
  "data": [...],
  "total": 100,
  "count": 10
}
```

### Error Response

```json
{
  "success": false,
  "error": "Error message here"
}
```

---

## Status Codes

- **200 OK** - Request successful
- **401 Unauthorized** - Invalid or missing authentication credentials
- **500 Internal Server Error** - Server error occurred

---

## Endpoints

### 1. Service Requests

Get service requests with optional filtering.

**Endpoint:** `GET /share/service_requests`

**Query Parameters:**

| Parameter | Type | Required | Description | Example Values |
|-----------|------|----------|-------------|----------------|
| `overall_status` | string | No | Filter by status | `approved`, `pending`, `returned`, `draft`, `cancelled` |
| `division_id` | integer | No | Filter by division ID | `1`, `2`, `3` |
| `staff_id` | integer | No | Filter by staff ID | `123` |
| `date_from` | date | No | Filter from date (Y-m-d) | `2024-01-01` |
| `date_to` | date | No | Filter to date (Y-m-d) | `2024-12-31` |
| `limit` | integer | No | Limit number of results | `10`, `50`, `100` |
| `offset` | integer | No | Offset for pagination | `0`, `10`, `20` |
| `order_by` | string | No | Field to order by | `id`, `request_date`, `created_at` |
| `order_dir` | string | No | Order direction | `ASC`, `DESC` |

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/service_requests?overall_status=approved&division_id=1&limit=10"
```

**Example Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "request_number": "SR-2024-001",
      "request_date": "2024-01-15",
      "staff_id": 123,
      "service_title": "IT Support Request",
      "overall_status": "approved",
      ...
    }
  ],
  "total": 50,
  "count": 10
}
```

---

### 2. Activities

Get activities with optional filtering.

**Endpoint:** `GET /share/activities`

**Query Parameters:**

| Parameter | Type | Required | Description | Example Values |
|-----------|------|----------|-------------|----------------|
| `overall_status` | string | No | Filter by status | `approved`, `pending`, `returned`, `draft`, `cancelled` |
| `division_id` | integer | No | Filter by division ID | `1`, `2`, `3` |
| `staff_id` | integer | No | Filter by staff ID | `123` |
| `is_single_memo` | boolean | No | Filter single memo activities | `true`, `false` |
| `date_from` | date | No | Filter from date (Y-m-d) | `2024-01-01` |
| `date_to` | date | No | Filter to date (Y-m-d) | `2024-12-31` |
| `limit` | integer | No | Limit number of results | `10`, `50`, `100` |
| `offset` | integer | No | Offset for pagination | `0`, `10`, `20` |
| `order_by` | string | No | Field to order by | `id`, `date_from`, `created_at` |
| `order_dir` | string | No | Order direction | `ASC`, `DESC` |

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/activities?overall_status=approved&limit=10"
```

---

### 3. Non-Travel Memos

Get non-travel memos (single memos) with optional filtering.

**Endpoint:** `GET /share/non_travel_memos`

**Query Parameters:**

| Parameter | Type | Required | Description | Example Values |
|-----------|------|----------|-------------|----------------|
| `overall_status` | string | No | Filter by status | `approved`, `pending`, `returned`, `draft`, `cancelled` |
| `division_id` | integer | No | Filter by division ID | `1`, `2`, `3` |
| `staff_id` | integer | No | Filter by staff ID | `123` |
| `date_from` | date | No | Filter from date (Y-m-d) | `2024-01-01` |
| `date_to` | date | No | Filter to date (Y-m-d) | `2024-12-31` |
| `limit` | integer | No | Limit number of results | `10`, `50`, `100` |
| `offset` | integer | No | Offset for pagination | `0`, `10`, `20` |
| `order_by` | string | No | Field to order by | `id`, `date_from`, `created_at` |
| `order_dir` | string | No | Order direction | `ASC`, `DESC` |

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/non_travel_memos?overall_status=approved&limit=10"
```

---

### 4. Special Memos

Get special memos with optional filtering.

**Endpoint:** `GET /share/special_memos`

**Query Parameters:**

| Parameter | Type | Required | Description | Example Values |
|-----------|------|----------|-------------|----------------|
| `overall_status` | string | No | Filter by status | `approved`, `pending`, `returned`, `draft`, `cancelled` |
| `division_id` | integer | No | Filter by division ID | `1`, `2`, `3` |
| `staff_id` | integer | No | Filter by staff ID | `123` |
| `date_from` | date | No | Filter from date (Y-m-d) | `2024-01-01` |
| `date_to` | date | No | Filter to date (Y-m-d) | `2024-12-31` |
| `limit` | integer | No | Limit number of results | `10`, `50`, `100` |
| `offset` | integer | No | Offset for pagination | `0`, `10`, `20` |
| `order_by` | string | No | Field to order by | `id`, `date_from`, `created_at` |
| `order_dir` | string | No | Order direction | `ASC`, `DESC` |

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/special_memos?overall_status=approved&limit=10"
```

---

### 5. Request ARFs

Get request ARFs (Advance Request Forms) with optional filtering.

**Endpoint:** `GET /share/request_arfs`

**Query Parameters:**

| Parameter | Type | Required | Description | Example Values |
|-----------|------|----------|-------------|----------------|
| `overall_status` | string | No | Filter by status | `approved`, `pending`, `returned`, `draft`, `cancelled` |
| `division_id` | integer | No | Filter by division ID | `1`, `2`, `3` |
| `staff_id` | integer | No | Filter by staff ID | `123` |
| `date_from` | date | No | Filter from date (Y-m-d) | `2024-01-01` |
| `date_to` | date | No | Filter to date (Y-m-d) | `2024-12-31` |
| `limit` | integer | No | Limit number of results | `10`, `50`, `100` |
| `offset` | integer | No | Offset for pagination | `0`, `10`, `20` |
| `order_by` | string | No | Field to order by | `id`, `request_date`, `created_at` |
| `order_dir` | string | No | Order direction | `ASC`, `DESC` |

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/request_arfs?overall_status=approved&limit=10"
```

---

### 6. Change Requests

Get change requests with optional filtering.

**Endpoint:** `GET /share/change_requests`

**Query Parameters:**

| Parameter | Type | Required | Description | Example Values |
|-----------|------|----------|-------------|----------------|
| `overall_status` | string | No | Filter by status | `approved`, `pending`, `returned`, `draft`, `cancelled` |
| `parent_memo_model` | string | No | Filter by parent memo model | `App\Models\SpecialMemo`, `App\Models\Activity` |
| `parent_memo_id` | integer | No | Filter by parent memo ID | `123` |
| `staff_id` | integer | No | Filter by staff ID | `123` |
| `date_from` | date | No | Filter from date (Y-m-d) | `2024-01-01` |
| `date_to` | date | No | Filter to date (Y-m-d) | `2024-12-31` |
| `limit` | integer | No | Limit number of results | `10`, `50`, `100` |
| `offset` | integer | No | Offset for pagination | `0`, `10`, `20` |
| `order_by` | string | No | Field to order by | `id`, `created_at` |
| `order_dir` | string | No | Order direction | `ASC`, `DESC` |

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/change_requests?overall_status=approved&parent_memo_model=App\Models\SpecialMemo&limit=10"
```

---

### 7. Fund Codes

Get fund codes (budget codes) with optional filtering.

**Endpoint:** `GET /share/fund_codes`

**Query Parameters:**

| Parameter | Type | Required | Description | Example Values |
|-----------|------|----------|-------------|----------------|
| `fund_type_id` | integer | No | Filter by fund type ID | `1`, `2`, `3` |
| `division_id` | integer | No | Filter by division ID | `1`, `2`, `3` |
| `funder_id` | integer | No | Filter by funder ID | `1`, `2`, `3` |
| `year` | integer | No | Filter by year | `2024`, `2025` |
| `is_active` | integer | No | Filter by active status (0 or 1) | `0`, `1` |
| `code` | string | No | Search by fund code | `FC001`, `FC002` |
| `limit` | integer | No | Limit number of results | `10`, `50`, `100` |
| `offset` | integer | No | Offset for pagination | `0`, `10`, `20` |
| `order_by` | string | No | Field to order by | `fc.code`, `fc.year`, `fc.id` |
| `order_dir` | string | No | Order direction | `ASC`, `DESC` |

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/fund_codes?fund_type_id=1&division_id=1&year=2025&is_active=1&limit=10"
```

**Example Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "funder_id": 1,
      "year": 2025,
      "code": "FC001",
      "activity": "Activity Description",
      "fund_type_id": 1,
      "division_id": 1,
      "cost_centre": "CC001",
      "amert_code": "AM001",
      "fund": "Fund Name",
      "is_active": 1,
      "budget_balance": "100000.00",
      "approved_budget": "150000.00",
      "uploaded_budget": "150000.00",
      "fund_type_name": "Donor",
      "division_name": "Administration",
      "funder_name": "Funder Name",
      "created_at": "2024-01-01 00:00:00",
      "updated_at": "2024-01-01 00:00:00"
    }
  ],
  "total": 50,
  "count": 10
}
```

---

### 8. Fund Types

Get fund types with optional filtering.

**Endpoint:** `GET /share/fund_types`

**Query Parameters:**

| Parameter | Type | Required | Description | Example Values |
|-----------|------|----------|-------------|----------------|
| `id` | integer | No | Filter by fund type ID | `1`, `2`, `3` |
| `name` | string | No | Search by fund type name | `Donor`, `Internal` |
| `limit` | integer | No | Limit number of results | `10`, `50`, `100` |
| `offset` | integer | No | Offset for pagination | `0`, `10`, `20` |
| `order_by` | string | No | Field to order by | `ft.name`, `ft.id` |
| `order_dir` | string | No | Order direction | `ASC`, `DESC` |

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/fund_types?name=Donor"
```

**Example Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Donor",
      "created_at": "2024-01-01 00:00:00",
      "updated_at": "2024-01-01 00:00:00"
    }
  ],
  "total": 1,
  "count": 1
}
```

---

### 9. Divisions

Get all divisions.

**Endpoint:** `GET /share/divisions`

**Query Parameters:** None

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/divisions"
```

**Example Response:**

```json
[
  {
    "division_id": 1,
    "division_name": "Administration",
    "abbrev": "ADMIN",
    ...
  }
]
```

---

### 10. Directorates

Get all directorates.

**Endpoint:** `GET /share/directorates`

**Query Parameters:** None

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/directorates"
```

---

### 9. Staff

Get staff information with detailed contract and profile data.

**Endpoint:** `GET /share/staff`

**Query Parameters:** None

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/staff"
```

**Example Response:**

```json
[
  {
    "start_date": "2024-01-01",
    "end_date": "2024-12-31",
    "SAPNO": "SAP001",
    "status": "Active",
    "duty_station_name": "Addis Ababa",
    "title": "Mr.",
    "name": "John Doe",
    "grade": "P5",
    "date_of_birth": "1980-01-01",
    "gender": "Male",
    "job_name": "Program Manager",
    "contract_type": "Fixed Term",
    "nationality": "Ethiopian",
    "division_name": "Administration",
    "first_supervisor_email": "supervisor@example.com",
    "second_supervisor_email": "supervisor2@example.com",
    "work_email": "john.doe@example.com",
    ...
  }
]
```

---

### 10. Visualise Staff Data

Get staff data formatted for visualization purposes. Returns staff with active contracts (status IDs: 1, 2, 3, 7) excluding division ID 27.

**Endpoint:** `GET /share/visualise`

**Query Parameters:** None

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/visualise"
```

**Example Response:**

```json
[
  {
    "start_date": "2024-01-01",
    "end_date": "2024-12-31",
    "funder": "World Bank",
    "SAPNO": "SAP001",
    "status": "Active",
    "duty_station_name": "Addis Ababa",
    "title": "Mr.",
    "name": "John Doe",
    "grade": "P5",
    "date_of_birth": "1980-01-01",
    "gender": "Male",
    "job_name": "Program Manager",
    "contract_type": "Fixed Term",
    "nationality": "Ethiopian",
    "division_name": "Administration",
    "first_supervisor_email": "supervisor@example.com",
    "second_supervisor_email": "supervisor2@example.com",
    "work_email": "john.doe@example.com",
    ...
  }
]
```

---

### 11. Get Current Staff

Get current staff with filters.

**Endpoint:** `GET /share/get_current_staff`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `limit` | integer | No | Limit number of results |
| `start` | integer | No | Start offset for pagination |

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/get_current_staff?limit=10&start=0"
```

**Example Response:**

```json
[
  {
    "staff_id": 123,
    "name": "John Doe",
    "work_email": "john.doe@example.com",
    "division_name": "Administration",
    ...
  }
]
```

---

### 12. Get Staff Signature

Get staff signature image as base64-encoded data.

**Endpoint:** `GET /share/get_signature`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `staff_id` | integer | Yes | Staff ID to retrieve signature for |

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/get_signature?staff_id=123"
```

**Example Response:**

```json
{
  "success": true,
  "staff_id": 123,
  "signature_data": "iVBORw0KGgoAAAANSUhEUgAA..."
}
```

**Note:** The signature file must be less than 2MB. The signature_data is base64-encoded image data.

---

### 13. Get Staff Photo

Get staff photo image as base64-encoded data.

**Endpoint:** `GET /share/get_photo`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `staff_id` | integer | Yes | Staff ID to retrieve photo for |

**Example Request:**

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/get_photo?staff_id=123"
```

**Example Response:**

```json
{
  "success": true,
  "staff_id": 123,
  "photo_data": "iVBORw0KGgoAAAANSUhEUgAA..."
}
```

**Note:** The photo file must be less than 2MB. The photo_data is base64-encoded image data.

---

### 14. Validate Session

Validate a Bearer token session for Laravel app integration.

**Endpoint:** `POST /share/validate_session`

**Headers:**

| Header | Type | Required | Description |
|--------|------|----------|-------------|
| `Authorization` | string | Yes | Bearer token (base64-encoded JSON) |

**Example Request:**

```bash
curl -X POST \
  -H "Authorization: Bearer eyJzdGFmZl9pZCI6MTIzfQ==" \
  "https://cbp.africacdc.org/staff/share/validate_session"
```

**Example Response (Valid Session):**

```json
{
  "success": true,
  "message": "Session is valid",
  "session_expired": false,
  "user": {
    "staff_id": 123,
    "name": "John Doe",
    "email": "john.doe@example.com"
  }
}
```

**Example Response (Invalid Session):**

```json
{
  "success": false,
  "message": "Invalid token format",
  "session_expired": true
}
```

---

### 15. Refresh Token

Refresh a Bearer token for Laravel app integration. Extends token expiry by 2 hours.

**Endpoint:** `POST /share/refresh_token`

**Headers:**

| Header | Type | Required | Description |
|--------|------|----------|-------------|
| `Authorization` | string | Yes | Bearer token (base64-encoded JSON) |

**Example Request:**

```bash
curl -X POST \
  -H "Authorization: Bearer eyJzdGFmZl9pZCI6MTIzfQ==" \
  "https://cbp.africacdc.org/staff/share/refresh_token"
```

**Example Response:**

```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "token": "eyJzdGFmZl9pZCI6MTIzLCJ0b2tlbl9pc3N1ZWRfYXQiOjE3MDAwMDAwMDAsInRva2VuX2V4cGlyZXNfYXQiOjE3MDAwNzIwMDB9",
  "expires_at": "2024-11-15T14:00:00+00:00"
}
```

---

### 16. API Documentation

Get interactive API documentation.

**Endpoint:** `GET /share/api_docs`

**Query Parameters:** None

**Example Request:**

```bash
curl "https://cbp.africacdc.org/staff/share/api_docs"
```

**Note:** This endpoint does not require authentication.

---

## Common Use Cases

### Get All Approved Service Requests

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/service_requests?overall_status=approved"
```

### Get Pending Activities for a Division

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/activities?overall_status=pending&division_id=1"
```

### Get Approved Special Memos with Pagination

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/special_memos?overall_status=approved&limit=20&offset=0"
```

### Get Change Requests for a Specific Memo

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/change_requests?parent_memo_model=App\Models\SpecialMemo&parent_memo_id=123"
```

### Get Fund Codes by Fund Type and Division

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/fund_codes?fund_type_id=1&division_id=1&year=2025&is_active=1"
```

### Get All Fund Types

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/fund_types"
```

### Get Staff Signature

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/get_signature?staff_id=123"
```

### Get Staff Photo

```bash
curl -u "email:password" \
  "https://cbp.africacdc.org/staff/share/get_photo?staff_id=123"
```

### Validate Session Token

```bash
curl -X POST \
  -H "Authorization: Bearer eyJzdGFmZl9pZCI6MTIzfQ==" \
  "https://cbp.africacdc.org/staff/share/validate_session"
```

### Refresh Session Token

```bash
curl -X POST \
  -H "Authorization: Bearer eyJzdGFmZl9pZCI6MTIzfQ==" \
  "https://cbp.africacdc.org/staff/share/refresh_token"
```

---

## Error Handling

### Authentication Error (401)

```json
{
  "status": false,
  "message": "Invalid credentials"
}
```

### Server Error (500)

```json
{
  "success": false,
  "error": "Database error: [error message]"
}
```

---

## Rate Limiting

Currently, there are no rate limits imposed on the API. However, please use the API responsibly and implement appropriate caching in your applications.

---

## Support

For API support or questions, please contact the Africa CDC IT team.

---

## Related Documentation

- 📚 [Main Project README](../../../README.md) - Overview of the Africa CDC Central Business Platform (CBP)
- 👥 [Staff Portal Documentation](../../../assets/ENVIRONMENT_VARIABLES.md) - Configuration and setup guides
- 📋 [APM Documentation](../../../apm/documentation/README.md) - Laravel Approvals Management System
- 💰 [Finance Documentation](../../../finance/documentation/README.md) - Node.js/React Finance Module
- 📖 [Main Documentation Hub](../../../documentation/README.md) - Central documentation index

---

## Changelog

### Version 1.2.0 (2025-01-XX)
- Added comprehensive documentation for all API endpoints
- Documented session management endpoints (validate_session, refresh_token)
- Documented staff data endpoints (staff, visualise, get_current_staff)
- Documented media endpoints (get_signature, get_photo)
- Fixed endpoint numbering and organization
- Added examples for all endpoints in Common Use Cases section
- Added links to main project documentation

### Version 1.1.0 (2025-01-XX)
- Added endpoints for fund codes and fund types
- Added filtering by fund_type_id, division_id, funder_id, year, and is_active for fund codes
- Added filtering by id and name for fund types

### Version 1.0.0 (2024-11-28)
- Initial API release
- Added endpoints for service requests, activities, memos, ARFs, and change requests
- Added filtering by overall_status, division_id, staff_id, and date ranges
- Added pagination support
- Added API documentation endpoint

