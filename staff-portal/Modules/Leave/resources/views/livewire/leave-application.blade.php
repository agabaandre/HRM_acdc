<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-success fw-bold mb-0">Apply for leave</h4>
        <a href="{{ route('leave.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <form wire:submit="submit" class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Leave type</label>
                    <select class="form-select" wire:model.live="leave_id" required>
                        <option value="0">Select type…</option>
                        @foreach ($leaveTypes as $type)
                            <option value="{{ $type->leave_id }}">{{ $type->leave_name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($selectedBalance)
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Available balance</label>
                        <div class="alert alert-light border mb-0 py-2">
                            <strong>{{ $selectedBalance['available'] }}</strong> days available
                            (used: {{ $selectedBalance['used'] }}, pending: {{ $selectedBalance['pending'] }})
                        </div>
                    </div>
                @endif
                <div class="col-md-4">
                    <label class="form-label">Start date</label>
                    <input type="date" class="form-control" wire:model.live="start_date" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">End date</label>
                    <input type="date" class="form-control" wire:model.live="end_date" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Working days requested</label>
                    <input type="number" class="form-control" wire:model="requested_days" min="1" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email while on leave</label>
                    <input type="email" class="form-control" wire:model="email_leave" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mobile while on leave</label>
                    <input type="text" class="form-control" wire:model="mobile_leave" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Supporting officer (staff ID or name)</label>
                    <input type="text" class="form-control" wire:model="supporting_staff">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Supporting document</label>
                    <input type="file" class="form-control" wire:model="document">
                    @error('document')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control" rows="3" wire:model="remarks"></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white">
            <button type="submit" class="btn btn-success" wire:loading.attr="disabled">Submit request</button>
        </div>
    </form>
</div>
