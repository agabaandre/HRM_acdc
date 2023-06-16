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


    public function get_status()
    {
        // Filters on the contracts relationship
        // End date should use the between filter
        // Other filters on contract use whereHas
        $search_array_filters = [
            'contracting_institution_id' => '1',
            'funder_id' => '2',
            'contract_type_id' => '3',
            'status_id' => '2',
            'duty_station_id' => '3',
            'division_id' => '3',
            'start_date' => '2021-01-01',
            'end_date' => '2022-01-01'
        ];

        // Filters on Employee use whereHas
        $search_array2_filters = [
            'nationality_id' => '4',
            'gender' => 'Male'
        ];
        (object) $search_array2_filters;
        $query = Employee::orderBy("lname", "desc")->take(20)->skip(0);

        // Apply filters on contracts relationship
        $query->whereHas('contracts', function ($query) use ($search_array_filters) {
            foreach ($search_array_filters as $field => $value) {
                if ($field === 'end_date') {
                    $query->whereBetween($field, ['start_date', $value]);
                } else {
                    $query->where($field, $value);
                }
            }
        });

        // Apply filters on Employee
        $query->whereHas('nationality', function ($query) use ($search_array2_filters) {
            foreach ($search_array2_filters as $field => $value) {
                $query->where($field, $value);
            }
        });

        $results = $query->with('contracts', 'contracts.funder')->get();

        return $results;
    }


}