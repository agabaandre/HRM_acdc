<style>
.timeline {
  display: flex;
  flex-direction: column;
  width: 100%;
  margin: 0 auto;
  padding: 20px 0;

  &__event {
    background: #fff;
    margin-bottom: 20px;
    position: relative;
    display: flex;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    border-left: 4px solid #e5e7eb;

    &__title {
      font-size: 1.1rem;
      line-height: 1.4;
      font-weight: 600;
      color: #374151;
      margin-bottom: 8px;
    }
    &__content {
      padding: 20px;
      flex: 1;
    }
    &__date {
      color: #6b7280;
      font-size: 0.9rem;
      font-weight: 500;
      margin-bottom: 8px;
    }
    &__icon {
      border-radius: 8px 0 0 8px;
      background: #f3f4f6;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-basis: 80px;
      font-size: 1.5rem;
      color: #6b7280;
      padding: 20px;
      min-width: 80px;
    }
    &__description {
      flex: 1;
    }
    &__badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-left: 8px;
    }
    &__remarks {
      color: #6b7280;
      font-size: 0.9rem;
      margin-top: 8px;
      padding: 8px 12px;
      background: #f9fafb;
      border-radius: 6px;
      border-left: 3px solid #d1d5db;
    }

    &:after {
      content: "";
      width: 2px;
      height: 100%;
      background: #e5e7eb;
      position: absolute;
      top: 0;
      left: 40px;
      z-index: 1;
    }

    &:before {
      content: "";
      width: 20px;
      height: 20px;
      position: absolute;
      background: #e5e7eb;
      border-radius: 100%;
      left: 30px;
      top: 20px;
      transform: translateY(-50%);
      border: 3px solid #fff;
      z-index: 2;
    }

    // Approved status
    &--approved {
      border-left-color: #10b981;
      &:before {
        background: #10b981;
      }
      &:after {
        background: #10b981;
      }
      .timeline__event__icon {
        background: #d1fae5;
        color: #10b981;
      }
      .timeline__event__badge {
        background: #d1fae5;
        color: #065f46;
      }
    }

    // Rejected status
    &--rejected {
      border-left-color: #ef4444;
      &:before {
        background: #ef4444;
      }
      &:after {
        background: #ef4444;
      }
      .timeline__event__icon {
        background: #fee2e2;
        color: #ef4444;
      }
      .timeline__event__badge {
        background: #fee2e2;
        color: #991b1b;
      }
    }

    // Submitted status
    &--submitted {
      border-left-color: #3b82f6;
      &:before {
        background: #3b82f6;
      }
      &:after {
        background: #3b82f6;
      }
      .timeline__event__icon {
        background: #dbeafe;
        color: #3b82f6;
      }
      .timeline__event__badge {
        background: #dbeafe;
        color: #1e40af;
      }
    }

    // Returned status
    &--returned {
      border-left-color: #f59e0b;
      &:before {
        background: #f59e0b;
      }
      &:after {
        background: #f59e0b;
      }
      .timeline__event__icon {
        background: #fef3c7;
        color: #f59e0b;
      }
      .timeline__event__badge {
        background: #fef3c7;
        color: #92400e;
      }
    }

    &:last-child {
      &:after {
        content: none;
      }
    }
  }
}

@media (max-width: 768px) {
  .timeline__event {
    flex-direction: column;
  }
  .timeline__event__icon {
    border-radius: 8px 8px 0 0;
    flex-basis: auto;
    min-width: auto;
  }
  .timeline__event:before {
    left: 20px;
  }
  .timeline__event:after {
    left: 30px;
  }
}
</style>

<div class="card shadow-sm border-0 rounded-3">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 text-success">
            <i class="fas fa-history me-2"></i>Approval Trail
        </h6>
    </div>
    <div class="card-body p-4">
        <div class="timeline">
            @forelse($trails as $trail)
                <div class="timeline__event timeline__event--{{ strtolower($trail->action) }}">
                    <div class="timeline__event__icon">
                        @if(strtolower($trail->action) === 'approved' || strtolower($trail->action) === 'passed')
                            <i class="bx bx-check"></i>
                        @elseif(strtolower($trail->action) === 'rejected' || strtolower($trail->action) === 'flagged')
                            <i class="bx bx-x"></i>
                        @elseif(strtolower($trail->action) === 'submitted')
                            <i class="bx bx-time"></i>
                        @elseif(strtolower($trail->action) === 'returned')
                            <i class="bx bx-undo"></i>
                        @else
                            <i class="bx bx-info-circle"></i>
                        @endif
                    </div>
                    <div class="timeline__event__content">
                        <div class="timeline__event__date">
                            {{ $trail->created_at->format('j') }}<sup>{{ $trail->created_at->format('S') }}</sup> {{ $trail->created_at->format('F, Y g:i a') }}
                        </div>
                        <div class="timeline__event__title">
                            {{ $trail->staff->name ?? 'N/A' }} 
                            <span class="text-muted">({{ $trail->approver_role_name ?? 'Focal Person' }})</span>
                            <span class="timeline__event__badge">
                                {{ ucfirst($trail->action) }}
                            </span>
                        </div>
                        @if($trail->action === 'returned' || $trail->action === 'flagged' || $trail->action === 'rejected')
                            @if($trail->comments || $trail->remarks)
                                <div class="timeline__event__remarks">
                                    <strong>Remarks:</strong> {{ $trail->comments ?? $trail->remarks }}
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div class="timeline__event">
                    <div class="timeline__event__icon">
                        <i class="bx bx-time"></i>
                    </div>
                    <div class="timeline__event__content">
                        <div class="timeline__event__title">
                            No approval trail found
                        </div>
                        <div class="timeline__event__description">
                            <p class="text-muted">No approval history is available for this item.</p>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>