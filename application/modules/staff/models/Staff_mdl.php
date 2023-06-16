<?php
use Illuminate\Database\Eloquent\Builder;
class Staff_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model("Employee");
        $this->load->model("Contracts");
    }

    public function get_all()
    {
       
      $query = Employee::orderBy("lname", "desc");
       

        // if (@$search['nationality_id'])
        // $query->where('nationality_id', $search->nationality_id);
        //implement ci-pagination later
        $results = $query->with('contracts', 'contracts.funder')
        ->take(20)->skip(20)->get();
        //$results = $query->with('contracts', 'contracts.funder')->get();

        return $results;
    }

    public function get_status($flag)
    {
        $query = Employee::orderBy("lname", "desc");

        $results = $query->with('contracts', 'contracts.funder')
        ->when($flag, function ($query, $flag) {
            $query->whereHas('contracts', function (Builder $query) use ($flag) {
                $query->where('status_id', $flag);
            });
               
        })
            ->take(20)
            ->skip(0)
            ->get();

        return $results;
    }
    public function getBirthdaysForToday()
    {
        // Get the current date
        $currentDate = date('Y-m-d');

        // Retrieve employees with birthdays for today
        return Employee::whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT('$currentDate', '%m-%d')")
        ->with('contracts')
        ->get();
    }

    public function getBirthdaysForTomorrow()
    {
        // Get the date for tomorrow
        $tomorrowDate = date('Y-m-d', strtotime('+1 day'));

        // Retrieve employees with birthdays for tomorrow
        return Employee::whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT('$tomorrowDate', '%m-%d')")
        ->with('contracts')
        ->get();
    }

    public function getBirthdaysForNextSevenDays()
    {
        // Get the current date
        $currentDate = date('Y-m-d');

        // Get the date for 7 days from now
        $nextSevenDays =date('Y-m-d', strtotime('+7 days'));

        // Retrieve employees with birthdays in the next 7 days
        return Employee::whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') BETWEEN DATE_FORMAT('$currentDate', '%m-%d') AND DATE_FORMAT('$nextSevenDays', '%m-%d')")
        ->with('contracts')
        ->get();
    }

    public function getBirthdaysForNextThirtyDays()
    {
        // Get the current date
        $currentDate = date('Y-m-d');

        // Get the date for 30 days from now
        $nextThirtyDays = date('Y-m-d', strtotime('+30 days'));

        // Retrieve employees with birthdays in the next 30 days
        return Employee::whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') BETWEEN DATE_FORMAT('$currentDate', '%m-%d') AND DATE_FORMAT('$nextThirtyDays', '%m-%d')") 
        ->with('contracts')
        ->get();

    }

 
    
  
}