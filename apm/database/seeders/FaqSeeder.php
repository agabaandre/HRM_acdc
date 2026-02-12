<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'When should I use the special memos?',
                'answer' => '<p>Use <strong>Special Memos</strong> for <strong>special travel requests and activities</strong> that are not part of a quarterly travel matrix. They are for one-off or ad hoc travel that requires its own justification, budget breakdown, and approval workflow. If the activity is part of your division\'s planned quarterly matrix, add it as an activity (or single memo) on the matrix instead.</p>',
                'sort_order' => 1,
                'search_keywords' => 'special memos when use travel request',
            ],
            [
                'question' => 'What should I use when a new activity occurs after the matrix is submitted or approved?',
                'answer' => '<p>Use a <strong>Single Memo</strong>. When a matrix is already submitted or approved, you can add new activities to it as Single Memos from the matrix detail page (via <strong>Add Single Memo</strong>). Single Memos follow their own approval workflow and do not change the matrix\'s approval status. They are the correct option for new activities that arise after the matrix has been submitted or approved.</p>',
                'sort_order' => 2,
                'search_keywords' => 'new activity after matrix submitted approved single memo',
            ],
            [
                'question' => 'How do I create a new travel matrix?',
                'answer' => '<ul><li>Go to <strong>APM Home → Matrices</strong> and click <strong>Create Matrix</strong>.</li><li>Enter division, year, quarter (or annual), key result areas, and description.</li><li>Add activities with dates, location, participants, and budget breakdown.</li><li>Save as draft or submit for approval. Once submitted, the matrix follows the approval workflow.</li></ul>',
                'sort_order' => 3,
                'search_keywords' => 'create new travel matrix',
            ],
            [
                'question' => 'Should I add procurements and consultants under the travel matrix?',
                'answer' => '<p>The <strong>travel matrix</strong> is for planning <strong>travel-related activities</strong> (missions, workshops, meetings, etc.) and their budgets. Procurements and consultants are typically handled through other processes (e.g. procurement or HR). If a specific activity on the matrix involves consultant travel or procurement as part of that activity, you can include it in the activity\'s budget breakdown and description; otherwise, use the appropriate non-matrix channel for standalone procurements and consultants.</p>',
                'sort_order' => 4,
                'search_keywords' => 'procurements consultants travel matrix',
            ],
            [
                'question' => 'Where do I find the change request?',
                'answer' => '<p><strong>Change Requests</strong> (addendums) are created from an <strong>already approved</strong> document. Open the approved Matrix, Single Memo, Special Memo, or Non-Travel Memo; on its detail page you will see a <strong>Change Request</strong> (or similar) button. You can also go to <strong>Change Requests</strong> from the main menu to list and manage your change requests. Change requests can only be created for documents that are fully approved.</p>',
                'sort_order' => 5,
                'search_keywords' => 'change request where find',
            ],
            [
                'question' => 'Does the change of staff name require approval from the executive office?',
                'answer' => '<p>Changes to staff names (e.g. creator, focal person, responsible person) on a matrix or memo may be subject to your organization\'s policy. In the CBP, edits to an approved document that change key personnel typically go through a <strong>Change Request</strong>, which follows the same workflow as the original document (including executive levels if they are part of that workflow). For profile or system-wide name updates, use the profile or staff management area; any requirement for executive approval is defined by Africa CDC HR and governance policies.</p>',
                'sort_order' => 6,
                'search_keywords' => 'staff name change executive office approval',
            ],
            [
                'question' => 'When should we submit the quarterly travel matrix?',
                'answer' => '<p>Submit the <strong>quarterly travel matrix</strong> in line with your division\'s planning cycle and the deadlines set by management (e.g. before the start of the quarter or as communicated). The system allows you to create matrices by quarter (Q1–Q4) or annual; submit once the content is complete and reviewed so that approvals can be completed in time for the planned activities.</p>',
                'sort_order' => 7,
                'search_keywords' => 'quarterly travel matrix when submit',
            ],
            [
                'question' => 'How do I know if I have been assigned to a mission?',
                'answer' => '<p>You are assigned to a mission (activity) when you are set as the <strong>Responsible Person</strong> or as an <strong>internal participant</strong> on that activity. Check <strong>Matrices</strong> (and the relevant matrix) or <strong>Activities</strong> / <strong>Single Memos</strong> where you are listed as responsible person or participant. You may also receive <strong>email notifications</strong> when documents are assigned to you or when you are added to an activity.</p>',
                'sort_order' => 8,
                'search_keywords' => 'assigned mission how know',
            ],
            [
                'question' => 'How do I stop daily reminders from coming to my email?',
                'answer' => '<p>Daily reminders are sent to <strong>approvers with pending items</strong> (e.g. at 9:00 AM and 4:00 PM). To reduce or stop them: (1) <strong>Process your pending approvals</strong> — once there are no pending items for you, you may not receive reminders; (2) Contact your <strong>system administrator</strong> or IT to see if reminder preferences or opt-out options are available for your account. The system is designed to remind approvers so that items are processed in time.</p>',
                'sort_order' => 9,
                'search_keywords' => 'stop daily reminders email',
            ],
            [
                'question' => 'What\'s the difference between a focal person and a responsible person?',
                'answer' => '<p><strong>Focal person</strong> is set at the <strong>matrix</strong> level: they are the main contact or owner for that entire matrix (division\'s plan for the quarter/year). <strong>Responsible person</strong> is set at the <strong>activity</strong> level: they are the person responsible for that specific activity or mission. One matrix has one focal person; each activity on the matrix can have its own responsible person. The focal person often coordinates the matrix; the responsible person is accountable for delivering the activity.</p>',
                'sort_order' => 10,
                'search_keywords' => 'focal person responsible person difference',
            ],
            [
                'question' => 'What do I do if I cannot log into the CBP platform?',
                'answer' => '<p>Ensure you are using an <strong>active Africa CDC email account</strong> and the correct login page (e.g. https://cbp.africacdc.org). Use <strong>Sign in with Staff Email</strong> (Microsoft SSO) when available. If access still fails: (1) Confirm your account is active with IT/HR; (2) Try a different browser or clear cache; (3) Contact your <strong>IT support</strong> or the CBP administrator to verify your account and permissions.</p>',
                'sort_order' => 11,
                'search_keywords' => 'cannot log in CBP platform login',
            ],
            [
                'question' => 'How do I access the CBP platform?',
                'answer' => '<p>Go to <strong>https://cbp.africacdc.org</strong> and sign in with your <strong>active Africa CDC email account</strong> (typically via Microsoft SSO / "Sign in with Staff Email"). After authentication you will be redirected into the Central Business Platform where you can access Matrices, Memos, Approvals, and other modules according to your role.</p>',
                'sort_order' => 12,
                'search_keywords' => 'access CBP platform how',
            ],
            [
                'question' => 'How do I update my profile information like 1st or 2nd supervisor?',
                'answer' => '<p>Profile and organisational data (e.g. 1st or 2nd supervisor) are usually maintained in the <strong>central staff system</strong> or HR system that feeds the CBP. Use your organisation\'s designated process (e.g. self-service portal, HR form, or IT request) to update supervisor or profile information. Changes may sync to the CBP; if you do not see a "Profile" or "My account" section in the CBP, contact IT or HR for how to update these details.</p>',
                'sort_order' => 13,
                'search_keywords' => 'update profile supervisor',
            ],
            [
                'question' => 'How do I recall a submitted matrix or memo?',
                'answer' => '<p>Once a matrix or memo is <strong>submitted</strong>, it is in the approval workflow and generally cannot be "recalled" by the creator without an approver action. If it has been <strong>returned</strong> by an approver, it comes back to you for editing and you can then change it and resubmit. If you need to withdraw a submitted document before it is returned or approved, contact the <strong>current approver</strong> or your <strong>system administrator</strong>; the system may support returning it to you so you can edit or withdraw it.</p>',
                'sort_order' => 14,
                'search_keywords' => 'recall submitted matrix memo',
            ],
            [
                'question' => 'What is the difference between a Single Memo and a Special Memo?',
                'answer' => '<p>A <strong>Single Memo</strong> is an activity added to an <strong>existing matrix</strong> that is already submitted or approved. It sits under that matrix and has its own approval path. Use it when a new activity comes up after the matrix is in progress. A <strong>Special Memo</strong> is a <strong>standalone</strong> travel request not tied to a quarterly matrix—use it for one-off or ad hoc travel that is not part of your division’s planned matrix. Both go through approval workflows; the difference is whether the activity is linked to a matrix (Single Memo) or not (Special Memo).</p>',
                'sort_order' => 15,
                'search_keywords' => 'single memo special memo difference',
            ],
            [
                'question' => 'What do draft, submitted, returned, and approved mean?',
                'answer' => '<p><strong>Draft</strong>: You are still editing; the document is not in the approval chain. <strong>Submitted</strong>: You have sent it for approval; it is with the first (or next) approver and you normally cannot edit until it is returned or approved. <strong>Returned</strong>: An approver sent it back to you for changes; you can edit and resubmit. <strong>Approved</strong>: The document has completed the workflow and is approved. For changes after approval, use a <strong>Change Request</strong> (addendum).</p>',
                'sort_order' => 16,
                'search_keywords' => 'draft submitted returned approved status',
            ],
            [
                'question' => 'What happens when my matrix or memo is returned?',
                'answer' => '<p>When an approver <strong>returns</strong> your document, it comes back to you for editing. You will see it in your <strong>Returns</strong> or similar list. Open it, make the requested changes (or address the approver’s comments), then <strong>resubmit</strong>. It will re-enter the approval workflow. The approval trail keeps a record of who returned it and when.</p>',
                'sort_order' => 17,
                'search_keywords' => 'returned matrix memo what happens edit resubmit',
            ],
            [
                'question' => 'Where do I see my pending approvals (as an approver)?',
                'answer' => '<p>Use the <strong>Dashboard</strong> or <strong>Pending Approvals</strong> (or similar) link in the main menu. There you will see matrices, memos, and other documents waiting for your action. You can approve, return, or reject each item. You may also receive <strong>email notifications</strong> when items are assigned to you. Daily reminder emails list your pending approvals.</p>',
                'sort_order' => 18,
                'search_keywords' => 'pending approvals approver dashboard where',
            ],
            [
                'question' => 'What is a Non-Travel Memo and when do I use it?',
                'answer' => '<p>A <strong>Non-Travel Memo</strong> is for activities that do <strong>not</strong> involve travel—e.g. local workshops, procurement, consultancy, or other non-travel work. Use it when you need approval and tracking for such activities. It has its own form (background, justification, budget, etc.) and approval workflow, similar to Special Memos but for non-travel. Travel-related work should go on a matrix, Single Memo, or Special Memo.</p>',
                'sort_order' => 19,
                'search_keywords' => 'non travel memo when use',
            ],
            [
                'question' => 'What is an ARF (Advance Request for Funds)?',
                'answer' => '<p>An <strong>ARF</strong> (Advance Request for Funds) is a request for <strong>advance funding</strong>—e.g. cash or funds in advance of an activity. You create an ARF request in the CBP when you need such an advance; it goes through its own approval workflow. ARFs are separate from the travel matrix and memos; use them when your process requires a formal advance request.</p>',
                'sort_order' => 20,
                'search_keywords' => 'ARF advance request funds',
            ],
            [
                'question' => 'What is a Service Request and when do I use it?',
                'answer' => '<p>A <strong>Service Request</strong> is used to request internal <strong>services</strong> such as transport, venue, IT, or other support needed for an activity. You submit a service request so the responsible department can approve and arrange the service. It can be linked to an activity or matrix where relevant. Use it when you need a formal request and approval for a service rather than only travel or budget approval.</p>',
                'sort_order' => 21,
                'search_keywords' => 'service request transport venue when use',
            ],
            [
                'question' => 'Can I edit my matrix or memo after I submit it?',
                'answer' => '<p>Once <strong>submitted</strong>, you cannot edit until an approver <strong>returns</strong> it to you. After it is returned, you can edit and resubmit. If the document is <strong>approved</strong> and you need to change something, you must create a <strong>Change Request</strong> (addendum) from that document; the change request goes through the approval workflow. Draft documents can be edited freely until you submit.</p>',
                'sort_order' => 22,
                'search_keywords' => 'edit after submit matrix memo',
            ],
            [
                'question' => 'What is the approval trail and where do I see it?',
                'answer' => '<p>The <strong>approval trail</strong> is the history of who approved, returned, or rejected your document and when. On the document’s detail page (matrix, memo, etc.) look for a section or tab such as <strong>Approval Trail</strong>, <strong>History</strong>, or <strong>Approval Status</strong>. There you can see each step, the approver’s name, action (approved/returned/rejected), date, and any comments.</p>',
                'sort_order' => 23,
                'search_keywords' => 'approval trail history status',
            ],
            [
                'question' => 'What are Key Result Areas (KRAs) in the matrix?',
                'answer' => '<p><strong>Key Result Areas (KRAs)</strong> are the main result areas or objectives for your division’s plan in that matrix (e.g. by quarter or year). You add KRAs and their descriptions when creating the matrix so approvers see what the matrix is aiming to achieve. They help align activities with division goals and make the matrix easier to review and approve.</p>',
                'sort_order' => 24,
                'search_keywords' => 'key result areas KRA matrix',
            ],
            [
                'question' => 'Who approves my matrix or memo?',
                'answer' => '<p>Approval is determined by <strong>workflows</strong> assigned to your division and document type. Typically the workflow includes steps such as Division Head, Director, and possibly Executive Office. The system routes the document to each approver in order. You can see who is next in the <strong>approval trail</strong> or on the document’s status page. Exact approvers are configured in <strong>Settings → Workflows</strong> by administrators.</p>',
                'sort_order' => 25,
                'search_keywords' => 'who approves workflow division head',
            ],
            [
                'question' => 'How do I add activities to my matrix?',
                'answer' => '<p>When <strong>creating</strong> or <strong>editing</strong> a matrix (in draft or returned status), use the <strong>Add Activity</strong> (or similar) button or section. Fill in each activity’s details: title, dates, location, participants, responsible person, and budget breakdown. You can add multiple activities. Save the matrix; when ready, submit for approval. For a matrix that is already submitted or approved, add new work via <strong>Add Single Memo</strong> from the matrix detail page.</p>',
                'sort_order' => 26,
                'search_keywords' => 'add activities matrix',
            ],
            [
                'question' => 'Where do I find my submitted matrices and memos?',
                'answer' => '<p>Go to <strong>APM Home</strong> and open <strong>Matrices</strong>, <strong>Special Memos</strong>, <strong>Single Memos</strong>, or <strong>Non-Travel</strong> as needed. Use the tabs or filters (e.g. “My division”, “All”, “Draft”, “Pending”, “Approved”, “Returned”) to find your documents. Returned items often appear under <strong>Returns</strong> in the menu. You can also use the list or search to find a specific matrix or memo by status or date.</p>',
                'sort_order' => 27,
                'search_keywords' => 'find my matrices memos submitted',
            ],
            [
                'question' => 'Can I attach documents or files to my memo?',
                'answer' => '<p>Yes. When creating or editing a Special Memo, Non-Travel Memo, or similar form, look for an <strong>Attach</strong> or <strong>Upload</strong> section. You can attach supporting documents (e.g. PDF, Word, Excel, images) to strengthen your request. Keep file sizes reasonable and use allowed formats as indicated on the form. Attachments are visible to approvers when they review the memo.</p>',
                'sort_order' => 28,
                'search_keywords' => 'attach documents files memo upload',
            ],
            [
                'question' => 'What document number will my matrix or memo get?',
                'answer' => '<p>The system assigns a <strong>document number</strong> to matrices and memos (and sometimes to change requests). The format and rules are configured by your administrator (e.g. by division, type, and year). You usually see the number on the document’s detail page or in the list after it is created or submitted. It is used for reference and filing. If you don’t see a number yet, it may be assigned on submit or by a background process.</p>',
                'sort_order' => 29,
                'search_keywords' => 'document number matrix memo',
            ],
            [
                'question' => 'What is the difference between intramural and extramural budget?',
                'answer' => '<p><strong>Intramural</strong> budget usually refers to funds from internal or core sources (Africa CDC’s own budget). <strong>Extramural</strong> budget refers to funds from external sources (e.g. grants, partners, donors). In the matrix or activity, you may be asked to break down budget by these categories so that finance and approvers can see the funding source. Use the labels and fields as defined in your division’s guidance.</p>',
                'sort_order' => 30,
                'search_keywords' => 'intramural extramural budget',
            ],
        ];

        foreach ($faqs as $item) {
            Faq::updateOrCreate(
                ['question' => $item['question']],
                array_merge($item, ['is_active' => true])
            );
        }
    }
}
