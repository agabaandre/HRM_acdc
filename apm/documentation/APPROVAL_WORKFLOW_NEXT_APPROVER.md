# Workflow roles and next-approver routing (`getNextApprover`)

This document describes how APM decides **who comes next** in the default forward workflow (`forward_workflow_id = 1`). The logic lives in **`App\Services\ApprovalService::getNextApprover()`** (`apm/app/Services/ApprovalService.php`). Models (matrices, non-travel memos, etc.) store **`approval_level`**, which must match an enabled row’s **`approval_order`** on `workflow_definition` for the same **`workflow_id`**.

> **Environment note:** Role names and orders below match a typical `workflow_id = 1` configuration (enabled rows). Your database may differ; inspect `workflow_definition` for the authoritative list.

---

## Canonical role chain (`workflow_id = 1`)

| Order | `fund_type` | Category (if any) | Role (typical) | Division-specific |
|------:|-------------|-------------------|----------------|-------------------|
| 1 | — | — | Head of Division (HOD) | Yes |
| 2 | — | — | Director | Yes |
| 3 | 2 (extramural) | — | Grants Officer | No |
| 4 | 1 (intramural) | — | PIU | No |
| 5 | 1 (intramural) | — | Finance Officer | Yes |
| 6 | — | — | Ag. Director Finance | No |
| 7 | — | Operations | Head of Operations | No |
| 8 | — | Programs | Head of Programs | No |
| 9 | — | Other | Ag. Deputy Director General (DDG) | No |
| 10 | — | — | OIC Deputy Chief of Staff | No |
| 11 | — | — | Chief of Staff | No |
| 12 | — | — | Director General (DG) | No |

- **`fund_type`:** `1` = intramural track, `2` = extramural track (used when multiple definitions share concepts at adjacent orders).
- **Category rows (7–9):** Used when routing jumps to a **category-specific** approver (see below).

Other document types may use a different **`workflow_id`** via `WorkflowModel::getWorkflowIdForModel()`; the same `getNextApprover` rules apply **for that workflow’s** `workflow_definition` rows.

---

## Inputs the resolver uses

| Input | Meaning |
|--------|--------|
| **`$model->forward_workflow_id`** | Which workflow definition set to read (defaults to `1` if null inside the method). |
| **`$model->approval_level`** | Current step; matched to `approval_order` on `workflow_definition`. |
| **`$model->division`** | Division category, `director_id`, `division_head`, allowed-division lists, etc. |
| **`$division_has_director`** | `division` exists and `director_id` is set. Blocks “external-only” shortcuts through the directorate (orders 1–5). |
| **`$has_intramural` / `$has_extramural`** | From model accessors when present (`getHasIntramuralAttribute` / `getHasExtramuralAttribute`), e.g. `HasApprovalWorkflow` (activities, `budget_breakdown`, etc.). Otherwise extramural defaults false, intramural defaults **true** only when the accessor does not exist. |
| **`allowed_funders` / `divisions` on definitions** | JSON lists on `workflow_definition`; used from order **≥ 3** to skip a row when the memo does not match. |

**Funder IDs on the memo** are resolved in **`getModelFunderIds()`**: matrix/activity budgets → fund codes → `funder_id`; `RequestARF` → `funder_id`; memos with **`budget_id`** or **`budget_breakdown`** (numeric fund-code keys) → `FundCode` → `funder_id`.

---

## High-level flow (in order of evaluation)

1. **Load current definition** for `workflow_id` + `approval_order = approval_level` (first match if duplicates exist—see model-specific `workflow_definition` accessors on some models).

2. **Category / `triggers_category_check` shortcuts** may return **`getCategoryApprover()`** early (Head of Operations, Head of Programs, or DDG by division category) when flags and levels say the memo should jump to category heads—**not** while the division still has a director and the memo is still in the **directorate band (levels 1–5)**, so the directorate chain is not skipped incorrectly.

3. **`$nextStepIncrement`** (usually `1`):
   - From HOD (order **1**), if workflow is `1` and the division has **no** `director_id`, increment **`2`** (skip Director / order 2).
   - From order **10** on workflow `1`, increment **`2`** (skip Chief of Staff / order 11; next is DG / 12).

4. **Load all enabled definitions** at `approval_level + nextStepIncrement`. If none, load the next higher `approval_order`.

5. **Level 9 → 10 / 11:** If the next step would be order **10** but the memo’s division is **not** in that definition’s allowed divisions, next becomes **11** (Chief of Staff), or **12** (DG) if 11 is disabled.

6. **Multiple rows at the same `approval_order`:** If both intramural and extramural rows exist, pick by **`$has_extramural`** vs **`$has_intramural`** (legacy condition uses level vs next order); then **`skipToNextDefinitionAllowedForDivision()`** for orders **≥ 3**.

