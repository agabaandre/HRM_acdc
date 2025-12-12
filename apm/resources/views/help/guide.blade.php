@extends('layouts.app')

@section('title', $title)
@section('header', $title)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-{{ $guideType === 'user' ? 'user' : 'user-check' }} me-2 text-{{ $guideType === 'user' ? 'primary' : 'info' }}"></i>{{ $title }}
                    </h4>
                    <div>
                        <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Help
                        </a>
                        @if($guideType === 'user')
                        <a href="{{ route('help.approvers-guide') }}" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-user-check me-1"></i>Approvers Guide
                        </a>
                        @else
                        <a href="{{ route('help.user-guide') }}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-user me-1"></i>User Guide
                        </a>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="guide-content">
                        {!! $content !!}
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>Last Updated: December 2024
                        </small>
                        <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-home me-1"></i>Back to Help Center
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .guide-content {
        line-height: 1.8;
        color: #333;
    }
    
    .guide-content h1 {
        color: #119a48;
        border-bottom: 3px solid #119a48;
        padding-bottom: 10px;
        margin-bottom: 30px;
        margin-top: 20px;
    }
    
    .guide-content h2 {
        color: #28a745;
        margin-top: 40px;
        margin-bottom: 20px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e9ecef;
    }
    
    .guide-content h3 {
        color: #17a2b8;
        margin-top: 30px;
        margin-bottom: 15px;
    }
    
    .guide-content h4 {
        color: #6c757d;
        margin-top: 25px;
        margin-bottom: 12px;
    }
    
    .guide-content p {
        margin-bottom: 15px;
        text-align: justify;
    }
    
    .guide-content ul, .guide-content ol {
        margin-bottom: 20px;
        padding-left: 30px;
    }
    
    .guide-content li {
        margin-bottom: 8px;
    }
    
    .guide-content code {
        background: #f8f9fa;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
        color: #e83e8c;
    }
    
    .guide-content pre {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 15px;
        overflow-x: auto;
        margin: 20px 0;
    }
    
    .guide-content pre code {
        background: none;
        padding: 0;
        color: #333;
    }
    
    .guide-content table {
        width: 100%;
        margin: 20px 0;
        border-collapse: collapse;
    }
    
    .guide-content table th,
    .guide-content table td {
        border: 1px solid #dee2e6;
        padding: 12px;
        text-align: left;
    }
    
    .guide-content table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #495057;
    }
    
    .guide-content blockquote {
        border-left: 4px solid #119a48;
        padding-left: 20px;
        margin: 20px 0;
        color: #6c757d;
        font-style: italic;
    }
    
    .guide-content a {
        color: #119a48;
        text-decoration: none;
    }
    
    .guide-content a:hover {
        text-decoration: underline;
    }
    
    .screenshot-placeholder {
        background: #f8f9fa;
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        padding: 60px 20px;
        margin: 30px 0;
        text-align: center;
        min-height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .screenshot-placeholder .placeholder-content {
        color: #6c757d;
    }
    
    .screenshot-placeholder i {
        font-size: 4rem;
        margin-bottom: 15px;
        color: #adb5bd;
        display: block;
    }
    
    .screenshot-placeholder p {
        font-weight: 600;
        margin: 10px 0 5px 0;
        color: #495057;
        font-size: 1.1rem;
    }
    
    .screenshot-placeholder small {
        color: #868e96;
        font-style: italic;
        display: block;
        margin-top: 5px;
    }
    
    .guide-content hr {
        margin: 40px 0;
        border: none;
        border-top: 2px solid #e9ecef;
    }
    
    @media print {
        .card-header, .card-footer {
            display: none;
        }
        
        .guide-content {
            font-size: 12pt;
        }
    }
</style>
@endpush

