<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Lang;
use App\Models\Logs;
use App\Models\User;

// Default success response for API
if (!function_exists('success')) {
    function success($data, $success_msg = "", $import_status = "")
    {
        $returndata = array(
            'code'      => 1,
            'message'   => $success_msg ? $success_msg : 'success',
            'data'      => $data
        );
        if ($import_status != '')
            $returndata['import_status'] = $import_status;
        return response()->json($returndata);
    }
}

// Default failed response for API
if (!function_exists('error')) {
    function error($error = "", $import_status = "",$extra_data = array())
    {
        $returndata = array(
            'code'  => 0,
            'message' => $error ? $error : 'failed'
        );
        if ($import_status != '')
            $returndata['import_status'] = $import_status;
        if(!empty($extra_data))
            $returndata['extra_details'] = $extra_data;

        return response()->json($returndata);
    }
}

// Get current time in y-m-d h:i:s format - for db entry and show
if (!function_exists('currenthumantime')) {
    function currenthumantime()
    {
        return Carbon::now()->toDateTimeString();
    }
}

//Get current IP address
if (!function_exists('getIP')) {
    function getIP()
    {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if (getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if (getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if (getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if (getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        else if (getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = request()->ip();
        return $ipaddress;
    }
}


//Get Current User
if (!function_exists('getuserbyUsernameEmail')) {
    function getuserbyUsernameEmail($username_email)
    {
        return User::where('username', $username_email)
            ->orWhere('email', $username_email)->first();
    }
}


//add Logs
if (!function_exists('addlog')) {
    function addlog($log_type = 'System', $module = 'System', $message = '', $created_by = 0)
    {
        Logs::create([
            'log_type' => $log_type,
            'module' => $module,
            'message' => $message,
            'created_by' => $created_by
        ]);
    }
}

// get current user if loggedin
if (!function_exists('getloggedinuser')) {
    function getloggedinuser($authtoken)
    {
        $userid = Crypt::decrypt($authtoken);
        $current_user = User::find($userid)->where('is_loggedin', 1)->first();
        if ((!empty($current_user))) {
            if ($current_user->status == 0) {
                return Lang::get('validation.custom.account_deleted');
            } else if ($current_user->status == 2) {
                return Lang::get('validation.custom.account_inactive');
            }
            return $current_user;
        } else {
            return Lang::get('validation.custom.already_loggedout');
        }
    }
}

// removes users who are given none permission
if (!function_exists('removeNonePersimissionUsers')) {
    function removeNonePersimissionUsers($userList)
    {
        $filteredArr = array();
        foreach($userList as $eachUser){
            if($eachUser['permission'] != '1')
                array_push($filteredArr, $eachUser);
        }
        return $filteredArr;
    }
}

// gets the users with 1 permission
if (!function_exists('getNonePersimissionUsers')) {
    function getNonePersimissionUsers($userList)
    {
        $filteredArr = array();
        foreach($userList as $eachUser){
            if($eachUser['permission'] == '1')
                array_push($filteredArr, $eachUser['user_id']);
        }
        return $filteredArr;
    }
}