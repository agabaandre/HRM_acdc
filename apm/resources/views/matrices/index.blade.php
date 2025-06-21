@extends('layouts.app')

@section('title', 'Quarterly Travel Matrices')
@section('header', 'Quarterly Travel Matrices')

@section('header-actions')
@php $isFocal = isfocal_person(); @endphp
@if ($isFocal)
<a href="{{ route('matrices.create') }}" class="btn btn-success">
    <i class="bx bx-plus"></i> Create New Matrix
</a>
@endif
@endsection

@php
//dd($matrices->toArray());
@endphp

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <div class="row g-2 align-items-center">
            <div class="col-md-2">
                <select class="form-select" id="yearFilter">
                    <option value="">All Years</option>
                    @foreach(range(date('Y'), date('Y') - 5) as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="quarterFilter">
                    <option value="">All Quarters</option>
                    @foreach(['Q1', 'Q2', 'Q3', 'Q4'] as $quarter)
                        <option value="{{ $quarter }}">{{ $quarter }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select select2" id="divisionFilter">
                    <option value="">All Divisions</option>
                    @foreach($divisions as $division)
                        <option value="{{ $division->id }}">{{ $division->division_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select select2" id="focalFilter">
                    <option value="">All Focal Persons</option>
                    @foreach($focalPersons as $person)
                        <option value="{{ $person->staff_id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Year</th>
                        <th>Quarter</th>
                        <th>Division</th>
                        <th>Focal Person</th>
                        <th>Key Result Areas</th>
                        <th>Created At</th>
                        <th>Level</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>

                   @php 

                    //dd($matrix);
                    @endphp
                <tbody>
                    @php
                        $count = 1;
                    @endphp
                    @forelse($matrices as $matrix)
                          
                        <tr>
                            <td>{{$count}}</td>
                            <td>{{ $matrix->year }}</td>
                            <td>{{ $matrix->quarter }}</td>
                            <td>{{ $matrix->division->division_name ?? 'N/A' }}</td>
                            <td>{{ $matrix->focalPerson->name ?? 'N/A' }}</td>
                            <td>
                                @php
                                    $kras = is_string($matrix->key_result_area)
                                        ? json_decode($matrix->key_result_area, true)
                                        : $matrix->key_result_area;
                                @endphp
                                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#kraModal{{ $matrix->id }}">
                                    <i class="bx bx-list-check me-1"></i> {{ is_array($kras) ? count($kras) : 0 }} Area(s)
                                </button>

                                <!-- Modal -->
                                <div class="modal fade" id="kraModal{{ $matrix->id }}" tabindex="-1" aria-labelledby="kraModalLabel{{ $matrix->id }}" aria-hidden="true">
                                    <div class="modal-dialog modal-md modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="kraModalLabel{{ $matrix->id }}">
                                                    Key Result Areas - {{ $matrix->year }} {{ $matrix->quarter }}
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                @if(is_array($kras) && count($kras))
                                                    <ul class="list-group">
                                                        @foreach($kras as $kra)
                                                            <li class="list-group-item">
                                                                <i class="bx bx-check-circle text-success me-2"></i> {{ $kra['description'] ?? '' }}
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <p class="text-muted">No key result areas defined.</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $matrix->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ ($matrix->overall_status=='approved')?'Registry':(($matrix->workflow_definition)?$matrix->workflow_definition->role:'Focal Person') }} <small class="text-muted">{{ ($matrix->current_actor)? "(".$matrix->current_actor->fname ." ".$matrix->current_actor->lname.")":""}}</small></td>
                            <td> <span class="p-1 rounded {{config('approval_states')[$matrix->overall_status]}}">{{ strtoupper($matrix->overall_status)}}</span></td>
                            <td class="text-left">
                                <div class="btn-group">
                                    <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-sm btn-outline-info" title="View">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    @if(still_with_creator($matrix))
                                    <a href="{{ route('matrices.edit', $matrix) }}" class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @php
                        $count++;
                       @endphp
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bx bx-calendar-x fs-1 opacity-50"></i>
                                <p>No matrices found.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($matrices->hasPages())
        <div class="card-footer bg-light py-2">
            {{ $matrices->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        // Pre-fill filters from URL
        const params = new URLSearchParams(window.location.search);
        $('#yearFilter').val(params.get('year') || '');
        $('#quarterFilter').val(params.get('quarter') || '');
        $('#divisionFilter').val(params.get('division') || '');
        $('#focalFilter').val(params.get('focal_person') || '');

        // Apply Select2
        $('.select2').select2({ width: '100%' });

        // Handle filter change
        $('#yearFilter, #quarterFilter, #divisionFilter, #focalFilter').change(function () {
            const url = new URL(window.location.href);
            ['year', 'quarter', 'division', 'focal_person'].forEach(id => {
                const val = $('#' + id + 'Filter').val();
                if (val) url.searchParams.set(id, val);
                else url.searchParams.delete(id);
            });
            window.location.href = url.toString();
        });
    });
</script>
@endpush
