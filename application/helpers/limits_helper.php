<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

/*
*Developer:Agaba Andrew 2022
* Helps to filter dashboard data by financial year or by the user department
*andyear,whereyear,andsubject,wheresubject
*/
if (!function_exists('dashlimits')) {
 function dashlimits($type){
     if($type=='andyear'):
        return andyearlimit();
     endif;
     if($type=='whereyear'):
        return whereyearlimit();
     endif;
     if($type=='andsubject'):
        return andsubjectlimit();
     endif;
     if($type=='wheresubject'):
        return wheresubjectlimit();
     endif;
     

 }
}
 function andyearlimit(){
        if($_SESSION['fy']!=""){
            $fy=$_SESSION['fy'];
    return "and financial_year='$fy'";   
        }
        else{
   return "";        
        }
    
    }
function whereyearlimit(){
        if($_SESSION['fy']!=""){
            $fy=$_SESSION['fy'];
     return   "where financial_year='$fy'";   
        }
        else{
    return   "";        
        }
    
    }
   //subject area and
  function andsubjectlimit(){
        if($_SESSION['subject_area']!=""){
            @$id=implode(",",json_decode($_SESSION['subject_area']));
  return "and subject_areas.id in ($id)";   
        }
        else{
    return   "";        
        }
    
    }
  function wheresubjectlimit(){
          
        if($_SESSION['subject_area']!=""){
           @$id=implode(",",json_decode($_SESSION['subject_area']));
     return   "where subject_areas.id in ($id)";   
        }
        else{
   return "";        
        }
    
    }
    function andinfocategory(){
    $info_cat = $_SESSION['info_category'];
    if(isset($info_cat)){
        return "and subject_areas.info_category = $info_cat";
    }
    else{
        return "";
    }
    }
function whereinfocategory()
{
    $info_cat = $_SESSION['info_category'];
    if (isset($info_cat)) {
        return "where subject_areas.info_category = $info_cat";
    } else {
        return "";
    }
}
if (!function_exists('render_csv_data')) {
    function render_csv_data($datas, $filename, $use_columns = true)
    {
        //datas should be assoc array
       // ob_start();
        // write data to CSV file here
        ob_clean();
        $csv_file = $filename . ".csv";
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"$csv_file\"");
        $fh = fopen('php://output', 'w+b');

        $is_coloumn = $use_columns;
        if (!empty($datas)) {
             if ($is_coloumn) {
                    fputcsv($fh, array_keys(($datas[0])));
            foreach ($datas as $data) {

                   // $is_coloumn = false;
                    if (!empty($data)) {
                        fputcsv($fh, array_values($data));
                    }
                }
               
                
            }
            else{
                    
                    fputcsv($fh, $datas);

            
            }
            fclose($fh);
        }
        exit;
    }
    
}


if (!function_exists('session_headings')) {
    function session_headings($data)
    {
        if($data==''){
            return 'Subject Areas';
        
        }
        else{
            return '';
        }
    
    }
    if (!function_exists('get_info_category_name')) {
        function get_info_category_name($data)
        {

            $ci = &get_instance();
            return $ci->db->query("SELECT name from info_category where id= $data")->row()->name;
        }
    }

    if (!function_exists('mynotifications')) {
        function mynotifications($supervisor)
        {

           $ci = &get_instance();
           $ci->db->select('new_data.draft_status,new_data.period,new_data.ihris_pid,new_data.upload_date, new_data.financial_year, new_data.approved, new_data.supervisor_id,new_data.approved2, new_data.supervisor_id_2, ihrisdata.surname, ihrisdata.firstname, ihrisdata.othername, ihrisdata.facility_id,ihrisdata.facility, ihrisdata.job, new_data.job_id as kpi_group');
           $ci->db->from('new_data');
           $ci->db->join('ihrisdata', 'new_data.ihris_pid = ihrisdata.ihris_pid');
           $ci->db->join('kpi_job_category', 'new_data.job_id = kpi_job_category.job_id');
           $ci->db->where('new_data.draft_status', 1);
        
           $ci->db->group_start();
           $ci->db->or_where('new_data.supervisor_id', "$supervisor");
           $ci->db->or_where('new_data.supervisor_id_2', "$supervisor");
           $ci->db->group_end();
            $ci->db->group_start();
            $ci->db->or_where('new_data.approved', 0);
            $ci->db->or_where('new_data.approved2', '');
            $ci->db->group_end();
           $ci->db->group_by('new_data.financial_year, new_data.period,new_data.ihris_pid');
           $ci->db->order_by('new_data.financial_year', 'ASC');

           $query = $ci->db->get();
            //dd($this->db->last_query());
           return $query->num_rows();



        }
    }

    if (!function_exists('get_field')) {
        function get_field($user_id,$field_name)
        {

            $ci = &get_instance();
            return @$ci->db->query("SELECT $field_name from ihrisdata where ihris_pid= '$user_id'")->row()->$field_name;
        }
    }
//take into account also the draft or final status
    if (!function_exists('lockedfield')) {
    function lockedfield($handshake)
        {

    
    if (($handshake)==1) {
        return "readonly";

    } else {
       return  "";

    }
}}

    //color helper
    if (!function_exists('rowcolor')) {
        function rowcolor($status1)
        {


            if (($status1 == '0')|| ($status1=='')) {
                return '#F79500';

            } else if ($status1 == '1') {
                return 'green';

            } else if ($status1 == '2') {
                return '#FF0000';

            }
        }
    }

    if (!function_exists('get_field_facility')) {
        function get_field_by_facility($facility_id, $field_name)
        {

            $ci = &get_instance();
            return @$ci->db->query("SELECT DISTINCT $field_name from ihrisdata_staging where facility_id= '$facility_id'")->row()->$field_name;
        }
    }

}