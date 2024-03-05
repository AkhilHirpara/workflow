<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Lang;
use App\Models\Project;
use App\Models\ProjectsUsers;
use App\Models\Files;
use App\Models\FilesShared;
use App\Models\User;
use App\Models\DownloadLogs;
use App\Models\ShortcutManagement;
use File;
use DB;
use Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use App\Models\EmailLogs;
use Illuminate\Support\Facades\Mail;


class DashboardController extends Controller
{
    
    public function dashboarddetails(Request $request){
        $check_user = $request->get('current_user');
        $getdata = Project::query()
        ->where(function ($query) {
            $query->where(function ($subquery) {
                $subquery->where('only_review', 1)
                    ->where('percentage_completed', '<', 100);
            })
            ->orWhere(function ($subquery) {
                $subquery->where('only_integrity', 1)
                    ->where('integrity_precentage_completed', '<', 100);
            })
            ->orWhere(function ($subquery) {
                $subquery->where('only_review', 0)
                    ->where('only_integrity', 0)
                    ->where(function ($innerSubquery) {
                        $innerSubquery->where('percentage_completed', '<', 100)
                            ->orWhere('integrity_precentage_completed', '<', 100);
                    });
            });
        })
        ->where('status','!=','2')
        ->orderBy('id', 'desc');
        if ($check_user->role == 2) {
            //For standard users
            $find_projectusers = ProjectsUsers::where('user_id', $check_user->id)->get();
            if ($find_projectusers->count()) {
                $project_ids = $find_projectusers->pluck('project_id')->toArray();
                foreach ($project_ids as $key => $pi) {
                    $find_file = Files::where('project_id', $pi)->where('type', 'Import')->first();
                    if (empty($find_file) || $find_file->import_status != 1) {
                        unset($project_ids[$key]);
                    }
                }
                // $getdata = $getdata->where('status', 1);
                $getdata = $getdata->whereIn('status', array(1, 2));
                $getdata = $getdata->whereIn('id', $project_ids);
            }
            else{
                $getdata = $getdata->whereIn('status', array(1, 2));
                $getdata = $getdata->whereIn('id', array());
            }
        } else {
            // For admin users
            if ($request->has('status'))
                $getdata = $getdata->where('status', $request->status);
            else
                $getdata = $getdata->where('status', '!=', 0);
            if ($request->has('userid') && $request->userid != '')
                $getdata = $getdata->where('created_by', $request->userid);
        }

        $all_data = $getdata->get();
        if (!empty($all_data)) {
            $total_projects = $projects_completed = $projects_in_progress = $projects_incomplete_creation = $uploaded_documents = 0;
            $uploaded_documents = Files::where('created_by',$check_user->id)->where('type','!=', 'Import')->get()->count();
            // echo $uploaded_documents;die;
            $projects = array();
            foreach ($all_data as $key => $sdata) {
                $total_projects++;
                $sdata->import_status = 0;
                $sdata->imported_rows = 0;
                $find_data = Files::where('project_id', $sdata->id)->where('type', 'Import')->first();
                if (!empty($find_data)) {
                    $sdata->import_status = $find_data->import_status;
                    if ($find_data->imported_rows > 1)
                        $sdata->imported_rows = $find_data->imported_rows - 1;
                }
                $find_data_user = User::find($sdata->created_by);
                $sdata->created_user = '';
                if (!empty($find_data_user))
                    $sdata->created_user = $find_data_user->firstname . ' ' . $find_data_user->lastname;
                
                // if ($find_data->type == 'Personal')
                    // $uploaded_documents++;

                if($sdata->status == '1')
                    $projects_in_progress++;
                else if($sdata->status == '2')
                    $projects_completed++;
                else if($sdata->status == '3')
                    $projects_incomplete_creation++;
                
                if($key < '10'){
                    array_push($projects,$sdata);
                }
                
            }
            $download_files = DownloadLogs::select('file_id')
            ->where('delete_status',0)
            ->where('downloaded_by', $check_user->id)
            ->get();

            $fileIds = FilesShared::where('is_own', '<>', '1')->groupBy('file_id')->get()->pluck('file_id');
            $filesShared = Files::where('created_by',$check_user->id)->whereIn('id',$fileIds)->count();
            // echo "<pre>";print_r($filesShared);return;

            $result['projects_list'] = $projects;
            $summary=array(
                // 'files_shared'=> FilesShared::where('user_id',$check_user->id)->groupBy('file_id')->get()->count(),
                'files_shared'=> $filesShared,
                'download_documents' => $download_files->count(),
                'uploaded_documents' => $uploaded_documents,
                'total_projects' => $total_projects,
                'projects_in_progress' => $projects_in_progress,
                'projects_completed' => $projects_completed,
                'projects_incomplete_creation' => $projects_incomplete_creation

            );
            $result['project_summary'] = $summary;
        }
        return ($result) ? success($result) : error();
    }

