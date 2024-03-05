<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DownloadLogs;
use App\Models\DeleteDownloadFiles;
use App\Models\Files;
use App\Models\User;
use App\Models\EmailLogs;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;

class Deletedownloadedfile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daily:deletedownloadedfile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Email reminder scheduler - mail to users who have downloaded the files';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $logs = DownloadLogs::select('file_id','downloaded_by','download_date')
        // ->with('UserDetails')
        ->with('FileDetails')
        ->where('delete_status',0)
        ->orderBy('downloaded_by')
        ->groupBy('downloaded_by')
        ->groupBy('file_id')
        ->get();
        
        $user_file_list = array();
        if ($logs->count()) {
            foreach ($logs as $each_log){
                $user_file_list[$each_log->downloaded_by]['file_list'][] = $each_log->FileDetails->filename;
            }
        }
        if(count($user_file_list)){
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
