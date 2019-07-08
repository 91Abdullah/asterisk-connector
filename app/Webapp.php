<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Webapp extends Model
{
    public $primaryKey = 'uid';
    public $incrementing = false;
    protected $fillable = ['uid', 'channel1','channel2','uniqueid1','uniqueid2','event','direction','from_number','to_number','starttime','endtime','totalduration','context','bridged','state','callcause','recordingpath','recordingurl'];
}
