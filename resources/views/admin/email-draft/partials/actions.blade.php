<div class="btn-group" role="group">
    <a href="{{ route('email-draft.edit', $draft->id) }}" class="btn btn-warning btn-sm">Edit</a>
    <button class="btn btn-success btn-sm approve-draft" data-id="{{ $draft->id }}">Approve</button>
    <button class="btn btn-danger btn-sm disapprove-draft" data-id="{{ $draft->id }}">Disapprove</button>
    <button class="btn btn-primary btn-sm send-email" data-id="{{ $draft->id }}">Send</button>
</div>
