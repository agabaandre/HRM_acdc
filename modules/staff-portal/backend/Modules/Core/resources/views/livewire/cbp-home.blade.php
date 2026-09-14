<div class="cbp-home-shell py-2">
    <div class="mb-4">
        <h4 class="mb-1 text-success fw-bold">Central Business Platform</h4>
        <p class="text-muted mb-0">Select a module to continue</p>
    </div>
    <div class="row g-3">
        @forelse ($modules as $module)
            <div class="col-md-6 col-lg-4">
                <a href="{{ $module['href'] }}" class="card h-100 text-decoration-none border-0 shadow-sm module-tile">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 d-flex align-items-center justify-content-center" style="min-width:3.25rem;min-height:3.25rem;">
                                <i class="{{ \App\Support\CbpIcon::classes($module['icon'] ?? null, 'text-success fs-4') }}" aria-hidden="true"></i>
                            </div>
                            <div>
                                <h5 class="card-title text-dark mb-1">{{ $module['label'] }}</h5>
                                <p class="card-text text-muted small mb-0">{{ $module['desc'] }}</p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-light border text-muted mb-0">
                    No modules are available for your account. If you expect access, ask an administrator to assign the correct permission group.
                </div>
            </div>
        @endforelse
    </div>
</div>
