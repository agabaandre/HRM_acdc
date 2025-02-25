<?php

use \Illuminate\Database\Eloquent\Model as Eloquent;


class Employee extends Eloquent{
    protected $table = 'staff';

    public function contracts()
    {
        return $this->hasMany(Contracts::class,"staff_id","staff_id");
    }
    public function nationality()
    {
        return $this->belongsTo(Nationality::class, "nationality_id", "nationality_id");
    }
    public function emailstatus()
    {
        return $this->hasMany(emailstatus::class,"staff_id","staff_id");
    }
}