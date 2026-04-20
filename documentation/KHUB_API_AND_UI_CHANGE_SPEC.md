# Africa Health KnowledgeHub — API & UI change spec (handoff)

This repository does **not** contain the Khub backend or frontend. Use this document in the **KnowledgeHub** project to implement features and update the published OpenAPI UI at [https://khub.africacdc.org/docs/#/](https://khub.africacdc.org/docs/#/).

---

## 1. Members — list regions

**Goal:** Document and expose a **regions** list under the Members area of the API (for dropdowns, filters, onboarding).

**Suggested endpoint**

- `GET /api/members/regions`  
  **Auth:** public or same as other reference lists (align with existing `/api/regions` if one exists—deduplicate).

**Response (example)**

```json
{
  "data": [
    { "id": 1, "name": "Central Africa", "code": "CA", "sort_order": 1 }
  ],
  "status": 200,
  "message": "Regions retrieved successfully"
}
```

**OpenAPI:** Add tag `Members`, path `/api/members/regions`, schema `RegionResource`.

---

## 2. Communities — only communities with at least 10 members

**Goal:** `GET /api/communities` should be able to return **only** communities where `members_count >= 10` (per your sample payload, `members_count` is already on each item).

**Suggested query parameter**

- `min_members=10` (integer, optional; default = no filter for backward compatibility).

**Implementation notes**

- Apply in the Eloquent query / repository: `having('members_count', '>=', $min)` or subquery count, consistent with how `members_count` is computed today.
- Document default behaviour: omitting `min_members` keeps current listing (e.g. includes small CoPs).

**OpenAPI:** On `GET /api/communities`, add parameter `min_members` and description.

---

## 3. Current user — forums they belong to

**Goal:** For authenticated users (`/me`), return **forums** the user is a member of (parallel to communities membership if applicable).

**Suggested endpoint**

- `GET /api/users/me/forums`  
  **Auth:** `Authorization: Bearer …` (same as `/api/users/me`).

**Response (example)**

```json
{
  "data": [
    {
      "id": 123,
      "title": "…",
      "slug": "…",
      "community_id": null,
      "user_role": "member",
      "joined_at": "2025-01-01T00:00:00.000000Z"
    }
  ],
  "status": 200,
  "message": "Forums retrieved successfully"
}
```

**OpenAPI:** Under tag `Users` or `Members`, document security `bearerAuth` and 401.

---

## 4. Authors lookup — HTTP 500 fix

**Goal:** The **authors** lookup endpoint used by search/UI returns **500**; fix server-side and document the contract.

**Checklist (typical causes)**

- Validate route parameters (`id` / `slug`) before querying; return **404** JSON instead of throwing.
- Eager-load relations used in the resource (`with([...])`) to avoid null dereference on missing author or pivot.
- Wrap only expected domain failures; log stack trace with request id for unexpected errors.
- Ensure response shape matches what the SPA expects (same `status` / `message` envelope as `/api/communities`).

**OpenAPI:** Document success and error responses (`400`, `404`, `422`); remove or fix any example that triggers 500 in Swagger “Try it out”.

---

## 5. Member states / countries — aggregate stats (API)

**Goal:** One endpoint that mirrors what the **country details** page needs (like [countries/details?state=9](https://khub.africacdc.org/countries/details?state=9)): health/stat block **plus** Khub engagement metrics for that member state.

**Suggested endpoint**

- `GET /api/countries/{country_id}/stats`  
  or (if `state` is the public query param)  
- `GET /api/member-states/{id}/stats`  
  with optional alias `GET /api/countries/stats?state={id}` for parity with the web `state` query.

**Response (example)**

```json
{
  "country": {
    "id": 9,
    "name": "Uganda",
    "iso_code": "UG",
    "iso3_code": "UGA"
  },
  "demographics": {
    "population": 1300,
    "urban_population_pct": 600,
    "total_fertility_rate": 190,
    "crude_death_rate_per_1000": 100
  },
  "khub": {
    "publications_total": 240,
    "unique_users_enrolled": 1520,
    "forum_discussions_from_country": 46
  },
  "status": 200,
  "message": "Country statistics retrieved successfully"
}
```

**Metric definitions (implement explicitly in code + docs)**

| Field | Suggested definition |
|--------|----------------------|
| `publications_total` | Count of published resources **associated** to this member state (same join/filter as the “Published resources” list on the country page). |
| `unique_users_enrolled` | Distinct **users** with an active account where profile / `country_id` / primary affiliation matches this member state (adjust to your schema). |
| `forum_discussions_from_country` | Count of **forum threads or posts** “originating from” this country—define as: author’s country at time of post, or forum’s `country_id`, or tag; pick one rule and document it. |

**OpenAPI:** New schema `CountryStatsResponse`, tag `Countries` or `Member states`.

---

## 6. Country details **web** page — UI improvements

**Page:** e.g. `/countries/details?state={id}`.

**Add a summary strip (above “Published resources” / forums)** showing:

1. **Total publications** associated to the country (same count as `khub.publications_total` from the new API, or computed client-side from one list endpoint).
2. **Unique users enrolled** on Khub for that country (`unique_users_enrolled`).
3. **Forum discussions originating from that country** (`forum_discussions_from_country`).

**Frontend:** Prefer a single call to `GET /api/countries/{id}/stats` (or `?state=`) to avoid triple requests and keep numbers consistent with the API contract.

---

## 7. Swagger / OpenAPI (`/docs`)

After implementation:

1. Add paths and schemas for: `GET /api/members/regions`, `GET /api/communities` (`min_members`), `GET /api/users/me/forums`, fixed **authors** path, `GET /api/countries/{id}/stats` (or chosen URL).
2. Regenerate static JSON if the site uses a build step; redeploy so [https://khub.africacdc.org/docs/#/](https://khub.africacdc.org/docs/#/) reflects changes.

---

## Reference payloads (from production)

- Communities list shape: see saved sample `uploads/communities-1.md` in Cursor (or call `GET https://khub.africacdc.org/api/communities`).
- Country details page content: see `uploads/details-2.md` (Uganda example with “Country statistics” and publication list).

---

## Next step for developers

Clone or open the **KnowledgeHub** application repository (Laravel or stack used by `khub.africacdc.org`), implement the endpoints and UI above, add tests, then update OpenAPI and deploy.
