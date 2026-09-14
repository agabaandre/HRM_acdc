# APM User Guide

Welcome to the APM (Approvals Management) User Guide. This guide will help you create and manage various document types in the system.

## Table of Contents

1. [Creating a Matrix](#creating-a-matrix)
2. [Fund codes and working budget balance](#fund-codes-and-working-budget-balance)
3. [Creating a Special Memo](#creating-a-special-memo)
4. [Creating a Single Memo](#creating-a-single-memo)
5. [Creating a Non-Travel Memo](#creating-a-non-travel-memo)
6. [Creating a Change Request](#creating-a-change-request)
7. [Creating a Service Request](#creating-a-service-request)
8. [Supplementary service requests](#supplementary-service-requests)
9. [Creating an ARF Request](#creating-an-arf-request)
10. [Reports](#reports)
11. [Budget execution dashboard](#budget-execution-dashboard)
12. [Managing Your Documents](#managing-your-documents)

---

## Creating a Matrix

A Matrix is a planning document that outlines activities and budgets for a specific period.

### Step-by-Step Guide

1. **Navigate to Matrices**
   - Click on **"APM Home"** in the main navigation
   - Select **"Matrices"** from the dropdown menu
   
   ![Create Matrix](./assets/images/create_matrix.png)

2. **Create New Matrix**
   - Click the **"Create Matrix"** button (usually green button with plus icon)
   
   ![Screenshot: Create Matrix Button](APM)

3. **Fill in Matrix Details**
   - **Title**: Enter a descriptive title for your matrix
   - **Division**: Select your division from the dropdown
   - **Year**: Select the year for this matrix
   - **Quarter**: Select the quarter (Q1, Q2, Q3, Q4, or Annual)
   - **Key Result Areas**: Add key result areas and their descriptions
   - **Description**: Provide additional context or description
   
   ![Screenshot: Matrix Form](APM)

4. **Add Activities** (if applicable)
   - Click **"Add Activity"** to include activities in your matrix
   - Fill in activity details:
     - Activity title
     - Dates (from and to)
     - Location
     - Participants
     - Budget breakdown
   
   ![Screenshot: Adding Activities](APM)

   When adding budget lines, stay within the **working balance** shown for each fund code. See [Fund codes and working budget balance](#fund-codes-and-working-budget-balance).

5. **Submit for Approval**
   - Review all information
   - Click **"Submit"** or **"Save and Submit"** button
   - The matrix will be sent to the first approver in the workflow
   
   ![Screenshot: Submit Button](APM)

### Important Notes

- Matrices can be saved as drafts before submission
- Once submitted, you cannot edit until it's returned or approved
- You can view the approval status in the "My Matrices" section

---

## Fund codes and working budget balance

APM prevents overspending by checking each **fund code** against a **working balance** while you enter or edit a budget. This applies when you create or edit:

- **Matrix activities** (including from **Matrices → Activities → Create**)
- **Special memos**
- **Non-travel memos**
- **Change requests** (budget increases only)

### Approved budget (admin setup)

Finance or system administrators maintain fund codes under **Fund Codes** (e.g. edit a code to set **Approved budget**). That value is the ceiling for the fund code for the period.

- **Approved budget** — the official allocation (set on the fund code edit screen).
- **Uploaded budget** — used only if approved budget is not set.
- **Budget balance** — legacy SAP/upload field; used as a fallback if neither approved nor uploaded budget is set.

If you are unsure which fund code to use or what the approved amount is, contact your division finance focal point before submitting.

### How working balance is calculated

**Working balance** is what you can still spend on a fund code:

```
Working balance = Approved budget − Committed spend
```

**Committed spend** includes budget lines from documents that are still using funds, in these statuses:

| Status | Counted toward committed spend? |
|--------|----------------------------------|
| Draft | Yes |
| Pending approval | Yes |
| Approved | Yes |
| Returned / rejected | No (unless still in draft on resubmit) |

Committed spend is summed across:

- Matrix **activities** (budget line items on the activity)
- **Special memos**
- **Non-travel memos**
- **Change requests** that are draft, pending, or submitted (the change request amount replaces the parent memo’s budget while the CR is active, so the same money is not counted twice)

**External source** fund type (fund type ID 3) is exempt from balance checks.

### What you see on budget forms

1. **Fund code dropdown**  
   Each option shows the fund code, funder, and **available working balance** (e.g. `CODE | Funder | $72,764.00`).

2. **Subtotal per fund code**  
   As you add rows (description, units, days, unit cost), the subtotal updates. If the subtotal **exceeds** the available balance, you will see:
   - Subtotal shown in **red**
   - A warning: **“Budget exceeded! Available: $…”**
   - **Submit** disabled until the budget is within the limit

3. **Live updates**  
   Balances refresh automatically about every **15 seconds** while the form is open, so if a colleague submits another memo against the same fund code, your available amount updates without reloading the page.

### Rules by document type

| Document type | When balance is checked | What is compared |
|---------------|-------------------------|------------------|
| Matrix activity (create/edit) | On save and in the form | Total budget per fund code vs working balance |
| Special memo (create/edit) | On save and in the form | Total budget per fund code vs working balance |
| Non-travel memo (create/edit) | On save and in the form (draft and submit) | Total budget per fund code vs working balance |
| Change request | On save and in the form | **Only the increase** over the original approved memo budget |

**Change requests:** If you are only reducing budget or leaving it unchanged, no extra balance is required. If you **add** new line items or increase amounts, only that **additional** amount must fit within the working balance.

**Editing an existing activity:** The system treats your current activity’s budget as already allocated, so the available amount shown includes your existing lines when you edit the same document.

### If submit is blocked

1. Reduce line items, units, days, or unit costs so the **subtotal** is at or below **Available**.
2. Remove a fund code from the request and use another code with sufficient balance (if appropriate).
3. Wait a moment for balances to refresh if someone else just released budget (e.g. returned a draft).
4. Ask finance to confirm **Approved budget** on the fund code or to resolve data issues.

The server also enforces the same rules on save; you cannot bypass the warning by submitting outside the normal form.

### For administrators

- Set **Approved budget** on each fund code (**Fund Codes → Edit**).
- Use **Fund code transactions** (where available) to audit debits and credits against `budget_balance`.
- Working balance cache is updated whenever budgets are saved or fund codes change; production should use **Redis** for the cache store (`CACHE_STORE=redis`) so all users see consistent figures.

---

## Creating a Special Memo

Special Memos are used for special travel requests and activities.

### Step-by-Step Guide

1. **Navigate to Special Memos**
   - Click on **"APM Home"** in the main navigation
   - Select **"Special Travel Memos"** from the dropdown
   
   ![Screenshot: Navigation to Special Memos](APM)

2. **Create New Special Memo**
   - Click the **"Create Special Memo"** button
   
   ![Screenshot: Create Special Memo Button](APM)

3. **Fill in Required Information**
   - **Activity Title**: Enter a clear title for the activity
   - **Request Type**: Select the appropriate request type
   - **Background/Context**: Provide background information
   - **Justification**: Explain why this memo is needed
   - **Dates**: Select start and end dates
   - **Location**: Select or enter location(s)
   - **Participants**: Enter number of internal and external participants
   - **Budget Breakdown**: Add detailed budget items
   
   ![Screenshot: Special Memo Form](APM)

4. **Attach Supporting Documents** (if needed)
   - Click **"Attach Files"** or drag and drop files
   - Supported formats: PDF, Word, Excel, Images
   
   ![Screenshot: File Attachment](APM)

5. **Submit for Approval**
   - Review all details
   - Click **"Submit"** button
   
   ![Screenshot: Submit Special Memo](APM)

### Important Notes

- Special memos require detailed justification
- Budget breakdown must be accurate and complete; totals cannot exceed the **working balance** for each fund code (see [Fund codes and working budget balance](#fund-codes-and-working-budget-balance))
- Supporting documents strengthen your request

---

## Creating a Single Memo

Single Memos are activities added to a matrix that is **approved or under approval**. They can also be created when a regular activity is **not passed** and returned by an approver.

### When Single Memos Are Created

1. **Adding to Approved/Pending Matrix**: When you add an activity to a matrix that is already approved or pending approval
2. **Activity Not Passed**: When an approver marks a regular activity as "not passed" during matrix review, it gets converted to a single memo

### Step-by-Step Guide: Adding Single Memo to Matrix

1. **Navigate to Your Matrix**
   - Go to **"Matrices"** from the main navigation
   - Find and open the matrix you want to add a single memo to
   - The matrix must be in **"Approved"** or **"Pending"** status
   
   ![Screenshot: Matrix List](APM)

2. **Open Matrix Details**
   - Click on the matrix to view its details
   - You'll see the matrix information and existing activities
   
   ![Screenshot: Matrix Details Page](APM)

3. **Add Single Memo**
   - On the matrix detail page, look for the **"Add Single Memo"** button
   - This button only appears when the matrix is approved or pending
   - Click the button to create a new single memo
   
   ![Screenshot: Add Single Memo Button](APM)

4. **Fill in Single Memo Details**
   - The form opens with "Single Memo" title
   - Fill in all required fields:
     - **Activity Title**: Enter descriptive title
     - **Request Type**: Select appropriate request type
     - **Dates**: Select start and end dates
     - **Location**: Select or enter location(s)
     - **Participants**: Add internal and external participants
     - **Budget Breakdown**: Add detailed budget items
     - **Background/Justification**: Provide context
   
   ![Screenshot: Single Memo Form](APM)

5. **Submit Single Memo**
   - Review all information
   - Click **"Submit"** button
   - Single memo will be submitted for approval
   - It follows its own approval workflow separate from the matrix
   
   ![Screenshot: Submit Single Memo](APM)

### Creating Single Memo from Returned Activity

When an approver marks an activity as "not passed" during matrix review:

1. **Activity is Converted**
   - The activity is automatically converted to a single memo
   - Status changes to "Draft"
   - A new document number is assigned (SM type)
   
   ![Screenshot: Activity Converted to Single Memo](APM)

2. **Edit and Resubmit**
   - The creator receives notification
   - Open the single memo from the matrix
   - Make necessary corrections based on approver comments
   - Resubmit for approval
   
   ![Screenshot: Edit Returned Single Memo](APM)

### Important Notes

- **Single Memos can only be added to approved or pending matrices** - Draft or returned matrices use regular activities
- **Single Memos have their own approval workflow** - They don't affect the matrix approval status
- **Budget should align with matrix allocations** - Ensure budget fits within the fund code **working balance** (see [Fund codes and working budget balance](#fund-codes-and-working-budget-balance))
- **One matrix can have multiple single memos** - You can add as many as needed
- **Single Memos can be created from returned activities** - When an activity is "not passed", it becomes a single memo
- **Single Memos are tracked separately** - They appear in a separate "Single Memos" tab on the matrix page

---

## Creating a Non-Travel Memo

Non-Travel Memos are for activities that don't involve travel.

### Step-by-Step Guide

1. **Navigate to Non-Travel Memos**
   - Click on **"APM Home"** in the main navigation
   - Select **"Non-Travel Memos"** from the dropdown
   
   ![Screenshot: Navigation to Non-Travel Memos](APM)

2. **Create New Non-Travel Memo**
   - Click the **"Create Non-Travel Memo"** button
   
   ![Screenshot: Create Non-Travel Memo Button](APM)

3. **Fill in Details**
   - **Category**: Select non-travel category (e.g., Training, Workshop, Meeting)
   - **Title**: Enter activity title
   - **Purpose**: Describe the purpose of the activity
   - **Dates**: Select start and end dates
   - **Location**: Enter location (usually local/office)
   - **Participants**: Number of participants
   - **Budget**: Budget breakdown for non-travel expenses
   
   ![Screenshot: Non-Travel Memo Form](APM)

4. **Add Budget Items**
   - Click **"Add Budget Item"**
   - Enter item description and amount
   - Common items: Venue, Catering, Materials, etc.
   
   ![Screenshot: Budget Items](APM)

5. **Submit for Approval**
   - Review all information
   - Click **"Submit"** button
   
   ![Screenshot: Submit Non-Travel Memo](APM)

### Important Notes

- Non-travel memos don't require travel-related information
- Focus on local expenses and logistics
- Categories help classify the type of activity
- Budget is checked against **working balance** on both **Save draft** and **Submit** (see [Fund codes and working budget balance](#fund-codes-and-working-budget-balance))

---

## Creating a Change Request

Change Requests (also called Addendums) are used to modify **previously approved documents**. Change Requests can only be created for documents that have been fully approved.

### Prerequisites

- You must have an **approved** document that you want to modify
- The document must be fully approved (overall_status = 'approved')
- You must have permission to edit the document (typically the creator or responsible person)

### Step-by-Step Guide

1. **Open Your Approved Document**
   - Navigate to the approved document you want to modify:
     - Matrix Activity (Single Memo)
     - Special Memo
     - Non-Travel Memo
   - The document must show status as **"Approved"**
   
   ![Screenshot: Approved Document](APM)

2. **Create Change Request**
   - On the approved document's detail page, look for the **"Change Request"** button
   - This button appears on approved documents
   - Click the button to start creating a change request
   
   ![Screenshot: Change Request Button](APM)

3. **Edit Document with Change Request Mode**
   - The document edit form opens in "Change Request" mode
   - Original values are pre-filled
   - Make the changes you need:
     - Modify dates
     - Update location
     - Adjust budget breakdown
     - Change participants
     - Update other fields as needed
   
   ![Screenshot: Change Request Edit Form](APM)

4. **Provide Justification**
   - **Supporting Reasons**: Provide detailed explanation for why changes are needed
   - This is a required field and must be comprehensive
   - Explain the circumstances requiring the change
   
   ![Screenshot: Change Request Justification](APM)

5. **Attach Supporting Documents**
   - Upload documents supporting the change request
   - Include any approvals, justifications, or related documents
   - These documents help approvers understand why changes are needed
   
   ![Screenshot: Supporting Documents](APM)

6. **Review Changes**
   - Review all changes carefully
   - Ensure the justification is clear and complete
   - Verify all modified fields are correct
   
   ![Screenshot: Review Changes](APM)

7. **Submit Change Request**
   - Click **"Submit"** button
   - The change request will follow the same approval workflow as the original document
   - Original document remains unchanged until change request is approved
   
   ![Screenshot: Submit Change Request](APM)

### Important Notes

- **Change Requests can ONLY be created for approved documents**
- **Strong justification is required** - Change requests without proper justification may be rejected
- **Budget increases** must fit within the fund code **working balance**; decreases do not require extra balance (see [Fund codes and working budget balance](#fund-codes-and-working-budget-balance))
- **Original document remains visible** - The original approved document stays unchanged until the change request is approved
- **Changes must be approved** - All changes go through the approval workflow before taking effect
- **Multiple change requests** - You can create multiple change requests for the same document
- **Change Request is an Addendum** - It modifies the original document, not replacing it
- **Track approval status** - Monitor the change request's approval progress separately from the original document

---

## Creating a Service Request

Service Requests (RQS - Request for Services) are for requesting DSA, Imprest, and Tickets for **approved intramural activities**. Service Requests can only be created from approved documents with **Intramural** fund type.

### Prerequisites

- You must have an **approved** document (Activity/Single Memo, Special Memo, or Non-Travel Memo)
- The document must have **Intramural** fund type (fund_type_id = 1)
- You must be the **responsible person** for the approved document
- The document must be fully approved (overall_status = 'approved')

### Step-by-Step Guide

1. **Open Your Approved Document**
   - Navigate to your approved Activity, Special Memo, or Non-Travel Memo
   - The document must show status as **"Approved"**
   
   ![Screenshot: Approved Document](APM)

2. **Create Service Request from Document**
   - On the approved document's detail page, look for the **"Request Services"** or **"Create RQS"** button
   - This button only appears if:
     - Document is approved
     - Fund type is Intramural
     - You are the responsible person
     - No existing Service Request exists for this document
   
   ![Screenshot: Request Services Button](APM)

3. **Service Request Form Pre-filled**
   - The form is automatically pre-filled with information from the approved document:
     - Activity details
     - Dates
     - Location
     - Participants
     - Budget breakdown
   
   ![Screenshot: Pre-filled Service Request Form](APM)

4. **Review and Complete Details**
   - **Service Type**: Select DSA, Imprest, or Ticket
   - **Service Title**: Review and modify if needed (pre-filled from activity)
   - **Description**: Review activity description
   - **Required By Date**: When you need the service
   - **Priority**: Select priority level (Low, Medium, High, Urgent)
   - **Budget Breakdown**: Review and adjust if needed (pre-filled from activity)
   
   ![Screenshot: Service Request Details](APM)

5. **Add Specifications**
   - Provide detailed specifications for the service
   - Include any special requirements or notes
   
   ![Screenshot: Service Request Specifications](APM)

6. **Submit Service Request**
   - Review all details
   - Click **"Submit"** button
   - Service Request will be submitted for approval
   
   ![Screenshot: Submit Service Request](APM)

### Important Notes

- **Service Requests can ONLY be created from approved documents** - You cannot create a standalone Service Request
- **Only Intramural activities** can have Service Requests (fund_type_id = 1)
- **Only the responsible person** can create Service Requests for their approved documents
- **One Service Request per document** - If a Service Request already exists, you'll see a "View Request" button instead
- Service Requests follow their own approval workflow
- Service Requests are linked to the source document for tracking purposes

For supplementary requests when an earlier service request did not use the full memo budget, see **[Supplementary service requests](#supplementary-service-requests)** below and the [technical reference](/documentation/CHILD_SERVICE_REQUESTS.md).

---

## Supplementary service requests

A **supplementary service request** is used when an approved memo still has **unrequested funds** after a service request (or another supplementary request) has been filed. It requests the **remaining balance** for the same approved memo (DSA, imprest, tickets, etc.), uses the same source memo and approval workflow, and is capped so you cannot exceed what was left unrequested on the **previous** service request.

### When you can create a supplementary request

On a service request detail page, **Create supplementary request** appears when:

- **Allow supplementary service requests** is enabled in [App Settings](https://cbp.africacdc.org/staff/apm/system-settings) (Service requests group; setting key `allow_child_service_requests`, default **Yes**).
- **Original Memo Budget** (or **Maximum allowable (remaining balance)** on a supplementary SR) is **greater than** **Total Requested Funds** — there is still unrequested balance.
- **No supplementary request** is already linked to that service request (one supplementary request per SR).
- **Today** falls between the parent memo’s **activity start and end dates** (non-travel memos are exempt from this window).
- You are the **creator** (draft/returned parent) or the parent SR **staff / responsible person** (approved or in approval).
- **Nested supplementary requests** are allowed: if a supplementary SR still has remaining balance, you may create another supplementary request from it (each level has its own cap).

### Steps

1. Open the service request that still has remaining balance (the “previous” request).
2. Click **Create supplementary request**.
3. Complete the form (pre-filled from the same approved memo). The budget summary shows **Maximum allowable (remaining balance)** — your **Total Requested Funds** must not exceed this amount.
4. Save as draft or **Submit for approval**.

### What you will see

- A **Supplementary service request** banner with the cap and a link to the **previous service request** document number.
- On the previous request’s detail page: once a supplementary request exists, an info message links to its document number; **Create supplementary request** is hidden until that linked request is removed or no longer applies.
- On PDF/print: **SUPPLEMENTARY SERVICE REQUEST** on the subject line, a short note after the subject with the balance and previous document number, and (when approved) embedded copies of previous service requests before the **Original Approval Memo**.

### Rules to remember

- **One supplementary request per service request** at each level; further levels are allowed while each SR still has remaining balance.
- The maximum for a new supplementary request is fixed at creation: **previous SR original budget − previous SR total requested** at that time.
- Supplementary requests do not replace the previous request; all remain on the audit trail for the same memo.
- Only **approved** previous service requests are attached to the PDF pack before the original approval memo.

Technical reference: [Supplementary service requests (developer guide)](/documentation/CHILD_SERVICE_REQUESTS.md).

---

## Creating an ARF Request

ARF (Advance Request Form) Requests are for requesting advances for **approved extramural activities**. ARF Requests can only be created from approved documents with **Extramural** fund type.

### Prerequisites

- You must have an **approved** document (Activity/Single Memo, Special Memo, or Non-Travel Memo)
- The document must have **Extramural** fund type (fund_type_id = 2)
- You must be the **responsible person** for the approved document
- The document must be fully approved (overall_status = 'approved')
- For Activities: Both the activity AND its matrix must be approved

### Step-by-Step Guide

1. **Open Your Approved Document**
   - Navigate to your approved Activity, Special Memo, or Non-Travel Memo
   - The document must show status as **"Approved"**
   - For Activities: Ensure the parent Matrix is also approved
   
   ![Screenshot: Approved Document](APM)

2. **Create ARF from Document**
   - On the approved document's detail page, look for the **"Create ARF"** button
   - This button only appears if:
     - Document is approved (and matrix is approved for activities)
     - Fund type is Extramural (fund_type_id = 2)
     - You are the responsible person
     - No existing ARF Request exists for this document
   
   ![Screenshot: Create ARF Button](APM)

3. **ARF Request Modal Opens**
   - Clicking "Create ARF" opens a modal with pre-filled information
   - All details from the approved document are automatically populated:
     - Activity title
     - Dates (from and to)
     - Location
     - Participants (internal and external)
     - Budget breakdown
     - Division information
   
   ![Screenshot: ARF Request Modal](APM)

4. **Review Pre-filled Information**
   - Review all pre-filled information carefully
   - The "Request for Approval" section shows the original document details
   - Budget breakdown is displayed with totals
   - Verify all information is correct
   
   ![Screenshot: Review ARF Information](APM)

5. **Confirm ARF Creation**
   - The form is pre-filled and ready to submit
   - Click **"Create ARF"** or **"Submit"** button in the modal
   - ARF Request will be created and submitted for approval
   
   ![Screenshot: Submit ARF Request](APM)

6. **ARF Request Created**
   - After submission, you'll be redirected to the ARF Request detail page
   - The ARF Request follows its own approval workflow
   - You can track its approval status
   
   ![Screenshot: ARF Request Created](APM)

### Important Notes

- **ARF Requests can ONLY be created from approved documents** - You cannot create a standalone ARF Request
- **Only Extramural activities** can have ARF Requests (fund_type_id = 2)
- **Only the responsible person** can create ARF Requests for their approved documents
- **For Activities**: Both the activity AND its parent matrix must be approved
- **One ARF Request per document** - If an ARF Request already exists, the button won't appear
- **Requested amount** is automatically set from the approved document's total budget
- ARF Requests follow their own approval workflow separate from the source document
- ARF Requests are linked to the source document for tracking and reference

---

## Reports

The Reports section lets you view and export summary data on matrices, memos, and other APM documents.

### How to Access Reports

1. **Navigate to Reports**
   - Click on **"APM Home"** in the main navigation
   - Select **"Reports"** from the dropdown (or use the Reports card on the dashboard and click **"All Reports"**)

2. **Available Reports**
   - **Division counts** – Summary of documents by division (e.g. counts by type, status, year/quarter). Use filters and export to Excel if needed.
   - **Memo list** – List of memos (and related documents) with filters by year, quarter, type, status, title, document number. Export to Excel for offline use.

3. **Using the Reports**
   - Apply **filters** (year, quarter, document type, status) to narrow the list
   - Use **search** (title, document number) where available
   - Use **Export to Excel** to download the current view for reporting or analysis

### Tips

- Reports are based on your access (e.g. division-scoped where applicable)
- Use the memo list report to track approved or pending memos across the division
- Export data before changing filters if you need to keep a snapshot

---

## Budget execution dashboard

The **Budget execution** dashboard shows how much of your division’s approved APM budget has been **executed** through Service Requests (intramural) and ARFs (extramural).

**Open:** **Dashboard → Budget execution**, or **Reports → Budget execution**.

### What it measures

| Term | Meaning |
|------|---------|
| **Initiative** | An approved matrix activity, single memo, special memo, or non-travel memo with a budget |
| **Approved budget** | Total budget on that initiative |
| **Executed** | Sum of **approved** Service Request `new_total_budget` and **approved** ARF `requested_amount` linked to the initiative |
| **Execution %** | Executed ÷ approved budget (capped at 100% per initiative) |
| **100% executed** | SR/ARF totals reach the full approved budget |

Data is based on activities and memos **initiated and approved in APM** only.

### Quarterly vs annual

- **Quarterly** — matrix activities use the matrix **year and quarter**; special and non-travel memos use activity/memo dates.
- **Annual** — all four quarters in the selected year.

### Who can see what

| Role | View |
|------|------|
| System admin / cross-division access (permission 88) | All divisions |
| Directorate or division **director** | Divisions under their oversight |
| Other staff | Their own **division only** |

Use the **Division** filter when you can see more than one division.

### Reading the dashboard

1. **Summary cards** — initiative count, how many have an SR/ARF, how many are 100% executed, overall execution %.
2. **By division** — each division is shown as its own card (sorted alphabetically) with a summary bar, division-level fund-code totals, then initiatives underneath.
3. **Initiative rows** — document, title (wrapped, no horizontal scroll), budget, executed amount, %, status, and **fund codes** with planned / executed / remaining on that initiative plus the fund code **working balance** (system-wide).
4. **Export** — **Export Excel** (CSV) or **Export PDF** using the current filters.

Administrators (role 10 or permission 88) default to **All divisions** in the division filter.

---

## Managing Your Documents

### Viewing Your Documents

1. **My Documents Section**
   - Access from **"APM Home"** → **"My [Document Type]"**
   - View all documents you've created
   
   ![Screenshot: My Documents](APM)

2. **Filter and Search**
   - Use filters to find specific documents
   - Filter by status, date, or other criteria
   - Use search to find by title or document number
   
   ![Screenshot: Document Filters](APM)

### Document Statuses

- **Draft**: Not yet submitted, can be edited
- **Submitted**: Sent for approval, awaiting first approver
- **In Progress**: Being reviewed by approvers
- **Approved**: Fully approved and ready for use
- **Returned**: Sent back for corrections, can be edited and resubmitted
- **Rejected**: Rejected and cannot be resubmitted

### Editing Returned Documents

1. **View Return Comments**
   - Open the returned document
   - Read comments from approvers
   - Understand what needs to be corrected
   
   ![Screenshot: Return Comments](APM)

2. **Make Corrections**
   - Click **"Edit"** or **"Resubmit"** button
   - Make necessary changes
   - Update information based on comments
   
   ![Screenshot: Edit Returned Document](APM)

3. **Resubmit**
   - After making corrections, click **"Resubmit"**
   - Document goes back to the approver who returned it
   
   ![Screenshot: Resubmit Button](APM)

### Tracking Approval Progress

1. **View Approval Trail**
   - Open any submitted document
   - Click **"Approval Trail"** or view approval history
   - See who has approved and when
   
   ![Screenshot: Approval Trail](APM)

2. **Check Current Status**
   - Status is displayed at the top of the document
   - See who is the current approver
   - View pending approvers
   
   ![Screenshot: Current Status](APM)

---

## Tips for Success

1. **Complete All Required Fields**
   - Fields marked with * are required
   - Incomplete forms cannot be submitted

2. **Provide Clear Justifications**
   - Well-written justifications improve approval chances
   - Be specific and detailed

3. **Attach Supporting Documents**
   - Relevant documents strengthen your request
   - Include quotes, estimates, or approvals when available

4. **Review Before Submitting**
   - Double-check all information
   - Ensure budgets are accurate and within each fund code’s **working balance**
   - Verify dates and locations

5. **Respond Promptly to Returns**
   - Address return comments quickly
   - Resubmit with corrections as soon as possible

6. **Track Your Documents**
   - Regularly check status of submitted documents
   - Follow up if documents are stuck in approval

---

## Getting Help

- **Help Documentation**: Click the **"Help"** link in the top navigation
- **Contact Support**: Reach out to your system administrator
- **Approvers Guide**: See [Approvers Guide](./APPROVERS_GUIDE.md) for approval process details

---

**Last Updated**: June 2026  
**Version**: 1.2.0

