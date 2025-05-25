<?php

namespace App\Http\Controllers;

use App\Models\Memo;
use App\Models\Workflow;
use Illuminate\Http\Request;

class MemoController extends Controller
{
    /**
     * Display a listing of the memos.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get user ID from session (from the external system)
        $userId = session('user')['user_id'] ?? null;

        // If we have a user ID, filter memos by that user
        if ($userId) {
            $memos = Memo::where('user_id', $userId)->get();
        } else {
            // If no user ID, show all memos (for admin purposes)
            $memos = Memo::all();
        }

        return view('memos.index', compact('memos'));
    }

    /**
     * Show the form for creating a new memo.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $workflows = Workflow::where('is_active', 1)->get();

        if ($workflows->isEmpty()) {
            return redirect()->route('memos.index')
                ->with('error', 'No active workflows available. Cannot create a memo.');
        }

        return view('memos.create', compact('workflows'));
    }

    /**
     * Store a newly created memo in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'workflow_id' => 'required|exists:workflows,id',
            'title' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'description' => 'required|string',
            'document_id' => 'sometimes|integer|default:1',
        ]);

        // Get user ID from session (from the external system)
        $userId = session('user')['user_id'] ?? null;

        if (!$userId) {
            return redirect()->route('memos.index')
                ->with('error', 'User ID not found in session. Cannot create a memo.');
        }

        $validated['user_id'] = $userId;
        $validated['document_id'] = $validated['document_id'] ?? 1;

        $memo = Memo::create($validated);

        return redirect()->route('memos.show', $memo->id)
            ->with('success', 'Memo created successfully.');
    }

    /**
     * Display the specified memo.
     *
     * @param  \App\Models\Memo  $memo
     * @return \Illuminate\View\View
     */
    public function show(Memo $memo)
    {
        // Get the workflow associated with this memo
        $workflow = $memo->workflow;

        // Get workflow definitions
        $workflowDefinitions = $workflow->workflowDefinitions()
            ->orderBy('approval_order')
            ->get();

        return view('memos.show', compact('memo', 'workflow', 'workflowDefinitions'));
    }

    /**
     * Show the form for editing the specified memo.
     *
     * @param  \App\Models\Memo  $memo
     * @return \Illuminate\View\View
     */
    public function edit(Memo $memo)
    {
        // Get user ID from session
        $userId = session('user')['user_id'] ?? null;

        // Check if the user is the creator of the memo
        if ($userId && $memo->user_id != $userId) {
            return redirect()->route('memos.index')
                ->with('error', 'You can only edit your own memos.');
        }

        $workflows = Workflow::where('is_active', 1)->get();

        return view('memos.edit', compact('memo', 'workflows'));
    }

    /**
     * Update the specified memo in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Memo  $memo
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Memo $memo)
    {
        $validated = $request->validate([
            'workflow_id' => 'required|exists:workflows,id',
            'title' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        // Get user ID from session
        $userId = session('user')['user_id'] ?? null;

        // Check if the user is the creator of the memo
        if ($userId && $memo->user_id != $userId) {
            return redirect()->route('memos.index')
                ->with('error', 'You can only edit your own memos.');
        }

        $validated['update_at'] = now();

        $memo->update($validated);

        return redirect()->route('memos.show', $memo->id)
            ->with('success', 'Memo updated successfully.');
    }

    /**
     * Remove the specified memo from storage.
     *
     * @param  \App\Models\Memo  $memo
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Memo $memo)
    {
        // Get user ID from session
        $userId = session('user')['user_id'] ?? null;

        // Check if the user is the creator of the memo
        if ($userId && $memo->user_id != $userId) {
            return redirect()->route('memos.index')
                ->with('error', 'You can only delete your own memos.');
        }

        $memo->delete();

        return redirect()->route('memos.index')
            ->with('success', 'Memo deleted successfully.');
    }
}