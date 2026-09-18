<h4 class="mt-4">A. Personal Details</h4>
<div class="table-responsive">
  <table class="table table-bordered">
    <tr>
      <th>Name</th>
      <td>{{ $contract->fname }} {{ $contract->lname }}</td>
      <th>Personnel Number (SAP NO)</th>
      <td>{{ $contract->SAPNO ?? '—' }}</td>
    </tr>
    <tr>
      <th>Position</th>
      <td>{{ $contract->job_name ?? '—' }}</td>
      <th>In this Position Since</th>
      <td>{{ $contract->initiation_date ?? '—' }}</td>
    </tr>
    <tr>
      <th>Directorate/Department</th>
      <td>{{ $contract->division_name ?? '—' }}</td>
      <th>Performance Period</th>
      <td>{{ $periodLabel }}</td>
    </tr>
    <tr>
      <th>Direct Supervisor</th>
      <td>{{ $supervisors->staffName($supervisorId ?: $contract->first_supervisor ?? null) }}</td>
      <th>Second Supervisor</th>
      <td>{{ $supervisors->staffName($supervisor2Id ?: $contract->second_supervisor ?? null) }}</td>
    </tr>
    <tr>
      <th>Funder</th>
      <td>{{ $contract->funder ?? '—' }}</td>
      <th>Contract Type</th>
      <td>{{ $contract->contract_type ?? '—' }}</td>
    </tr>
  </table>
</div>
