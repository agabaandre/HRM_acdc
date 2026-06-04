<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-success fw-bold mb-0">Settings</h4>
        <a href="{{ route('settings.leave') }}" class="btn btn-outline-success btn-sm">Leave configuration</a>
    </div>
    <input type="search" class="form-control form-control-sm mb-3" placeholder="Search settings…" wire:model.live="search">
    <div class="row g-3">
        @foreach ($cards as $card)
            <div class="col-6 col-md-4 col-lg-3">
                <a href="{{ isset($card['params']) ? route($card['route'], $card['params']) : route($card['route']) }}"
                   class="text-decoration-none text-dark">
                    <div class="card border shadow-sm h-100 settings-card-hover">
                        <div class="card-body d-flex justify-content-between align-items-center py-3">
                            <h6 class="mb-0 small">{{ $card['label'] }}</h6>
                            <i class="bx {{ $card['icon'] }} text-muted fs-5"></i>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
    <style>.settings-card-hover:hover { box-shadow: 0 .5rem 1rem rgba(0,0,0,.1)!important; transform: translateY(-2px); transition: .2s; }</style>
</div>
