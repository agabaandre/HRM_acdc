<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

/*
*
* Developed by <henricsanyu@gmail.com>
*
* $autoload['helper'] =  array('kpiTrend');
* display a language
* echo kpiTrend($params); 
*
*/

if (!function_exists('kpiTrend')) {

    function kpiTrend($params)
    {
        $params = (Object) $params;

        $current_target      = $params->current_target;
        $gauge_value         = $params->current_value;
        $previousgauge_value = $params->prev_value;
        $current_period      = $params->current_period;
        $previous_period     = $params->prev_period;

         if ($previous_period!=0){
             $previous_period='for '. $previous_period;
         }
         else{
            $previous_period='';
         }

         $arrow = "fa fa-arrow-down";
         $color = "red";

       //increasing
         if(($current_target)>=40){


             if ($gauge_value > $previousgauge_value){
                $arrow = "fa fa-arrow-up";
                $color = "green";
             } 
             elseif ($gauge_value == $previousgauge_value){

                $arrow = "fa fa-arrow-right";
                $color = "orange";
            }
            else
            {
                $arrow = "fa fa-arrow-down";
                $color = "red";
            }

        }  
         
        //reducing
        if(($current_target)<40){

            if ($gauge_value < $previousgauge_value){

                $arrow = "fa fa-arrow-up";
                $color = "green";
             } 
             elseif ($gauge_value == $previousgauge_value){

                $arrow = "fa fa-arrow-right";
                $color = "orange";
             } 
             
             else
             {
                $arrow = "fa fa-arrow-down";
                $color = "red";
             }
         }  


         $gaugeValue          = round($gauge_value);
         $previousPeriodValue = round($previousgauge_value);

         return  '<i class="fa '.$arrow.'" style="color:'.$color.';margin-bottom:10px;"></i> '.$gaugeValue.'% for  '.$current_period.'  compared to '.$previousPeriodValue .'% '.$previous_period=FALSE.'';
 
    }
    if (!function_exists('get_field')) {
        function data_value($user_id, $kpi_id, $fy, $period)
        {
            $ci = &get_instance();
            $ci->db->select('new_data.numerator, new_data.denominator, new_data.data_target, new_data.comment, new_data.ihris_pid,new_data.draft_status');
            $ci->db->from('new_data');
            $ci->db->where('new_data.ihris_pid', $user_id);
            $ci->db->where('new_data.kpi_id', "$kpi_id");
            $ci->db->where('new_data.period', "$period");
            $ci->db->where('new_data.financial_year', "$fy");
           $query = $ci->db->get()->row();

            return $query;

        }




    }

    if (!function_exists('supervisor')) {
        function supervisor($supervisor_id)
        {
            $ci = &get_instance();
           $query = $ci->db->query("SELECT surname, firstname, othername  from ihrisdata_staging where ihris_pid='$supervisor_id'")->row();
           

       return  $query->surname. ' '.$query->firstname. ' '.$query->othername;


        }



    }

    
 
}

