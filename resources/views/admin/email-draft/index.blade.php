@extends('layouts.app')

@section('content')

    <div class="card shadow-sm rounded-4">
        <div class="card-header  text-white rounded-top-4 d-flex justify-content-between align-items-center">
            <h4 class="mb-0">📧 Pending Email Drafts</h4>
            <div>
                <a href="{{ route('email-draft.create') }}" id="create-draft" class="btn btn-primary btn-sm me-2 text-white">📝 Create Draft</a>
                <button id="approve-selected" class="btn btn-success btn-sm me-2 text-white">✅ Approve Selected</button>
                <button id="disapprove-selected" class="btn btn-danger btn-sm me-2 text-white">❌ Disapprove Selected</button>
                <button id="send-selected" class="btn btn-info btn-sm text-white">📤 Send Emails</button>
            </div>
        </div>
        <div class="card-body">
           
                {!! $dataTable->table(['class' => 'table table-hover table-bordered align-middle']) !!}
         
        </div>
    
</div>
<!-- Modal -->
<div class="modal fade" id="emailBodyModal" tabindex="-1" aria-labelledby="emailBodyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="emailBodyModalLabel">Edit Email Body</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="updateEmailForm" method="POST" action="">
                    @csrf
                    <div class="mb-3">
                        <label for="modalSubject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="modalSubject" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label for="modalBody" class="form-label">Email Body</label>
                        <textarea id="modalBody" name="body" class="form-control" rows="10"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="shopifyOrderModal" tabindex="-1" aria-labelledby="shopifyOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="shopifyOrderModalLabel">Shopify Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Order ID:</strong> <span id="modalOrderId"></span></p>
                <p><strong>Order Number:</strong> <span id="modalOrderNumber"></span></p>
                <p><strong>Product Name:</strong> <span id="modalProductName"></span></p>
                <p><strong>Order Date:</strong> <span id="modalOrderDate"></span></p>
                <p><strong>Customer Name:</strong> <span id="modalCustomerName"></span></p>
                <p><strong>Email Address:</strong> <span id="modalEmailAddress"></span></p>
                <p><strong>Paid Amount:</strong> <span id="modalPaidAmount"></span></p>
                <p><strong>Discount:</strong> <span id="modalDiscount"></span></p>
                <p><strong>Number of Items:</strong> <span id="modalNumberOfItems"></span></p>
                <p><strong>Tracking Number:</strong> <span id="modalTrackingNumber"></span></p>
                <p><strong>Tracking URL:</strong> <span id="modalTrackingUrl"></span></p>
                <p><strong>Coupon:</strong> <span id="modalCoupon"></span></p>
                <p><strong>Created At:</strong> <span id="modalCreatedAt"></span></p>
                <p><strong>Updated At:</strong> <span id="modalUpdatedAt"></span></p>
            </div>
        </div>
    </div>
</div>
<!-- Bulk Disapprove Modal -->
<div class="modal fade" id="bulkDisapproveModal" tabindex="-1" aria-labelledby="bulkDisapproveLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="bulkDisapproveForm">
                <div class="modal-header">
                    <h5 class="modal-title">Disapprove Selected Drafts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label>Reason for Disapproval:</label>
                    <input type="text" name="reason" class="form-control" required>

                    <label class="mt-2">Comments (optional):</label>
                    <textarea name="comments" class="form-control" rows="3"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger">Disapprove Selected</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Single Disapprove Modal -->
<div class="modal fade" id="disapproveModal" tabindex="-1" aria-labelledby="disapproveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="disapproveForm" method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="disapproveModalLabel">Disapprove Email Draft</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="draft_id" id="draftId">

                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Disapproval <span class="text-danger">*</span></label>
                        <input type="text" name="reason" id="reason" class="form-control" required placeholder="e.g., Tone issues, Policy violation">
                    </div>

                    <div class="mb-3">
                        <label for="comments" class="form-label">Comments (Optional)</label>
                        <textarea name="comments" id="comments" class="form-control" rows="4" placeholder="Additional feedback..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger">❌ Disapprove Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    <script>
   // ✅ Send Individual Email

   function showToast(type, message) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type, // 'success', 'error', 'warning', 'info'
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }
function sendEmail(draftId) {
    fetch(`/email-draft/send/${draftId}`, {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        showToast('success', data.message);
  
        $('#emailDraftsTable').DataTable().ajax.reload();
    })
    .catch(error => console.error("Error:", error));
}

