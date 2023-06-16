<?php

use \Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Capsule\Manager as Capsule;

class Contracts extends Eloquent{
    protected $table = 'staff_contracts';
    public $appends = ['status','funder_name',"grade_name","job_name","contractor_name",'station_name','jobacting_name','division_name','contract_type_name'];

    public function funder(){
        return $this->belongsTo(Funder::class, 'funder_id', 'funder_id');
    }
     public function grade(){
        return $this->belongsTo(Grades::class, 'grade_id', 'grade_id');
       
    }
    public function job()
    {
        return $this->belongsTo(Grades::class, 'job_id', 'job_id');
    }

    public function getStatusAttribute(){

        $statuses = Capsule::table("status")->get()->pluck("status","status_id");
        return $statuses[$this->status_id];
    }

     public function getFunderNameAttribute(){

        $funders = Capsule::table("funders")->get()->pluck("funder","funder_id");
        return $funders[$this->funder_id];
    }

     public function getGradeNameAttribute(){

        $grades= Capsule::table("grades")->get()->pluck("grade","grade_id");
        return $grades[$this->grade_id];
    }
    public function getJobNameAttribute()
    {

        $jobs = Capsule::table("jobs")->get()->pluck("job_name", "job_id");
        return $jobs[$this->job_id];
    }
    public function getContractorNameAttribute()
    {

        $contractor = Capsule::table("contracting_institutions")->get()->pluck("contracting_institution", "contracting_institution_id");
        return $contractor[$this->contracting_institution_id];
    }
    public function getDivisionNameAttribute()
    {

        $division = Capsule::table("divisions")->get()->pluck("division_name", "division_id");
        return $division[$this->division_id];
    }
    public function getStationNameAttribute()
    {

        $station = Capsule::table("duty_stations")->get()->pluck("duty_station_name", "duty_station_id");
        return $station[$this->duty_station_id];
    }
    public function getJobactingNameAttribute()
    {

        $job_acting = Capsule::table("jobs_acting")->get()->pluck("job_acting", "job_acting_id");
        return $job_acting[$this->job_acting_id];
    }
    public function getContractTypeNameAttribute()
    {

        $contract_types = Capsule::table("contract_types")->get()->pluck("contract_type", "contract_type_id");
        return $contract_types[$this->contract_type_id];
    }
}
