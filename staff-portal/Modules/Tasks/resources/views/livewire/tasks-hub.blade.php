<div>
    <h4 class="text-success fw-bold mb-3">Weekly Task Planner</h4>
    <div class="list-group">
        @if (portal_can(79))
            <a href="{{ route('workplan.index') }}" class="list-group-item list-group-item-action"><i class="bx bx-right-arrow-alt me-2"></i>Workplan</a>
        @endif
        @if (portal_can(81))
            <a href="{{ route('tasks.activities') }}" class="list-group-item list-group-item-action"><i class="bx bx-right-arrow-alt me-2"></i>Sub Activities</a>
        @endif
        @if (portal_can(75))
            <a href="{{ route('tasks.weekly') }}" class="list-group-item list-group-item-action"><i class="bx bx-right-arrow-alt me-2"></i>Weekly Tasks</a>
        @endif
    </div>
</div>