// ✅ Send Bulk Emails
document.getElementById("send-selected").addEventListener("click", function () {
    let selectedDrafts = [];
    document.querySelectorAll(".select-draft:checked").forEach(checkbox => {
        selectedDrafts.push(checkbox.value);
    });

    if (selectedDrafts.length === 0) {
        showToast('Info'," No drafts selected.");

        return;
    }

    fetch("/email-draft/send-bulk", {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ draft_ids: selectedDrafts })
    })
    .then(response => response.json())
    .then(data => {
        showToast('success', data.message);

    
        $('#emailDraftsTable').DataTable().ajax.reload();
    })
    .catch(error => console.error("Error:", error));
});

        document.addEventListener('DOMContentLoaded', function () {
            const table = $('#emailDraftsTable');

// Toggle checkbox when clicking on the checkbox cell
table.on('click', 'td.select-checkbox', function (e) {
    const checkbox = $(this).find('.select-draft');

    // Prevent double toggling if the actual checkbox was clicked
    if (!$(e.target).is('input[type="checkbox"]')) {
        checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
    }
});

// Handle checkbox click directly (to maintain toggle behavior)
table.on('click', '.select-draft', function (e) {
    e.stopPropagation(); // Prevent the parent cell click from triggering again
});

// Handle "Select All" checkbox
$('#select-all').on('click', function () {
    const isChecked = $(this).prop('checked');
    $('.select-draft').prop('checked', isChecked);
});
            tinymce.init({
                selector: 'textarea',
                height: 300,
                menubar: false,
                plugins: 'link lists',
                toolbar: 'undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist outdent indent | link'
            });

            $(document).on('click', '.email-body', function () {
                const id = $(this).data('id');
                const subject = $(this).data('subject');
                const body = $(this).data('body');

                $('#modalSubject').val(subject);
                tinymce.get('modalBody').setContent(body);

                $('#updateEmailForm').attr('action', `/email-draft/${id}/update`);

                $('#emailBodyModal').modal('show');
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            // Select/Deselect All
            $(document).on('click', '#select-all', function () {
                $('.select-draft').prop('checked', this.checked);
            });

            // Approve Selected
            $('#approve-selected').click(function () {
                const selected = $('.select-draft:checked').map(function () {
                    return $(this).val();
                }).get();

                if (selected.length > 0) {
                    // TODO: AJAX request to approve drafts
                } else {
                    showToast('Info', 'No drafts selected.');

                }
            });

            // Disapprove Selected
            $('#disapprove-selected').click(function () {
                const selected = $('.select-draft:checked').map(function () {
                    return $(this).val();
                }).get();

                if (selected.length > 0) {
         
                    // TODO: AJAX request to disapprove drafts
                } else {
                    alert("No drafts selected.");
                }
            });
        });


    $(document).ready(function () {
    $('#emailDraftsTable').on('click', '.shopify-order-link', function () {
        const orderData = $(this).data('order');  // Get the order data
        console.log("Order Data:", orderData);    // Debugging output

        if (orderData) {
            // Populate the modal fields
            $('#modalOrderId').text(orderData.id || 'N/A');
            $('#modalOrderNumber').text(orderData.order_number || 'N/A');
            $('#modalProductName').text(orderData.product_name || 'N/A');
            $('#modalOrderDate').text(orderData.order_date || 'N/A');
            $('#modalCustomerName').text(orderData.customer_name || 'N/A');
            $('#modalEmailAddress').text(orderData.email_address || 'N/A');
            $('#modalPaidAmount').text(orderData.paid_amount ? `$${orderData.paid_amount}` : 'N/A');
            $('#modalDiscount').text(orderData.discount ? `$${orderData.discount}` : 'N/A');
            $('#modalNumberOfItems').text(orderData.number_of_items || 'N/A');
            $('#modalTrackingNumber').text(orderData.tracking_number || 'N/A');
            $('#modalTrackingUrl').html(orderData.tracking_url
                ? `<a href="${orderData.tracking_url}" target="_blank">Track Order</a>`
                : 'N/A');
            $('#modalCoupon').text(orderData.coupon || 'N/A');
            $('#modalCreatedAt').text(orderData.created_at ? new Date(orderData.created_at).toLocaleString('en-US') : 'N/A');
            $('#modalUpdatedAt').text(orderData.updated_at ? new Date(orderData.updated_at).toLocaleString('en-US') : 'N/A');

            // Show the modal
            $('#shopifyOrderModal').modal('show');
        } else {
            console.error("No order data found.");
        }
    });
});
document.getElementById("disapprove-selected").addEventListener("click", function () {
    const selectedDrafts = Array.from(document.querySelectorAll(".select-draft:checked"))
        .map(checkbox => checkbox.value);

    if (selectedDrafts.length === 0) {
        showToast('info', "No drafts selected.");
        return;
    }

    // Show Modal
    $('#bulkDisapproveModal').modal('show');

    // Handle Form Submission
    $('#bulkDisapproveForm').on('submit', function (e) {
        e.preventDefault();

        const reason = this.reason.value;
        const comments = this.comments.value;

        fetch("/email-draft/bulk-disapprove", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                draft_ids: selectedDrafts,
                reason: reason,
                comments: comments
            })
        })
        .then(response => response.json())
        .then(data => {
            showToast('success', data.message);

            $('#emailDraftsTable').DataTable().ajax.reload();
            $('#bulkDisapproveModal').modal('hide');
        })
        .catch(error => console.error("Error:", error));
    });
});
function approveDraft(draftId) {
    fetch(`/email-draft/approve/${draftId}`, {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (response.ok) {
            showToast('success', data.message);
        } else {
            showToast('warning', data.message); // Warning if already approved
        }
        $('#emailDraftsTable').DataTable().ajax.reload();
    })
    .catch(error => {
        showToast('error', "🚨 Email already sent Please Crete a new draft");
        console.error("Error:", error);
    });
}

