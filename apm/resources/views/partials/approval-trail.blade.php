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
    left: 24px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}
.timeline-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 30px;
    position: relative;
}
.timeline-item:last-child {
    margin-bottom: 0;
}
.timeline-avatar-cell {
    flex: 0 0 48px;
    width: 48px;
    height: 48px;
    position: relative;
}
.timeline-approver-img {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #dee2e6;
    display: block;
}
.timeline-approver-initials {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    border: 2px solid #dee2e6;
}
.timeline-badge-cell {
    flex: 0 0 24px;
    width: 24px;
    padding-top: 12px;
}
.timeline-badge {
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
.timeline-content {
    flex: 1;
    min-width: 0;
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

@if($resource->approvalTrails->count() > 0)
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Approval Trail</h5>
    </div>
    <div class="card-body">
        <ul class="timeline">
            @php
                $trailsSorted = $resource->approvalTrails->sortByDesc('created_at');
            @endphp
            @forelse($trailsSorted as $trail)
                @php
                    $approver = $trail->oicStaff ?? $trail->staff;
                    $approverName = $approver ? ($approver->name ?? trim(($approver->title ?? '') . ' ' . ($approver->fname ?? '') . ' ' . ($approver->lname ?? '') . ' ' . ($approver->oname ?? ''))) : 'N/A';
                    $initials = $approver ? strtoupper(substr($approver->fname ?? '', 0, 1) . substr($approver->lname ?? '', 0, 1)) : '?';
                    $hasPhoto = $approver && !empty(trim($approver->photo ?? ''));
                    $photoUrl = $hasPhoto ? rtrim(user_session('base_url') ?? url('/'), '/') . '/uploads/staff/' . $approver->photo : '';
                @endphp
                <li class="timeline-item">
                    <div class="timeline-avatar-cell">
                        @if($hasPhoto)
                            <img src="{{ $photoUrl }}" alt="{{ $approverName }}" class="timeline-approver-img" title="{{ $approverName }}" onerror="this.style.display='none'; var n=this.nextElementSibling; if(n){ n.style.display='flex'; }" onload="var n=this.nextElementSibling; if(n){ n.style.display='none'; }">
                            <div class="timeline-approver-initials" style="display: none; position: absolute; top: 0; left: 0;" title="{{ $approverName }}">{{ $initials ?: '?' }}</div>
                        @else
                            <div class="timeline-approver-initials" title="{{ $approverName }}">{{ $initials ?: '?' }}</div>
                        @endif
                    </div>
                    <div class="timeline-badge-cell">
                        <div class="timeline-badge {{ strtolower($trail->action) }}">
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
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-time">
                            {{ $trail->created_at->format('j') }}<sup>{{ $trail->created_at->format('S') }}</sup> {{ $trail->created_at->format('F, Y g:i a') }}
                        </div>
                        <div class="timeline-title">
                            {{ $approverName }}
                            <span class="text-muted">({{ $trail->approver_role_name ?? 'N/A' }})</span>
                            <span class="badge bg-{{ strtolower($trail->action) === 'approved' ? 'success' : (strtolower($trail->action) === 'rejected' ? 'danger' : 'warning') }}">
                                {{ ucfirst($trail->action) }}
                            </span>
                        </div>
                        <div class="timeline-remarks text-muted">
                            {{ Str::limit($trail->remarks, 100) ?? 'No remarks' }} {{ (strlen($trail->remarks ?? '') > 100) ? '...' : '' }}
                            @if(strlen($trail->remarks ?? '') > 100)
                                <a href="#trailDetail{{ $trail->id }}" data-bs-toggle="modal">Read More</a>
                                @include('partials.trail-detail-modal', ['trail' => $trail])
                            @endif
                        </div>
                    </div>
                </li>
            @empty
                <li class="timeline-item">
                    <div class="timeline-avatar-cell"></div>
                    <div class="timeline-badge-cell"><div class="timeline-badge other"><i class="bx bx-time"></i></div></div>
                    <div class="timeline-content"><div class="timeline-title">No approval trail found</div></div>
                </li>
            @endforelse
        </ul>
    </div>
</div>
@endif 