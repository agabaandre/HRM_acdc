<style>
.timeline {
    position: relative;
    margin: 0;
    padding: 0;
    list-style: none;
    max-height: 50vh;
    overflow-y: auto;
}
.timeline:before {
    content: '';
    position: absolute;
    left: 30px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}
.timeline-item {
    position: relative;
    margin-bottom: 30px;
    padding-left: 60px;
}
.timeline-item:last-child {
    margin-bottom: 0;
}
.timeline-badge {
    position: absolute;
    left: 18px;
    top: 0;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid #28a745;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}
.timeline-badge.approved {
    border-color: #28a745;
    color: #28a745;
}
.timeline-badge.rejected {
    border-color: #dc3545;
    color: #dc3545;
}
.timeline-badge.returned {
    border-color:rgb(217, 136, 15);
    color:rgb(208, 149, 12);
}
.timeline-badge.submitted {
    border-color:rgb(17, 166, 211);
    color:rgb(27, 143, 216);
}
.timeline-time {
    font-size: 0.9rem;
    color: #888;
    margin-bottom: 2px;
}
.timeline-title {
    font-weight: 600;
    margin-bottom: 2px;
}
.timeline-remarks {
    color: #555;
    font-size: 0.95rem;
}
</style>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Approval Trail</h5>
    </div>
    <div class="card-body">
        <ul class="timeline">
            @forelse($matrix->approvalTrails as $trail)
                <li class="timeline-item">
                    <div class="timeline-badge 
                        {{ strtolower($trail->action)}}">
                        @if(strtolower($trail->action) === 'approved')
                            <i class="bx bx-check"></i>
                        @elseif(strtolower($trail->action) === 'rejected')
                            <i class="bx bx-x"></i>
                       @elseif(strtolower($trail->action) === 'submitted')
                            <i class="bx bx-time"></i>
                        @else
                            <i class="bx bx-x"></i>
                        @endif
                    </div>
                    <div class="timeline-time">
                        {{ $trail->created_at->format('j') }}<sup>{{ $trail->created_at->format('S') }}</sup> {{ $trail->created_at->format('F, Y g:i a') }}
                    </div>
                    <div class="timeline-title">
                        {{ $trail->staff->name ?? 'N/A' }} 
                        <span class="text-muted">({{ $trail->approver_role->role ?? 'Focal Person' }})</span>
                        <span class="badge bg-{{ strtolower($trail->action) === 'approved' ? 'success' : (strtolower($trail->action) === 'rejected' ? 'danger':'warning') }}">
                            {{ ucfirst($trail->action) }}
                        </span>
                    </div>
                    <div class="timeline-remarks text-muted">
                        {{ Str::limit($trail->remarks,100) ?? 'No remarks' }} {{ (strlen($trail->remarks)>100)?'...':''}}
                        @if(strlen($trail->remarks)>100)
                            <a href="#trailDetail{{$trail->id}}" data-bs-toggle="modal">Read More</a>
                            @include('matrices.partials.trail-detail-modal',['trail'=>$trail])
                        @endif
                    </div>
                </li>
            @empty
                <li class="timeline-item">
                    <div class="timeline-badge other"><i class="bx bx-time"></i></div>
                    <div class="timeline-title">No approval trail found</div>
                </li>
            @endforelse
        </ul>
    </div>
</div>