document.getElementById("approve-selected").addEventListener("click", function () {
    const selectedDrafts = Array.from(document.querySelectorAll(".select-draft:checked"))
        .map(checkbox => checkbox.value);

    if (selectedDrafts.length === 0) {
        showToast('info', "No drafts selected.");
        return;
    }

    fetch("/email-draft/bulk-approve", {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ draft_ids: selectedDrafts })
    })
    .then(response => response.json())
    .then(data => {
        showToast('success', data.message);
        $('#emailDraftsTable').DataTable().ajax.reload();
    })
    .catch(error => {
        showToast('error', "🚨 Failed to approve drafts.");
        console.error("Error:", error);
    });
});


// ✅ Open Modal When Disapprove Button is Clicked
function disapproveDraft(draftId) {
    $('#draftId').val(draftId); // Set draft ID in hidden input
    $('#reason').val('');       // Clear previous inputs
    $('#comments').val('');
    $('#disapproveModal').modal('show'); // Show modal
}

// ✅ Handle Form Submission
$('#disapproveForm').on('submit', function (e) {
    e.preventDefault();

    const draftId = $('#draftId').val();
    const reason = $('#reason').val();
    const comments = $('#comments').val();

    fetch(`/email-draft/disapprove/${draftId}`, {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ reason, comments })
    })
    .then(response => response.json())
    .then(data => {
        showToast('success', data.message);

        $('#emailDraftsTable').DataTable().ajax.reload();
        $('#disapproveModal').modal('hide');
    })
    .catch(error => console.error("Error:", error));
});



    </script>


    </script>
    </script>
@endpush
