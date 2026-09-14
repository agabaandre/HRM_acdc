<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticipantSchedule extends Model
{

    protected $guarded = [];
    public function staff(){
        return $this->belongsTo(Staff::class,"participant_id","staff_id");
    }

    public function activity(){
        return $this->belongsTo(Activity::class,"activity_id");
    }

    public function matrix(){
        return $this->belongsTo(Matrix::class,"matrix_id");
    }

}
