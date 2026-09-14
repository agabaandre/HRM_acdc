<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FaqCategorySeeder::class);

        $approvalsCategory = FaqCategory::where('slug', 'approvals-management-system')->firstOrFail();
        $staffPortalCategory = FaqCategory::where('slug', 'staff-portal')->firstOrFail();

        $faqs = [
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'When should I use the special memos?',
                'answer' => '<p>Use <strong>Special Memos</strong> for<strong> travel requests </strong></p><p><strong>a) that span multiple quarters or</strong></p><p><b>b) whose preparations have to be done very early prior to execution e.g CPHIA or</b></p><p><b>c) an urgent activity that may have come up and has to be approved, e.g outbreak response deployment</b></p><p><b> NOTE: Special memos must be supported by a justification.</b></p>',
                'sort_order' => 1,
                'search_keywords' => 'special memos when use travel request',
            ],
            [
                'question' => 'What should I use when a new activity occurs after the matrix is submitted or approved?',
                'answer' => '<p>Use a <strong>Single Memo</strong>. When a matrix is already submitted or approved, you can add new activities to it as Single Memos from the matrix detail page (via <strong>Add Single Memo</strong>). Single Memos follow their own approval workflow and do not change the matrix\'s approval status. </p><p><b>NOTE: The Single memo option will only appear after a matrix has either been submited or has been approved</b></p>',
                'sort_order' => 2,
                'search_keywords' => 'new activity after matrix submitted approved single memo',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'How do I create a new travel matrix?',
                'answer' => '<div>First, only the division focal person can create a new Matrix. At the top of the landing page, you will see the division name, Head, and Focal person. If you are not the focal person, then you cannot create the matrix</div><div>Once you confirm you are the focal person or locate the focal person, then proceed as follows</div><ul><li>Go to <strong>APM Home → Quarterly Travel Matrix (QM)</strong> and click <strong>Open</strong>.</li><li>Click on the button <b>Create New Matrix</b></li><li>Select the year and quarter </li><li>Add at least one key result area under description. If you want to add more, click "<b>Add Key Result Area.</b>"</li><li><b>A key result area (KRA)</b> is a broad, critical area of responsibility where you are expected to consistently deliver important outcomes.</li><li>Then Click on "<b>Create Matrix.</b>"</li><li>The Quarterly Travel Matrix has now been created, and staff within the division can now add their activities</li><li>NOTE: At creation of the Matrix, all staff in the division will receive a notification email</li></ul>',
                'sort_order' => 3,
                'search_keywords' => 'create new travel matrix',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'Should I add procurements and consultants hiring under the quarterly travel matrix?',
                'answer' => '<p><b>No, </b>the <b>quarterly</b> <strong>travel matrix</strong> is for planning <strong>travel-related activities</strong> (missions, workshops, meetings, etc.) and their budgets. </p><p>Independent Procurements and consultants hiring are to be handled using the Non Travel Memo Option, except if they are costs that will be incurred during a travel activity, then they can be included</p>',
                'sort_order' => 4,
                'search_keywords' => 'procurements consultants travel matrix',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'Where do I find the change request?',
                'answer' => '<p><strong>Change Requests</strong> (addenda) are created from an <strong>already approved</strong> document. Open the approved Matrix, Single Memo, Special Memo, or Non-Travel Memo; on its detail page, you will see a <strong>Change Request</strong> (or similar) button. You can also go to <strong>Change Requests</strong> from the main menu to list and manage your change requests. Change requests can only be created for fully approved documents.</p><p><b>NOTE: Change requests can only be submitted by the activity\'s Responsible Person.</b></p>',
                'sort_order' => 5,
                'search_keywords' => 'change request where find',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'Does the change of staff name require approval from the executive office?',
                'answer' => '<p>Yes, Changes to staff names (e.g., staff replacements or additions) on any activity will need to be resubmitted to the executive office for approval.</p><p>Only changes of dates within the same quarter will go to the Director Admin</p>',
                'sort_order' => 6,
                'search_keywords' => 'staff name change executive office approval',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'When should we submit the quarterly travel matrix?',
                'answer' => '<p>Submit the <strong>quarterly travel matrix</strong> in line with your division\'s planning cycle by the 15th of the preceding month to the quarter </p><p>However, the system allows you to create matrices for any quarter at any time. Submit once the content is complete and reviewed so approvals can be completed in time for the planned activities.</p>',
                'sort_order' => 7,
                'search_keywords' => 'quarterly travel matrix when submit',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'How do I know if I have been assigned to a mission?',
                'answer' => '<p>You are assigned to a mission (activity) when you are set as the <strong>Responsible Person</strong> or as an <strong>internal participant</strong> on that activity. </p><p>Under <b>Quarterly Travel Matrix, click Open</b>. Click on View on any matrix and scroll to the bottom, you will see all division members and the number of days they have been assigned. Click on the name to see which activity they have been assigned to</p><p><strong>Activities</strong> / <strong>Single Memos</strong> where you are listed as the responsible person or participant. You will also receive <strong>email notifications</strong> when documents are assigned to you or when you are added to an activity.</p>',
                'sort_order' => 8,
                'search_keywords' => 'assigned mission how know',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'How do I stop daily reminders from coming to my email?',
                'answer' => '<p>Daily reminders are sent to <strong>approvers with pending approvals</strong> (e.g., at 9:00 AM and 4:00 PM) or staff with returned memos </p><p>To reduce or stop them: </p><p>(1) <strong>Process your pending approvals</strong> — once there are no pending items for you, you will not receive reminders. </p><p>(2) For <b>Returned Memos</b>, either edit and resubmit or delete them completely, then the reminders will stop</p><p><b>NOTE: Only the responsible person can edit or delete a returned memo</b></p>',
                'sort_order' => 9,
                'search_keywords' => 'stop daily reminders email',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'What\'s the difference between a focal person and a responsible person?',
                'answer' => '<p><b>A focal person is the staff member assigned to create a matrix on behalf of the division and to s</b>ubmit it once all activities for the quarter have been added. </p><p><strong>A responsible person</strong> is a staff member who oversees an activity from start to finish. Once approval is given, this person will be the one to request DSA, ticket, imprest, as well as submit the accountability and report once it\'s complete</p><p><b>NOTE: The focal person for a division can be changed, but only when the division Head sends an email to MIS</b></p>',
                'sort_order' => 10,
                'search_keywords' => 'focal person responsible person difference',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'What do I do if I cannot log into the CBP platform?',
                'answer' => '<p>Ensure you are using an <strong>active Africa CDC email account</strong> and the correct login page (cbp.africacdc.org). </p><p>Use <strong>Sign in with Staff Email</strong> (Microsoft SSO) when available. If access still fails: </p><p>(1) Confirm your record is active with HR. </p><p>(2) Try a different browser or clear the cache. </p><p>(3) Contact your <strong>IT support</strong> or the CBP administrator to verify your account and permissions.</p>',
                'sort_order' => 11,
                'search_keywords' => 'cannot log in CBP platform login',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'How do I update my profile information like 1st or 2nd supervisor?',
                'answer' => '<p>Profile and organisational data (e.g., 1st or 2nd supervisor) are usually maintained in the <strong>central staff portal</strong> system that feeds the CBP. </p><p>Contact HR to update the supervisor or profile information. Changes will sync to the CBP. If you do not see a "Profile" or "My account" section in the CBP, contact IT or HR for instructions on updating these details.</p>',
                'sort_order' => 12,
                'search_keywords' => 'update profile supervisor',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'How do I recall a submitted matrix or memo?',
                'answer' => '<p>Once a matrix or memo is <strong>submitted</strong>, it is in the approval workflow and generally cannot be "recalled" by the creator without an approver action. </p><p>If you need to withdraw a submitted document before it is returned or approved, contact the <strong>current approver</strong> to return it.</p>',
                'sort_order' => 13,
                'search_keywords' => 'recall submitted matrix memo',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'What is the difference between a Single Memo and a Special Memo?',
                'answer' => '<p>A <strong>Single Memo</strong> is an activity added to an <strong>existing matrix</strong> that is already submitted or approved, Use it when a new activity comes up after the matrix is in progress. </p><p>A <strong>Special Memo</strong> is an activity created in scenarios where it will <strong>a) span multiple quarters or </strong><b>b) whose preparations have to be done very early, prior to execution e.g CPHIA or </b><b>c) an urgent activity that may have come up and has to be approved, e.g outbreak response deployment</b></p>',
                'sort_order' => 14,
                'search_keywords' => 'single memo special memo difference',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'What do draft, submitted, returned, and approved mean?',
                'answer' => '<p><strong>Draft</strong>: You are still editing; the document is not in the approval chain. </p><p><strong>Submitted</strong>: You have sent it for approval; it is with the first (or next) approver and you normally cannot edit until it is returned or approved. </p><p><strong>Returned</strong>: An approver sent it back to you for changes or clarification; you can edit and resubmit. </p><p><strong>Approved</strong>: The document has completed the workflow and is approved. For changes after approval, use a <strong>Change Request</strong> (addendum).</p>',
                'sort_order' => 15,
                'search_keywords' => 'draft submitted returned approved status',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'What happens when my matrix or memo is returned?',
                'answer' => '<p>When an approver <strong>returns</strong> your document, it goes back to the respective HOD. You will see it in your <strong>Returns</strong> or similar list. </p><p>The HOD will have 2 options;</p><p>a) Either to add a comment and send it back to the level it came from, or</p><p>b) send it back to the draft to make the requested changes. It will re-enter the approval workflow. The approval trail keeps a record of who returned it and when.</p>',
                'sort_order' => 16,
                'search_keywords' => 'returned matrix memo what happens edit resubmit',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'Where do I see my pending approvals (as an approver)?',
                'answer' => '<p>Use the <strong>Dashboard</strong> or <strong>Pending Approvals</strong> (or similar) link in the main menu. </p><p>There you will find matrices, memos, and other documents awaiting your action. You can approve, return, or reject each item. </p><p>You will also receive <strong>email notifications</strong> when items are assigned to you, and daily reminder emails list your pending approvals.</p><p><b>NOTE: There is an approvers dashboard that shows the average time each approver takes to Action on a given memo</b></p>',
                'sort_order' => 17,
                'search_keywords' => 'pending approvals approver dashboard where',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'What is a Non-Travel Memo and when do I use it?',
                'answer' => '<p>A <strong>Non-Travel Memo</strong> is for activities that do <strong>not</strong> involve travel—e.g., local workshops, procurement, consultancy, or other non-travel work. </p><p>Use it when you need approval and tracking for any activity that does not involve travel. </p>',
                'sort_order' => 18,
                'search_keywords' => 'non travel memo when use',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'What is an ARF (Activity Request Form)?',
                'answer' => '<p>An <strong>ARF</strong> (Activity Request Form) is a memo generated by the system to request extramural funds from the implementing partner.</p><p><b>NOTE: ARF can only be generated after the activity has been approved, and there exists an approval memo</b></p>',
                'sort_order' => 19,
                'search_keywords' => 'ARF advance request funds',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'What is a Service Request and when do I use it?',
                'answer' => '<p>A <strong>Service Request</strong> is a memo used to request <strong>services</strong> such as DSA, ticket, imprest, transport, venue, or other support needed for an activity from the finance and administration offices. </p><p>Used when the activity uses intramural funding and an approval memo already exists.</p>',
                'sort_order' => 20,
                'search_keywords' => 'service request transport venue when use',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'Can I edit my matrix or memo after I submit it?',
                'answer' => '<p>Once <strong>submitted</strong>, you cannot edit until an approver <strong>returns</strong> it to you. After it is returned, you can edit and resubmit. </p><p>If the document is <strong>approved</strong> and you need to make a change, you must create a Change Request (addendum) based on that document; the change request then goes through the approval workflow. </p><p>Draft documents can be edited freely until you submit.</p>',
                'sort_order' => 21,
                'search_keywords' => 'edit after submit matrix memo',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'What is the approval trail and where do I see it?',
                'answer' => '<p>The <strong>approval trail</strong> is the history of who approved, returned, or rejected your document and when. On the document\'s detail page (matrix, memo, etc.) look for a section or tab such as <strong>Approval Trail</strong>, <strong>History</strong>, or <strong>Approval Status</strong>. There you can see each step, the approver\'s name, action (approved/returned/rejected), date, and any comments.</p>',
                'sort_order' => 22,
                'search_keywords' => 'approval trail history status',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'What are Key Result Areas (KRAs) in the matrix?',
                'answer' => '<p><strong>Key Result Areas (KRAs)</strong> are the main result areas or objectives for your division\'s plan in that matrix (e.g. by quarter or year). </p><p>You add KRAs and their descriptions when creating the matrix so approvers can see what the matrix aims to achieve. </p><p>They help align activities with division goals and make the matrix easier to review and approve.</p>',
                'sort_order' => 23,
                'search_keywords' => 'key result areas KRA matrix',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'Who approves my matrix or memo?',
                'answer' => '<p>Approval is determined by <strong>workflows</strong> assigned to your division and document type. </p><p>Typically, the workflow includes steps such as the Division Head, the Director, and possibly the Executive Office. The system routes the document to each approver in order. </p><p>You can see who is next in the <strong>approval trail</strong> or on the document\'s status page. </p><p><b>NOTE: Approval workflows are managed by the systems administrator and only change when there is a fully approved review and memo</b></p>',
                'sort_order' => 24,
                'search_keywords' => 'who approves workflow division head',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'How do I add activities to my matrix?',
                'answer' => '<p>Under <b>Quarterly Travel Matrix,</b> click on <b>Open</b>, and click the <strong>Add Activity</strong> button. Fill in the details for each activity: title, dates, location, participants, responsible person, and budget breakdown. </p><p>You can add multiple activities. Save the matrix; when ready, submit for approval. For a matrix that is already submitted or approved, add new activities by selecting Add Single Memo on the matrix detail page incase the matrix has already been submitted</p>',
                'sort_order' => 25,
                'search_keywords' => 'add activities matrix',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'Where do I find my submitted matrices and memos?',
                'answer' => '<p>Go to <strong>APM Home</strong> and open <b>Quarterly Travel </b><strong>Matrix</strong>, <strong>Special Memos</strong>, <strong>Single Memos</strong>, or <strong>Non-Travel</strong> as needed. Use the tabs or filters (e.g., "My division", "All", "Draft", "Pending", "Approved", "Returned") to find your documents. Returned items often appear under <strong>Returns</strong> in the menu. You can also use the list or search to find a specific matrix or memo by status or date.</p>',
                'sort_order' => 26,
                'search_keywords' => 'find my matrices memos submitted',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'Can I attach documents or files to my memo?',
                'answer' => '<p>Yes. When creating or editing a Special Memo, Non-Travel Memo, or similar form, look for an <strong>Attach</strong> or <strong>Upload</strong> section. You can attach supporting documents (e.g. PDF, Word, Excel, images) to strengthen your request. Keep file sizes reasonable and use allowed formats as indicated on the form. Attachments are visible to approvers when they review the memo.</p>',
                'sort_order' => 27,
                'search_keywords' => 'attach documents files memo upload',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'What does document number on the matrix or memo mean?',
                'answer' => '<p>The system assigns a <strong>document number</strong> to matrices and memos (and to change requests), and this acts as a reference number for the memo. </p><p>The format and rules are configured by registry (e.g., by division, type, and year). It is used for reference and filing.</p>',
                'sort_order' => 28,
                'search_keywords' => 'document number matrix memo',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'What is the difference between intramural, extramural budget and External Source?',
                'answer' => '<p><strong>Intramural</strong> budget usually refers to funds from internal sources (Africa CDC\'s own budget). </p><p><strong>Extramural</strong> budget refers to funds from external sources (e.g., grants, partners, donors). </p><p><b>External Source</b> refers to funding from an external partner; Africa CDC does not contribute any funding to this source</p>',
                'sort_order' => 29,
                'search_keywords' => 'intramural extramural budget',
            ],
            [
                'faq_category_id' => $approvalsCategory->id,
                'question' => 'Where do i put Activity Dates and travel Dates?',
                'answer' => '<p>Under the activity details, indicate only the activity date, i.e., Start Date and End Date</p><p>Indicate the travel dates under the Request for Approval Field at the bottom of the Form</p>',
                'sort_order' => 30,
                'search_keywords' => 'activity dates travel dates',
            ],
            // Staff Portal category
        ];

        foreach ($faqs as $item) {
            Faq::updateOrCreate(
                ['question' => $item['question']],
                array_merge($item, ['is_active' => true])
            );
        }
    }
}
