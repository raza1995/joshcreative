<?php
namespace App\Http\Controllers;

use App\DataTables\EmailDraftsDataTable;
use App\Http\Controllers\Controller;
use App\Models\EmailDraft;
use App\Models\EmailReview;
use App\Models\FeedbackLog;
use App\Models\ShopifyOrder;
use App\Services\GmailService;
use Auth;
use Illuminate\Http\Request;
use DataTables;

class EmailDraftController extends Controller
{

    protected $gmailService;

    public function __construct(GmailService $gmailService)
    {
        $this->gmailService = $gmailService;
    }
    public function index(EmailDraftsDataTable $dataTable)
    {
        if (Auth::user()) {
            ini_set('memory_limit', '1024M');

            return $dataTable->render('admin.email-draft.index');
        }
    }


    public function create()
{
    return view('admin.email-draft.create');
}

public function store(Request $request)
{
    $request->validate([
        'subject'           => 'required|string|max:255',
        'body'              => 'required|string',
        'action'            => 'required|in:send,draft',
        'email'             => 'required|email',
        'shopify_order_id'  => 'nullable|integer|exists:shopify_orders,id',
    ]);

    // Initial draft creation without email_id
    $draft = EmailDraft::create([
        'subject'          => $request->subject,
        'body'             => $request->body,
        'status'           => $request->action === 'send' ? 'sent' : 'draft',
        'shopify_order_id' => $request->shopify_order_id,
        'sent_at'          => $request->action === 'send' ? now() : null,
    ]);

    if ($request->action === 'send') {
        // ✅ Send the email using GmailService
        $result = $this->gmailService->sendEmail($request->email, $request->subject, $request->body);

        if ($result && isset($result['id'])) {
            // ✅ Update the draft with the auto-generated email ID
            $draft->update([
                'email_id' => $result['id'],
                'sent_at'  => now(),
            ]);

            return redirect()->route('email-draft.index')->with('success', '✅ Email sent successfully.');
        }

        return back()->with('error', '🚨 Failed to send email.');
    }

    return redirect()->route('email-draft.index')->with('success', '✅ Draft saved successfully.');
}

    /**
     * Fetch data for DataTables (AJAX Request).
     */
    public function getData(Request $request)
    {
        if ($request->ajax()) {
            $drafts = EmailDraft::with('shopifyOrder')->where('status', 'pending');

            return DataTables::eloquent($drafts)
                ->addColumn('checkbox', function ($draft) {
                    return '<input type="checkbox" class="select-draft" value="' . $draft->id . '">';
                })
                ->addColumn('subject', function ($draft) {
                    return '<strong>' . e($draft->subject) . '</strong>';
                })
                ->addColumn('body', function ($draft) {
                    return '<div class="text-truncate" style="max-width: 300px;">' . e($draft->body) . '</div>';
                })
                ->addColumn('status', function ($draft) {
                    if ($draft->status === 'approved') {
                        return '<span class="badge bg-success">Approved</span>';
                    } elseif ($draft->status === 'pending') {
                        return '<span class="badge bg-warning text-dark">Pending</span>';
                    } elseif ($draft->status === 'disapproved') {
                        return '<span class="badge bg-danger">Disapproved</span>';
                    }
                    return $draft->status;
                })
                ->addColumn('shopify_order', function ($draft) {
                    return $draft->shopifyOrder ? $draft->shopifyOrder->order_number : 'N/A';
                })
                ->addColumn('actions', function ($draft) {
                    return view('admin.email-draft.partials.actions', compact('draft'))->render();
                })
                ->editColumn('created_at', function ($draft) {
                    return $draft->created_at->format('Y-m-d h:i A');
                })
                ->editColumn('updated_at', function ($draft) {
                    return $draft->updated_at->format('Y-m-d h:i A');
                })
                ->rawColumns(['checkbox', 'subject', 'body', 'status', 'actions'])
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
    
        // Check if the draft is already approved
        if ($draft->status === 'approved') {
            return response()->json(['message' => '🚨 This draft has already been approved.'], 400);
        }
    
        // Approve the draft and send the email
        $draft->update(['status' => 'approved', 'sent_at' => now()]);
    
        $to = $draft->shopifyOrder->email_address ?? null;
        if ($to) {
            $this->gmailService->sendEmail($to, $draft->subject, $draft->body);
        }
    
        return response()->json(['message' => '✅ Draft approved and email sent successfully.']);
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
public function sendEmail($id)
{
    $draft = EmailDraft::findOrFail($id);
    $to = $draft->shopifyOrder->email_address ?? null;

    if (!$to) {
        return response()->json(['message' => '❌ No recipient email found.'], 400);
    }

    $result = $this->gmailService->sendEmail($to, $draft->subject, $draft->body);

    if ($result) {
            $draft->update(['status' => 'approved', 'sent_at' => now()]);
        return response()->json(['message' => "✅ Email sent to $to successfully."]);
    }

    return response()->json(['message' => '🚨 Failed to send email.'], 500);
}
public function sendBulkEmails(Request $request)
{
    $request->validate([
        'draft_ids' => 'required|array|min:1',
    ]);

    $result = $this->gmailService->sendBulkEmails($request->draft_ids);

    return response()->json($result);
}

public function bulkApprove(Request $request)
{
    $request->validate([
        'draft_ids' => 'required|array|min:1',
    ]);

    $drafts = EmailDraft::whereIn('id', $request->draft_ids)->get();
    $approvedCount = 0;

    foreach ($drafts as $draft) {
        if ($draft->status !== 'approved') {
            $draft->update(['status' => 'approved']);

            // Send emails for approved drafts
            $to = $draft->shopifyOrder->email_address ?? null;
            if ($to) {
                $this->gmailService->sendEmail($to, $draft->subject, $draft->body);
            }

            $approvedCount++;
        }
    }

    return response()->json(['message' => "✅ $approvedCount drafts approved successfully."]);
}

public function bulkDisapprove(Request $request)
{
    $request->validate([
        'draft_ids' => 'required|array|min:1',
        'reason'    => 'required|string|max:255',
        'comments'  => 'nullable|string|max:1000',
    ]);

    $drafts = EmailDraft::whereIn('id', $request->draft_ids)->get();
    $disapprovedCount = 0;

    foreach ($drafts as $draft) {
        if ($draft->status !== 'disapproved') {
            $draft->update(['status' => 'disapproved']);

            $review = EmailReview::create([
                'draft_id'    => $draft->id,
                'reviewer_id' => auth()->id(),
                'status'      => 'disapproved',
                'feedback'    => $request->comments,
            ]);

            FeedbackLog::create([
                'review_id' => $review->id,
                'reason'    => $request->reason,
                'comments'  => $request->comments,
            ]);

            $disapprovedCount++;
        }
    }

    return response()->json(['message' => "❌ $disapprovedCount drafts disapproved with feedback saved."]);
}
public function getShopifyOrders(Request $request)
{
    $search = $request->input('q'); // Get the search query

    $orders = ShopifyOrder::where('customer_name', 'like', '%' . $search . '%')
                ->orWhere('email_address', 'like', '%' . $search . '%')
                ->orWhere('order_number', 'like', '%' . $search . '%')
                ->select('id', 'order_number', 'customer_name', 'email_address') // Fetch only necessary fields
                ->limit(10) // Limit results for performance
                ->get();

    return response()->json($orders);
}

}
