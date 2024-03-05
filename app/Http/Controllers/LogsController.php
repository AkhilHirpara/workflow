<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Lang;
use App\Models\Logs;
use App\Models\User_Sessions;
use App\Models\EmailLogs;
use Carbon\Carbon;


class LogsController extends Controller
{
    
    // get all site logs
    public function getsitelogs(){
        $getdata = Logs::query()->orderBy('id','desc');
        $all_data = $getdata->with('UserDetails')->get();
        $all_data = $getdata->get();
        foreach ($all_data as $each_data) {
            $each_data->created_time = date("d/m/Y h:i:s A", strtotime($each_data->created_at));
            if (!empty($each_data->UserDetails)) {
                $each_data->username = $each_data->UserDetails->firstname . ' ' . $each_data->UserDetails->lastname;
            }
            unset($each_data->UserDetails);
        }
        return ($all_data) ? success($all_data) : error();
    }

    // delete all logs before N months
    public function deletesitelogs(Request $request){
        if($request->noofmonths != ''){
            $no_of_months = $request->noofmonths;
            if($no_of_months >= '1' && $no_of_months <= '12' ){
                $deleted_data = Logs::where("created_at","<", Carbon::now()->subMonths($no_of_months))->delete();
                return success('','Site logs deleted successfully');
            }
            else{
                return error(['Enter a valid number between 1 & 12']);
            }
        }
        else{
            return error(['Parameter missing']);
        }
        
        
    }

    // get all session logs
    public function getsessionlogs(){
        $getdata = User_Sessions::query()->orderBy('id','desc');
        $all_data = $getdata->with('UserDetails')->get();
        $all_data = $getdata->get();
        foreach ($all_data as $each_data) {
            if (!empty($each_data->UserDetails)) {
                $each_data->username = $each_data->UserDetails->firstname . ' ' . $each_data->UserDetails->lastname;
            }
            $each_data->login_time = date("d/m/Y h:i:s A", strtotime($each_data->login_time));
            $each_data->logout_time = date("d/m/Y h:i:s A", strtotime($each_data->logout_time));

            // $formattedDate = date("d/m/Y h:i:s A", strtotime($each_data->created_at));
            // $each_data->created_time = $formattedDate;
            unset($each_data->UserDetails);
        }
        return ($all_data) ? success($all_data) : error();
    }

    // delete all sessions before N months
    public function deletesessionlogs(Request $request){
        if($request->noofmonths != ''){
            $no_of_months = $request->noofmonths;
            if($no_of_months >= '1' && $no_of_months <= '12' ){
                $deleted_data = User_Sessions::where("created_at","<", Carbon::now()->subMonths($no_of_months))->delete();
                return success('','Session logs deleted successfully');
            }
            else{
                return error(['Enter a valid number between 1 & 12']);
            }
        }
        else{
            return error(['Parameter missing']);
        }
    }

    // confirmation of email logs by customer
    public function verifyemaildelete(Request $request){
        die('1');
        $record = EmailLogs::where('delete_token',$request->token)->first();
        echo "<pre>";print_r($record);die;
    }
    
}