    public function generateshortcut(Request $request){

        $validator = Validator::make($request->all(), [
            'url' => 'required',
            'shortcut_name' => 'required',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }

        $check_user = $request->get('current_user');
        $url = $request->url;
        $name = $request->shortcut_name;
        $shortcut_exists = ShortcutManagement::where('shortcut_name',$name)->where('created_by',$check_user->id)->first();
        if($shortcut_exists){
            ShortcutManagement::where('shortcut_name',$name)->where('created_by',$check_user->id)->delete();
        }
        $shortcut = ShortcutManagement::create(['shortcut_name' => $name, 'shortcut_url' => $url, 'authentication_token' => $check_user->authtoken, 'created_by' => $check_user->id]);
        // $shortcut_url = env('LARAVEL_SHORTCUT_URL').'?url='.$url.'&name='.$name.'&tk='.$check_user->authtoken;
        $shortcut_url = env('LARAVEL_SHORTCUT_URL').'?id='.Crypt::encrypt($shortcut->id).'&name='.$name;
        return ($shortcut_url) ? success($shortcut_url) : error();

    }

    public function redirecturl(Request $request){
        
        $id = Crypt::decrypt($request->shortcutid);
        // echo $id;die;
        $shortcutDetails = ShortcutManagement::find($id);
        if($shortcutDetails){
            if(strtotime($shortcutDetails->created_at) < strtotime('-30 days')){
                return error(Lang::get('validation.custom.expired_link'));
            }
            User::where('id',$shortcutDetails['created_by'])->update(['is_loggedin' => 1]);
            $userDetails = User::where('id',$shortcutDetails['created_by'])->first();
            Auth::login($userDetails);
            $userDetails->makeHidden(['password', 'remember_token', 'updated_at']);
            $shortcutDetails->user_details = $userDetails;
            return ($shortcutDetails) ? success($shortcutDetails) : error();
        }

        

        // return redirect()->away($shortcutDetails->shortcut_url)
        // ->header('authtoken', $shortcutDetails['authentication_token']);
        // return redirect($shortcutDetails->shortcut_url, 302, [
        //     'authtoken' => $shortcutDetails['authentication_token']
        // ]);

    }

    public function maildeletedownloadedfiles(Request $request){
        $logs = DownloadLogs::select('file_id','downloaded_by','download_date')
        ->with('FileDetails')
        ->where('delete_status',0)
        ->orderBy('downloaded_by')
        ->groupBy('downloaded_by')
        ->groupBy('file_id')
        ->get();

        $user_file_list = array();
        if ($logs->count()) {
            foreach ($logs as $each_log){
                if($each_log->FileDetails)
                    $user_file_list[$each_log->downloaded_by]['file_list'][] = $each_log->FileDetails->filename;
            }
        }
        if(count($user_file_list)){
            // echo "<pre>";print_r($logs);die;
            foreach($user_file_list as $each_file_index => $each_file){
                $user_details = User::select('firstname','lastname','email')->where('id',$each_file_index)->first();
                $is_limit_reached = EmailLogs::select('email_reminder_num')->where('user_id',$each_file_index)->first();
                // echo "<pre>";print_r($is_limit_reached->count());die;
                if(($is_limit_reached === null) || ($is_limit_reached['email_reminder_num'] < 3))
                    $this->sendMail($user_details->email,$each_file,$user_details->firstname.' '.$user_details->lastname,$each_file_index);
            }
        }

        $warning_user_list = EmailLogs::select('user_id')->where('is_deleted','0')->where('email_reminder_num', '3')->get();
        $user_warning_list = array();
        if($warning_user_list->count()){
            foreach($warning_user_list as $each_user){
                $user = User::select('firstname','lastname')->where('id',$each_user->user_id)->first();
                array_push($user_warning_list,$user->firstname.' '.$user->lastname);
            }
            $data=array(
                'usernames' => $user_warning_list
            );
            Mail::send('emails.DeleteDownloadedFileAdmin', ["maildata"=>$data], function($message) {
                $message->from(env('MAIL_FROM_ADDRESS'), 'Qflow');
                $message->to(env('ADMIN_EMAIL'))->subject('List of users who didn\'t deleted the downloaded files after warnings');
            });
        }
        return success(array(), Lang::get('validation.custom.delete_files_mail_sent'));

    }

    public function sendMail($to_email,$file_list,$username,$user_id){

        $delete_token = Crypt::encrypt($to_email);
        // $usermail = Crypt::decrypt($delete_token);
        $data=array(
            'fullname' => $username,
            'file_list' => $file_list['file_list'],
            'token' => $delete_token
        );
        $user_log = EmailLogs::where('user_id', $user_id)->first();
        if ($user_log === null) {
            EmailLogs::create(['subject' => 'Delete Downloaded Files', 'sent_on' => date("Y-m-d H:i:s",time()), 'sent_to' => $to_email, 'user_id' => $user_id, 'delete_token'=> $delete_token, 'is_deleted' => '0', 'email_reminder_num' => '1']);
        }
        else{
            $data['token'] = $user_log['delete_token'];
            if($user_log['email_reminder_num'] < 3){
                $user_log['email_reminder_num'] = $user_log['email_reminder_num']+1;
                EmailLogs::where('user_id',$user_id)->update(['email_reminder_num'=>$user_log['email_reminder_num']]);
            }
        }

        Mail::send('emails.deletedownloadedfile', ["maildata"=>$data], function($message) use ($to_email) {
            $message->from(env('MAIL_FROM_ADDRESS'), 'Qflow');
            $message->to($to_email)->subject('Delete Downloaded Files');
        });
        
    }
    
}
