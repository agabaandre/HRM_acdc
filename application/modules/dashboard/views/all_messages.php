
<div class="container-fluid mt-4">
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">All Messages</h5>
            <input type="text" class="form-control w-25" id="searchInput" placeholder="Search messages...">
        </div>
        <div class="card-body" id="messagesContainer">
            <div class="text-muted">Loading messages...</div>
        </div>
    </div>
</div>

<!-- Modal for message body -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="messageModalLabel">Message Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalMessageBody">
        Loading...
      </div>
    </div>
  </div>
</div>

<!-- Required JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function fetchMessages(query = '') {
    $.ajax({
        url: "<?= base_url('dashboard/search_messages') ?>",
        type: "GET",
        data: { query: query },
        dataType: "json",
        success: function (messages) {
            let html = '';
            if (messages.length > 0) {
                messages.forEach(function (msg) {
                    html += `
                        <div class="message-card border-bottom pb-2 mb-3" style="cursor:pointer;" 
                             data-bs-toggle="modal" data-bs-target="#messageModal"
                             data-message-body="${encodeURIComponent(msg.body ?? '')}"
                             data-message-subject="${msg.subject}">
                            <div class="d-flex justify-content-between">
                                <h6 class="fw-bold mb-1">${msg.trigger}</h6>
                                <small class="text-muted">${msg.time_ago} ago</small>
                            </div>
                            <p class="mb-1"><strong>Subject:</strong> ${msg.subject}</p>
                        </div>`;
                });
            } else {
                html = '<p class="text-muted">No messages found.</p>';
            }

            $('#messagesContainer').html(html);

            $('.message-card').on('click', function () {
                const subject = $(this).data('message-subject');
                const body = decodeURIComponent($(this).data('message-body'));
                $('#messageModalLabel').text(subject);
                $('#modalMessageBody').html(body);
            });
        },
        error: function () {
            $('#messagesContainer').html('<p class="text-danger">Failed to load messages.</p>');
        }
    });
}

$(document).ready(function () {
    fetchMessages();

    $('#searchInput').on('keyup', function () {
        let query = $(this).val().trim();
        fetchMessages(query);
    });
});
</script>
