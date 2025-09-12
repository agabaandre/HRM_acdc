 <!-- Trail comment modal -->
        <div class="modal fade" id="trailDetail{{$trail->id}}" tabindex="-1" aria-labelledby="trailDetail{{$trail->id}}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title" id="trailDetail{{$trail->id}}">Comment by {{ $trail->staff->name ?? 'N/A' }} </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <p>{{$trail->remarks}}</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                </div>
            </div>
 </div>