<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Particpants' Schedules</h5>
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
         $count++;
         $total_days = $schedule->staff->division_days + $schedule->staff->division_days;
        @endphp
        <tr>
            <td>{{$count}}</td>
            <td>{{$schedule ->staff->lname ." ".$schedule ->staff->lname}}</td>
            <td>{{$schedule ->staff->job_name}}</td>
            <td>{{$schedule->staff->division_days}}</td>
            <td>{{$schedule->staff->other_days}}</td>
            <td class="{{($total_days<21)?"":"bg-danger text-bold"}}">{{ $total_days}}</td>
        </tr>
        @endforeach

        </tbody>
        </table>
</div>
</div>