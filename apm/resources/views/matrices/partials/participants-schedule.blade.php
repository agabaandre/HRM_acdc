<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Particpants' Schedules {{ $matrix->quarter."-".$matrix->year }}</h5>
    </div>
    <div class="card-body">
        <table class="table table-striped">
        <thead>
        <th>#</th>
        <th>Staff Name</th>
        <th>Position</th>
        <th>Days In Division</th>
        <th>Days Outside Disiion</th>
        <th>Total Days</th>
        <tbody>
        @php
            $count =0;
        @endphp
        @foreach($matrix->division_schedule as $schedule)
        @php
         $quarter_year = $matrix->quarter."-".$matrix->year;
         $count++;
         $division_days = (isset($schedule->staff->division_days[$quarter_year]))?$schedule->staff->division_days[$quarter_year]:0;
         $other_days = (isset($schedule->staff->other_days[$quarter_year]))?$schedule->staff->other_days[$quarter_year]:0;
         $total_days = $division_days + $other_days;
        @endphp
        <tr>
            <td>{{$count}}</td>
            <td>{{$schedule ->staff->lname ." ".$schedule ->staff->lname}}</td>
            <td>{{$schedule ->staff->job_name}}</td>
            <td>{{$division_days}}</td>
            <td>{{$other_days}}</td>
            <td class="{{($total_days<21)?"":"bg-danger text-bold"}}">{{ $total_days}}</td>
        </tr>
        @endforeach

        </tbody>
        </table>
</div>
</div>