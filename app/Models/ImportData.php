<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ImportColumns;
use App\Models\Files;

class ImportData extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'project_id',
        'row_details',
        'task_status',
        'integrity_status',
    ];

    // public function getRowDetailsAttribute($value)
    // {
    // $find_file = Files::where('project_id', $this->attributes['project_id'])->where('type', 'Import')->first();
    // $all_columns = ImportColumns::where('file_id', $find_file->id)->pluck('column_heading')->toArray();
    // $data = json_decode($value);
    // $return_final = array();
    // foreach ($data as $k => $v) {
    //     $row_data = (array)$v;
    //     $return_final[] = array_combine($all_columns, array_values($row_data));
    // }
    // return $return_final;
    // return "Adsasda";
    // }


    public function getRowDetailsAttribute($value)
    {
        // if ($this->attributes['project_id'] != NULL && $this->attributes['project_id'] != '') {
        //     $all_columns = ImportColumns::where('project_id', $this->attributes['project_id'])->where('status', 1)->pluck('column_heading')->toArray();
        //     $all_data = json_decode($value);
        //     if (!empty($all_data) && !empty($all_columns)) {
        //         $all_columns = array_map('strtolower', $all_columns);
        //         foreach ($all_data as $rhead => $rval) {
        //             if (!in_array(strtolower($rhead), $all_columns)) {
        //                 unset($all_data->$rhead);
        //             }
        //         }
        //         return $all_data;
        //     } else {
        //         return array();
        //     }
        // } else {
            return json_decode($value);
        // }
    }
}
