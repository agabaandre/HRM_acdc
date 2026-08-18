{{-- CI3-aligned printable performance form (PPA / Midterm / Endterm) --}}
@php
    $title = match ($phase->value) {
        'midterm' => 'MIDTERM REVIEW',
        'endterm' => 'ENDTERM REVIEW',
        default => 'PERFORMANCE PLANNING AGREEMENT',
    };
    $periodLabel = str_replace('-', ' ', (string) ($entry->performance_period ?? ''));
    $staffName = trim(($contract->fname ?? '').' '.($contract->lname ?? ''))
        ?: trim(($staff->fname ?? '').' '.($staff->lname ?? ''))
        ?: ('#'.$entry->staff_id);
    $isReview = in_array($phase->value, ['midterm', 'endterm'], true);
    $objRows = [];
    foreach ($objectives as $key => $row) {
        $obj = is_array($row) ? $row : (array) $row;
        if (\Modules\Performance\Support\PerformanceRichText::isEmpty($obj['objective'] ?? '')) {
            continue;
        }
        $objRows[] = $obj;
    }
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; }
  body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11pt;
    color: #0f172a;
    line-height: 1.55;
    margin: 0;
  }
  .header-tagline { font-size: 11pt; color: #911C39; font-weight: 500; text-align: right; }
  .doc-title { text-align: center; margin: 14px 0 18px; font-size: 15pt; font-weight: bold; letter-spacing: 0.4px; }
  .section-title {
    font-size: 12pt; font-weight: bold; margin: 18px 0 8px;
    border-bottom: 2px solid #e2e8f0; padding-bottom: 6px;
  }
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  td, th { border: 1px solid #e2e8f0; padding: 8px; text-align: left; vertical-align: top; }
  th { background: #f9fafb; font-weight: 600; }
  .muted { color: #64748b; font-size: 9pt; font-style: italic; }
  .page-break { page-break-before: always; }
  .html-cell { font-size: 10.5pt; line-height: 1.5; word-wrap: break-word; }
  .html-cell p { margin: 0 0 0.4em; }
  .html-cell p:last-child { margin-bottom: 0; }
  .html-cell ul, .html-cell ol { margin: 0 0 0.4em; padding-left: 1.2em; }
  .html-cell a { color: #0d7a3a; }
</style>
</head>
<body>
<div class="content">
  <table style="border:none;margin-bottom:4px;">
    <tr>
      <td style="border:none;width:65%;">
        @if(!empty($logoSrc))
          <img src="{{ $logoSrc }}" alt="Africa CDC" style="height:70px;">
        @else
          <strong style="font-size:14pt;color:#0d7a3a;">Africa CDC</strong>
        @endif
      </td>
      <td style="border:none;" class="header-tagline">Safeguarding Africa's Health</td>
    </tr>
  </table>
  <div class="doc-title">{{ $title }}</div>

  {{-- A. Staff Details --}}
  <table>
    <thead>
      <tr><th colspan="4"><strong>A. Staff Details</strong></th></tr>
    </thead>
    <tbody>
      <tr>
        <td><b>Name</b></td><td>{{ $staffName }}</td>
        <td><b>SAP NO</b></td><td>{{ $contract->SAPNO ?? ($staff->SAPNO ?? '—') }}</td>
      </tr>
      <tr>
        <td><b>Position</b></td><td>{{ $contract->job_name ?? '—' }}</td>
        <td><b>In this Position Since</b></td><td>{{ $contract->start_date ?? ($contract->initiation_date ?? '—') }}</td>
      </tr>
      <tr>
        <td><b>Division/Directorate</b></td><td>{{ $contract->division_name ?? '—' }}</td>
        <td><b>Performance Period</b></td><td>{{ $periodLabel }}</td>
      </tr>
      <tr>
        <td><b>First Supervisor</b></td><td>{{ $supervisor1Name ?: '—' }}</td>
        <td><b>Second Supervisor</b></td><td>{{ $supervisor2Name ?: '—' }}</td>
      </tr>
      <tr>
        <td><b>Funder</b></td><td>{{ $contract->funder ?? '—' }}</td>
        <td><b>Contract Type</b></td><td>{{ $contract->contract_type ?? '—' }}</td>
      </tr>
    </tbody>
  </table>

  {{-- B. Objectives --}}
  <table class="objective-table">
    <thead>
      <tr>
          <th colspan="{{ $isReview ? 7 : 5 }}">
          <strong>B. {{ $isReview ? ($phase->value === 'midterm' ? 'Midterm Objectives Review' : 'Endterm Objectives Review') : 'Performance Objectives' }}</strong>
          <div class="muted">Individual objectives should be derived from the Departmental Work Plan.</div>
        </th>
      </tr>
      <tr>
        <th style="width:4%;">#</th>
        <th>Objective<br><span class="muted">Statement of the result that needs to be achieved</span></th>
        <th style="width:12%;">Timeline<br><span class="muted">Timeframe</span></th>
        <th>Deliverables and KPI's<br><span class="muted">Evidence / KPIs</span></th>
        <th style="width:8%;">Weight<br><span class="muted">Total 100%</span></th>
        @if($isReview)
          <th>Staff Self Appraisal</th>
          <th style="width:10%;">Appraiser's Rating</th>
        @endif
      </tr>
    </thead>
    <tbody>
      @forelse($objRows as $i => $obj)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($obj['objective'] ?? '') !!}</td>
          <td>{{ $obj['timeline'] ?? '' }}</td>
          <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($obj['indicator'] ?? '') !!}</td>
          <td>{{ $obj['weight'] ?? '' }}</td>
          @if($isReview)
            <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($obj['self_appraisal'] ?? '') !!}</td>
            <td>{{ $obj['appraiser_rating'] ?? '' }}</td>
          @endif
        </tr>
      @empty
        <tr><td colspan="{{ $isReview ? 7 : 5 }}">No objectives recorded.</td></tr>
      @endforelse
    </tbody>
  </table>

  @if($phase->value === 'ppa')
    {{-- C. Competencies --}}
    <table>
      <thead><tr><th colspan="3"><strong>C. Competencies</strong></th></tr></thead>
      <tbody>
        <tr>
          <td colspan="3" class="muted">
            All staff members shall be assessed against AU Values and Core and Functional Competencies;
            staff with managerial responsibilities will also be rated on the Leadership Competencies.
          </td>
        </tr>
        <tr><td colspan="3"><b>AU Values</b><br>Respect for Diversity and Teamwork – Think Africa Above All – Transparency and Accountability – Integrity and Impartiality – Efficiency and Professionalism – Information and Knowledge Sharing</td></tr>
        <tr style="background:#f9fafb;font-weight:600;text-align:center;">
          <td>Core</td><td>Functional</td><td>Leadership</td>
        </tr>
        <tr><td>Building Relationships</td><td>Conceptual Thinking and Problem Solving</td><td>Strategic Perspective</td></tr>
        <tr><td>Responsibility</td><td>Job Knowledge</td><td>Developing Others</td></tr>
        <tr><td>Learning Orientation</td><td>Drive for Results</td><td>Driving Change</td></tr>
        <tr><td>Communicating with Impact</td><td>Innovative and Taking Initiative</td><td>Managing Risk</td></tr>
      </tbody>
    </table>

    <div class="page-break"></div>

    {{-- D. PDP --}}
    <table>
      <thead><tr><th colspan="2"><strong>D. Personal Development Plan</strong></th></tr></thead>
      <tbody>
        <tr>
          <td style="width:45%;"><b>Is training recommended for this staff member?</b></td>
          <td>{{ $entry->training_recommended ?? 'No' }}</td>
        </tr>
        @if(($entry->training_recommended ?? 'No') === 'Yes')
          <tr>
            <td><b>Subject/skill area(s) recommended</b></td>
            <td>{{ $skillsLabel ?: '—' }}</td>
          </tr>
          <tr>
            <td><b>How training will contribute to development and the department's work</b></td>
            <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($entry->training_contributions ?? '') !!}</td>
          </tr>
          <tr>
            <td><b>Recommended course(s) from the AUC L&D Catalogue</b></td>
            <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($entry->recommended_trainings ?? '') !!}</td>
          </tr>
          <tr>
            <td><b>Other recommendable course(s)</b></td>
            <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($entry->recommended_trainings_details ?? '') !!}</td>
          </tr>
        @endif
      </tbody>
    </table>

    {{-- E. Sign off --}}
    <div class="section-title">E. Staff and Supervisor Sign Off</div>
    <table>
      <tr>
        <td style="width:50%;height:70px;"><b>Staff</b><br>{{ $staffName }}</td>
        <td><b>First Supervisor</b><br>{{ $supervisor1Name ?: '—' }}</td>
      </tr>
      <tr>
        <td><b>Second Supervisor</b><br>{{ $supervisor2Name ?: '—' }}</td>
        <td><b>Period</b><br>{{ $periodLabel }}</td>
      </tr>
    </table>
  @endif

  @if($phase->value === 'midterm')
    <table>
      <thead><tr><th colspan="2"><strong>C. Appraiser's Comments</strong></th></tr></thead>
      <tbody>
        <tr>
          <td style="width:35%;"><b>Comments</b></td>
          <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($entry->midterm_comments ?? '') !!}</td>
        </tr>
      </tbody>
    </table>
    <table>
      <thead><tr><th colspan="2"><strong>E. Personal Development Plan – Progress Review</strong></th></tr></thead>
      <tbody>
        <tr>
          <td style="width:35%;"><b>Training review</b></td>
          <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($entry->midterm_training_review ?? '') !!}</td>
        </tr>
        <tr>
          <td><b>Achievements</b></td>
          <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($entry->midterm_achievements ?? '') !!}</td>
        </tr>
        <tr>
          <td><b>Non-achievements</b></td>
          <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($entry->midterm_non_achievements ?? '') !!}</td>
        </tr>
      </tbody>
    </table>
    <div class="section-title">F. Staff and Supervisor Sign Off</div>
    <table>
      <tr>
        <td style="width:50%;height:70px;"><b>Staff</b><br>{{ $staffName }}</td>
        <td><b>First Supervisor</b><br>{{ $supervisor1Name ?: '—' }}</td>
      </tr>
    </table>
  @endif

  @if($phase->value === 'endterm')
    <table>
      <thead><tr><th colspan="2"><strong>C. Appraiser's Comments</strong></th></tr></thead>
      <tbody>
        <tr>
          <td style="width:35%;"><b>Comments</b></td>
          <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($entry->endterm_comments ?? '') !!}</td>
        </tr>
      </tbody>
    </table>
    <table>
      <thead><tr><th colspan="2"><strong>E. Endterm Review Comments &amp; Training</strong></th></tr></thead>
      <tbody>
        <tr>
          <td style="width:35%;"><b>Training review</b></td>
          <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($entry->endterm_training_review ?? '') !!}</td>
        </tr>
        <tr>
          <td><b>Achievements</b></td>
          <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($entry->endterm_achievements ?? '') !!}</td>
        </tr>
        <tr>
          <td><b>Non-achievements</b></td>
          <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($entry->endterm_non_achievements ?? '') !!}</td>
        </tr>
      </tbody>
    </table>
    <div class="section-title">F. Overall Rating and Supervisor Signoff</div>
    <table>
      <tr>
        <td style="width:50%;"><b>Overall rating</b><br>{{ $entry->endterm_overall_rating ?? '—' }}</td>
        <td><b>Staff consent</b><br>{{ !empty($entry->endterm_staff_consent) ? 'Yes' : '—' }}</td>
      </tr>
    </table>
    <div class="section-title">G. Staff Sign Off</div>
    <table>
      <tr>
        <td style="width:50%;height:70px;"><b>Staff</b><br>{{ $staffName }}</td>
        <td><b>First Supervisor</b><br>{{ $supervisor1Name ?: '—' }}</td>
      </tr>
    </table>
  @endif

  @if($withTrail)
    <div class="page-break"></div>
    <div class="section-title">{{ $phase->value === 'ppa' ? 'E' : ($phase->value === 'midterm' ? 'G' : 'G') }}. Approval Trail</div>
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Role</th>
          <th>Action</th>
          <th>Date</th>
          <th>Comment</th>
        </tr>
      </thead>
      <tbody>
        @forelse($trail as $log)
          @php
            $sid = (int) ($log->staff_id ?? 0);
            if ($sid === (int) $entry->staff_id) {
                $role = 'Staff';
            } elseif ($sid === (int) ($entry->supervisor_id ?? 0) || $sid === (int) ($entry->midterm_supervisor_1 ?? 0) || $sid === (int) ($entry->endterm_supervisor_1 ?? 0)) {
                $role = 'First Supervisor';
            } elseif ($sid === (int) ($entry->supervisor2_id ?? 0) || $sid === (int) ($entry->midterm_supervisor_2 ?? 0) || $sid === (int) ($entry->endterm_supervisor_2 ?? 0)) {
                $role = 'Second Supervisor';
            } else {
                $role = 'Other';
            }
            $when = $log->created_at ?? null;
            try {
                $whenLabel = $when ? \Carbon\Carbon::parse($when)->format('d M Y H:i') : '—';
            } catch (\Throwable) {
                $whenLabel = (string) $when;
            }
          @endphp
          <tr>
            <td>{{ $log->staff_name ?? ('#'.$sid) }}</td>
            <td>{{ $role }}</td>
            <td>{{ $log->action ?? '' }}</td>
            <td>{{ $whenLabel }}</td>
            <td class="html-cell">{!! \Modules\Performance\Support\PerformanceRichText::toSafeHtml($log->comments ?? '') !!}</td>
          </tr>
        @empty
          <tr><td colspan="5">No approval trail entries.</td></tr>
        @endforelse
      </tbody>
    </table>
  @endif

  <p class="muted" style="margin-top:18px;">Generated {{ $generatedAt }}</p>
</div>
</body>
</html>
