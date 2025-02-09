<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EmailDraft;
use App\Models\EmailReview;
use App\Models\FeedbackLog;
use Illuminate\Http\Request;
use DataTables;

class EmailDraftController extends Controller
{
    public function index()
    {
        return view('admin.email-draft.index');
    }
    public function getData(Request $request)
    {
        if ($request->ajax()) {
            $drafts = EmailDraft::with('shopifyOrder')->where('status', 'pending');

            return DataTables::eloquent($drafts)
                ->addColumn('checkbox', function ($draft) {
                    return '<input type="checkbox" class="select-draft" value="' . $draft->id . '">';
                })
                ->addColumn('shopify_order', function ($draft) {
                    return $draft->shopifyOrder ? $draft->shopifyOrder->order_number : 'N/A';
                })
                ->addColumn('actions', function ($draft) {
                    return view('admin.email-draft.partials.actions', compact('draft'))->render();
                })
                ->addColumn('action', function ($draft) {
                    return '
                        <button class="btn btn-sm btn-success" onclick="approveDraft(' . $draft->id . ')">Approve</button>
                        <button class="btn btn-sm btn-danger" onclick="disapproveDraft(' . $draft->id . ')">Disapprove</button>
                    ';
                })
                
                ->rawColumns(['checkbox', 'actions'])
                ->make(true);
        }
    }
    public function getPendingDrafts(Request $request)
    {
        if ($request->ajax()) {
            $data = EmailDraft::with('shopifyOrder')
                ->where('status', 'pending')
                ->latest();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('select', function ($row) {
                    return '<input type="checkbox" class="select-draft" value="' . $row->id . '">';
                })
                ->addColumn('email', function ($row) {
                    return $row->shopifyOrder->email_address ?? '-';
                })
                ->addColumn('status', function ($row) {
                    return '<span class="badge ' . ($row->status === 'pending' ? 'bg-warning' : 'bg-success') . '">' . ucfirst($row->status) . '</span>';
                })
   
                ->rawColumns(['select', 'status', 'actions'])
                ->make(true);
        }
    }

    public function approveDraft($id)
    {
        $draft = EmailDraft::findOrFail($id);
        $draft->update(['status' => 'approved']);

        return response()->json(['message' => 'Draft approved and email sent successfully.']);
    }

    public function disapproveDraft(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
            'comments' => 'nullable|string',
        ]);

        $draft = EmailDraft::findOrFail($id);
        $draft->update(['status' => 'disapproved']);

        $review = EmailReview::create([
            'draft_id' => $draft->id,
            'reviewer_id' => auth()->id(),
            'status' => 'disapproved',
            'feedback' => $request->comments,
        ]);

        FeedbackLog::create([
            'review_id' => $review->id,
            'reason' => $request->reason,
            'comments' => $request->comments,
        ]);

        return response()->json(['message' => 'Draft disapproved with feedback saved.']);
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:approve,disapprove',
            'draft_ids' => 'required|array',
        ]);

        $drafts = EmailDraft::whereIn('id', $request->draft_ids)->get();

        foreach ($drafts as $draft) {
            if ($request->action === 'approve') {
                $draft->update(['status' => 'approved']);
            } else {
                $draft->update(['status' => 'disapproved']);
            }
        }

        return response()->json(['message' => 'Bulk action completed successfully.']);
    }




public function edit($id)
{
    $draft = EmailDraft::findOrFail($id);
    return view('admin.email-draft.edit', compact('draft'));
}

public function update(Request $request, $id)
{
    $request->validate([
        'subject' => 'required|string|max:255',
        'body'    => 'required|string', // TinyMCE will send HTML content
    ]);

    $draft = EmailDraft::findOrFail($id);
    $draft->update([
        'subject'    => $request->subject,
        'body'       => $request->body, // Saving as-is (HTML content)
    ]);

    return redirect()->route('email-draft.index')->with('success', 'Email draft updated successfully!');
}

}
