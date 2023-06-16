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

    public function get_status($flag = null)
    {
        $query = Employee::orderBy("lname", "desc");

        $results = $query->with('contracts', 'contracts.funder')
        ->whereHas('contracts', function (Builder $query) use ($flag) {
            $query->where('staff_contracts.status_id', '=', $flag);
        })
            ->take(20)
            ->skip(0)
            ->get();

        return $results;
    }

 
    
  
}