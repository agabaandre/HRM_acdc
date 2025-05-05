<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'divisions';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'division_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'division_name', 'division_head', 'focal_person', 'admin_assistant', 'finance_officer'
    ];
}