7. **Single next row:** For orders **≥ 3**, still run **`skipToNextDefinitionAllowedForDivision()`** so **`divisions`** and **`allowed_funders`** on the candidate (and forward candidates) are respected.

8. **Special level 7 (after Head of Operations):** If current **`approval_level` is 7**, the next step **must follow division `category`**, not the lowest `approval_order` greater than 7 (that would always be **8 — Head of Programs** and would wrongly send **Other** memos to Programs).

   | Division `category` (case-insensitive) | Next `approval_order` | Typical role |
   |----------------------------------------|-------------------------|---------------|
   | **Operations** | **9** | DDG (skips Programs at 8) |
   | **Programs** | **8** | Head of Programs (prefers `workflow_definition.category = Programs`, then any enabled row at 8) |
   | **Other**, unknown, or missing division | **9** | DDG (prefers a row whose `category` matches the division when set, then any enabled row at 9) |

   This aligns with **category rows 7–9** in the table above: Operations use order **7** then **9**; Programs use **8**; Other uses **9**.

9. **Special level 10 / divisions:** If next is order **10** and divisions allow this memo, next may jump to **12** (DG). If next is **10** and divisions **disallow**, next is **11** (or **12** fallback).

10. **Fund-type alignment (does not remove directorate; only wrong fund track):**
    - **Order 3, `fund_type = 2` (extramural):** If the memo has **no extramural** funding, normally skip to **`approval_order + 1`** (e.g. intramural PIU at 4). **Exception:** if **`level3ExtramuralAppliesViaAllowedFunders()`** is true (order 3, extramural row, non-empty **`allowed_funders`**, memo funders intersect), **stay** on order 3 for funder-scoped Grants routing.
    - **Order 4, `fund_type = 1` (intramural):** **`level4IntramuralFunderGatePasses()`** — memo must have **intramural** funding; if the row has **`allowed_funders`**, memo must have funders and they must **intersect**. Otherwise next is order **5**, then **`skipToNextDefinitionAllowedForDivision()`** from that candidate.
    - **Other intramural rows (`fund_type = 1`) when the memo has no intramural:** legacy behaviour still skips **two** orders (`approval_order + 2`) for extramural-only memos (does not use the order-4 special case).

11. **External-only (no intra, no extra):** At next step orders **2 or 3**, if there is **no** division director, may route to **`getCategoryApprover()`** instead of HOD/Directorate definitions. Similar rule at finance order **7** without director.

12. **Return** the resulting `WorkflowDefinition` or **`null`** (end of workflow → treat as fully approved upstream).

---

## `getCategoryApprover()` (category heads)

When the service jumps to a category head instead of the linear row, it maps **division `category`** to an **`approval_order`** and loads a definition (with optional `category` column match):

| Division category | Typical target order | Role (typical) |
|---------------------|----------------------|----------------|
| Operations | 7 | Head of Operations |
| Programs | 8 | Head of Programs |
| Other (default) | 9 | DDG |

Fallbacks exist in code if a category-specific row is missing.

---

## `skipToNextDefinitionAllowedForDivision()`

From a candidate definition at **`approval_order >= 3`**, the service walks **forward** while either:

- **`divisions`** is set and the memo’s division is **not** in the list, or  
- **`allowed_funders`** is set and the memo’s funders do **not** intersect the list  

(`isOnlyAllowedForFunders()` treats empty memo funder list as “do not restrict” **except** for the explicit **level 4** gate above.)

The walk uses the **next higher `approval_order`** only (it does not swap **fund_type** siblings at the **same** order); parallel intramural/extramural at the **same** order should be handled in the multi-definition branch before this walk.

---

## How approvers attach to a role

Each `WorkflowDefinition` has approvers in **`approvers`** (`workflow_dfn_id`) unless **`is_division_specific`** is set—in which case the staff id comes from the division (e.g. `division_head`, `director_id`, `finance_officer` via **`division_reference_column`**). **`getNextApprover`** returns the **definition**; the UI / `current_actor` resolves the actual staff user from that definition plus the model’s division.

---

## Quick inspection (Tinker)

From the `apm/` directory:

```bash
php artisan tinker --execute="
\$m = \\App\\Models\\NonTravelMemo::with('division')->find(YOUR_ID);
\$d = (new \\App\\Services\\ApprovalService())->getNextApprover(\$m);
echo \$d ? (\$d->approval_order.' '.\$d->role.' ft='.(string)(\$d->fund_type ?? '')) : 'null';
"
```

Replace `NonTravelMemo` and id with your model class and primary key.

---

## Related code

- **`ApprovalService::getNextApprover()`** — main resolver.  
- **`ApprovalService::getCategoryApprover()`** — category shortcuts.  
- **`ApprovalService::skipToNextDefinitionAllowedForDivision()`** — division/funder walk from order ≥ 3.  
- **`HasApprovalWorkflow::getNextApprover()`** / **`updateApprovalStatus()`** — delegates to `ApprovalService` for consistent behaviour.
