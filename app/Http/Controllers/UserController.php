<?php

namespace App\Http\Controllers;

use App\Models\DownloadLogs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\User_Sessions;
use App\Models\TwoFALogs;
use Illuminate\Support\Carbon;
use App\Models\Project;
use App\Models\Files;
use App\Models\ImportColumns;
use App\Models\ImportData;
use App\Models\ImportLogs;
use App\Models\Investor;
use App\Models\Logs;
use App\Models\Platform;
use App\Models\ProjectsUsers;
use App\Models\Questions;
use App\Models\Task;
use App\Models\Integrity;
use App\Models\Templates;
use App\Models\EmailLogs;

class UserController extends Controller
{
    //Check user login details
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $find_data = getuserbyUsernameEmail($request->username);
        if (empty($find_data)) {
            return error(Lang::get('validation.custom.wrong_username'));
        }
        if ($find_data->status == 0) {
            return error(Lang::get('validation.custom.account_deleted'));
        }
        if ($find_data->status == 2) {
            return error(Lang::get('validation.custom.account_inactive'));
        }
        if (Hash::check($request->password, $find_data->password)) {
            $current_time = currenthumantime();
            if ($find_data->email_verified_at == '') {
                return error(Lang::get('validation.custom.email_unverified'));
            }
            if ($request->has('twofacode')) {
                //Validate 2FAcode and allow login
                $last_twofa = TwoFALogs::where('user_id', $find_data->id)->orderBy('email_sent_time', 'desc')->first();
                if (!empty($last_twofa)) {
                    $to = Carbon::createFromFormat('Y-m-d H:s:i', $last_twofa->email_sent_time);
                    $from = Carbon::createFromFormat('Y-m-d H:s:i', $current_time);
                    $diff_in_minutes = $to->diffInMinutes($from);
                    // if ($diff_in_minutes > 20) {
                    //     return error(Lang::get('validation.custom.twofa_expired_code'));
                    // } else {
                    if ($request->twofacode == $last_twofa->twofa_code) {
                        TwoFALogs::where('id', $last_twofa->id)->update(['twofa_verified' => 1, 'twofa_verified_time' => $current_time]);
                        $apiToken = Crypt::encrypt($find_data->id);
                        User::where('id', $find_data->id)->update(['authtoken' => $apiToken, 'is_loggedin' => 1]);
                        $find_data->authtoken = $apiToken;
                        // Auth::login($find_data);
                        User_Sessions::where('user_id', $find_data->id)->where('logout_time', NULL)->update(['logout_time' => $current_time, 'ipaddress' => getIP()]);
                        User_Sessions::create(['user_id' => $find_data->id, 'login_time' => $current_time, 'ipaddress' => getIP()]);

                        addlog('Login', 'User', Lang::get('validation.logs.user_login', ['username' => $find_data->username]), $find_data->id);
                        return success($find_data, Lang::get('validation.custom.login_success'));
                    } else {
                        return error(Lang::get('validation.custom.twofa_invalid_code'));
                    }
                    // }
                }
            } else {
                //Send 2FA email
                $facode = random_int(100000, 999999);
                Mail::send('emails.Send2FACode', ['facode' => $facode, 'data' => $find_data], function ($message) use ($find_data) {
                    $message->to($find_data->email);
                    $message->subject('Qflow - Two Factor Verification Code');
                });
                if (Mail::failures()) {
                    $add_data = TwoFALogs::create(['user_id' => $find_data->id, 'twofa_code' => $facode, 'email_sent' => 0]);
                    $delete_oldcodes = TwoFALogs::where('id', '!=', $add_data->id)->where('user_id', $find_data->id)->delete();
                    addlog('Login', 'User', Lang::get('validation.logs.twofa_mail_failed', ['email' => $find_data->email]), $find_data->id);
                    return error(Lang::get('validation.custom.twofamail_failed'));
                } else {
                    addlog('Login', 'User', Lang::get('validation.logs.twofa_mail_success', ['email' => $find_data->email]), $find_data->id);
                    $add_data = TwoFALogs::create(['user_id' => $find_data->id, 'twofa_code' => $facode, 'email_sent' => 1, 'email_sent_time' => $current_time]);
                    $delete_oldcodes = TwoFALogs::where('id', '!=', $add_data->id)->where('user_id', $find_data->id)->delete();
                    return success($find_data, Lang::get('validation.custom.twofamail_sent'));
                }
                //Temp-to remove 2fa mail and show code in message
                // $facode = random_int(100000, 999999);
                // addlog('Login', 'User', Lang::get('validation.logs.twofa_mail_success', ['email' => $find_data->email]), $find_data->id);
                // TwoFALogs::create(['user_id' => $find_data->id, 'twofa_code' => $facode, 'email_sent' => 1, 'email_sent_time' => $current_time]);
                // return success($find_data, Lang::get('validation.custom.twofamail_sent').'-2facode-'.$facode);
            }
        } else {
            return error(Lang::get('validation.custom.wrong_password'));
        }
    }

    //Send password reset email - forgot-password 
    public function forgotpass(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $current_user = getuserbyUsernameEmail($request->username);

        if (empty($current_user)) {
            return error(Lang::get('validation.custom.wrong_username_or_email'));
        }
        if ($current_user->status == 2) {
            return error(Lang::get('validation.custom.account_inactive'));
        }

        if (!empty($current_user)) {
            $current_time = currenthumantime();
            $token = Str::random(64);
            DB::table('password_resets')->insert(
                ['email' => $current_user->email, 'token' => $token, 'created_at' => $current_time]
            );
            Mail::send('emails.ForgotPassword', ['token' => $token, 'data' => $current_user], function ($message) use ($current_user) {
                $message->to($current_user->email);
                $message->subject('Qflow - Reset Password Notification');
            });
            if (Mail::failures()) {
                return error(Lang::get('validation.custom.reset_email_failed'));
            } else {
                addlog('Password Reset', 'User', Lang::get('validation.logs.password_reset_mail', ['email' => $current_user->email]), $current_user->id);
                return success($current_user, Lang::get('validation.custom.reset_email_sent'));
            }
        } else {
            return error(Lang::get('validation.custom.wrong_username_or_email'));
        }
    }

    // Reset password with new password and token
    public function resetpassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required',
            'token' => 'required',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $newconfirmpassword = Hash::make($request->password_confirmation);
        if (!Hash::check($request->password, $newconfirmpassword)) {
            return error(array('password_confirmation' => array(Lang::get('validation.custom.mismatch_password'))));
        }
        $get_token = DB::table('password_resets')
            ->where('token', $request->token)
            ->first();
        if (!empty($get_token)) {
            $current_time = currenthumantime();
            // $to = Carbon::createFromFormat('Y-m-d H:s:i', $get_token->created_at);
            // $from = Carbon::createFromFormat('Y-m-d H:s:i', $current_time);
            // $diff_in_minutes = $to->diffInMinutes($from);
            /**/
            $to_time = strtotime($current_time);
            $from_time = strtotime($get_token->created_at);
            $diff_in_minutes = round(abs($to_time - $from_time) / 60);

            /**/
            if ($diff_in_minutes > 20) {
                return error(Lang::get('validation.custom.expired_reset_token'));
            } else {
                $user = User::where('email', $get_token->email)
                    ->update(['password' => Hash::make($request->password)]);
                $current_user = getuserbyUsernameEmail($get_token->email);
                addlog('Password Reset', 'User', Lang::get('validation.logs.password_reset_success', ['email' => $current_user->email]), $current_user->id);
                return success($user, Lang::get('validation.custom.resetpassword_success'));
            }
        } else {
            return error(Lang::get('validation.custom.invalid_token'));
        }
    }

    // Logout user with token
    public function logout(Request $request)
    {
        $current_user = User::where('authtoken', $request->authtoken)->first();
        if (!empty($current_user)) {
            // Auth::logout();
            $current_time = currenthumantime();
            User::where('id', $current_user->id)->update(['is_loggedin' => 0, 'authtoken' => NULL]);

            User_Sessions::where('user_id', $current_user->id)->where('logout_time', NULL)->update(['logout_time' => $current_time, 'ipaddress' => getIP()]);
            addlog('Logout', 'User', Lang::get('validation.logs.user_logout', ['username' => $current_user->username]), $current_user->id);
            return success([], Lang::get('validation.custom.logout_success'));
        } else {
            return error(Lang::get('validation.custom.invalid_authtoken'));
        }
    }

    // View user details by ID
    public function viewuser(Request $request, $userid)
    {
        $finduser = User::find($userid);
        if (!empty($finduser)) {
            return success($finduser);
        } else {
            return error(Lang::get('validation.custom.invalid_userid'));
        }
    }

    // View current logged-in user details
    public function currentuser(Request $request)
    {
        $check_user = $request->get('current_user');
        return success($check_user);
    }

    // Get all users-Active & Inactive
    public function allusers(Request $request)
    {
        $getdata = User::query();
        if ($request->has('exclude_userid') && $request->exclude_userid != '')
            $getdata = $getdata->where('id', '!=', $request->exclude_userid);
        if ($request->has('status'))
            $getdata = $getdata->where('status', $request->status);
        else
            $getdata = $getdata->where('status', '!=', 0);
        if ($request->has('role') && $request->role != '')
            $getdata = $getdata->where('role', $request->role);
        $all_data = $getdata->get();
        if ($all_data) {
            $all_data->makeHidden(['authtoken']);
            return success($all_data, '');
        } else {
            return error();
        }
    }

    // Add new user
    public function adduser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'username' => 'required|max:255',
            'email' => 'required|email|max:255',
            'role' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $is_exist = User::where('username', $request->username)->orWhere('email', $request->email)->first();
        if (empty($is_exist)) {
            $check_user = $request->get('current_user');
            $user_details = array(
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'username' => $request->username,
                'email' => $request->email,
                'role' => $request->role,
                'status' => 2,
                'authtoken' => NULL,
                'created_by' => $check_user->id,
            );
            $adduser = User::create($user_details);
            if ($adduser) {
                $added_user = getuserbyUsernameEmail($request->email);
                $token = Crypt::encrypt($added_user->email);
                Mail::send('emails.VerifyEmail', ['token' => $token, 'data' => $added_user], function ($message) use ($added_user) {
                    $message->to($added_user->email);
                    $message->subject('Qflow - Verify your email');
                });
                if (Mail::failures()) {
                    addlog('Add', 'User', Lang::get('validation.logs.verifyemail_failed', ['email' => $added_user->email]), $check_user->id);
                    return error(Lang::get('validation.custom.verify_email_failed'));
                } else {
                    addlog('Add', 'User', Lang::get('validation.logs.useradd_success', ['email' => $request->email, 'username' => $check_user->username]), $check_user->id);
                    return success([], Lang::get('validation.custom.user_add_success'));
                }
            } else {
                addlog('Add', 'User', Lang::get('validation.logs.user_add_failed', ['email' => $request->email, 'username' => $check_user->username]), $check_user->id);
                return error(Lang::get('validation.custom.useradd_failed'));
            }
        } else {
            if ($is_exist->status == 0) {
                return error(Lang::get('validation.custom.user_already_deleted'));
            } else {
                if ($is_exist->username == $request->username)
                    return error(Lang::get('validation.custom.user_already_exist_username'));
                else
                    return error(Lang::get('validation.custom.user_already_exist_email'));
            }
        }
    }

    // Verify email and activate account
    public function verifyemail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $usermail = Crypt::decrypt($request->token);
        if (filter_var($usermail, FILTER_VALIDATE_EMAIL)) {
            $current_time = currenthumantime();
            $verfiy_user = User::where('email', $usermail)->where('status', 2)->update(['email_verified_at' => $current_time, 'status' => 1]);
            if ($verfiy_user) {
                $cur_user = User::where('email', $usermail)->get();
                return success($cur_user, Lang::get('validation.custom.verify_account_success'));
            } else {
                return error(Lang::get('validation.custom.verify_account_failed'));
            }
        } else {
            return error(Lang::get('validation.custom.invalid_email'));
        }
    }


    // Set email after email verified
    public function setuserpassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userid' => 'required|numeric',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $newconfirmpassword = Hash::make($request->password_confirmation);
        if (!Hash::check($request->password, $newconfirmpassword)) {
            return error(array('password_confirmation' => [Lang::get('validation.custom.mismatch_password')]));
        }
        $newpassword = Hash::make($request->password);
        $cur_user = User::find($request->userid);
        if (!empty($cur_user)) {
            $newpassword = Hash::make($request->password);
            $update_data = User::where('id', $request->userid)->update(['status' => 1, 'password' => $newpassword]);
            if ($update_data) {
                return success([], Lang::get('validation.custom.passwordset_success'));
            } else {
                return error(Lang::get('validation.custom.passwordset_failed'));
            }
        } else {
            return error(Lang::get('validation.custom.invalid_userid'));
        }
    }

    // update user details
    public function updateuser(Request $request, $userid)
    {
        $validate_fields = array(
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        );
        $check_user = $request->get('current_user');
        if ($check_user->role == 1) {
            $validate_fields['role'] = 'required|integer';
            $validate_fields['status'] = 'required|integer';
        }
        if ($request->has('password') && trim($request->password) != '') {
            $validate_fields['password'] = 'sometimes|required|string|min:8';
        }
        $validator = Validator::make($request->all(), $validate_fields);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        if (($check_user->role != 1) && ($check_user->id != $userid)) {
            return error(Lang::get('validation.custom.unauthorized_access'));
        }
        $is_exist = User::where('id', '!=', $userid)->where(static function ($query) use ($request) {
            $query->where('username', $request->username)
                ->orWhere('email', $request->email);
        })->first();
        if (empty($is_exist)) {
            $update_array = array(
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'email' => $request->email,
            );
            if ($request->has('password'))
                $update_array['password'] = Hash::make($request->password);
            if ($check_user->role == 1) {
                $update_array['role'] = $request->role;
                $update_array['status'] = $request->status;
            }
            $update_user = User::where('id', $userid)->update($update_array);
            if ($update_user) {
                addlog('Update', 'User', Lang::get('validation.logs.userupdate_success', ['email' => $request->email, 'username' => $check_user->username]), $check_user->id);
                $find_data = User::find($userid);
                return success($find_data, Lang::get('validation.custom.user_update_success'));
            } else {
                addlog('Update', 'User', Lang::get('validation.logs.userupdate_failed', ['email' => $request->email, 'username' => $check_user->username]), $check_user->id);
                return error(Lang::get('validation.custom.user_update_failed'));
            }
        } else {
            if ($is_exist->status == 0) {
                return error(Lang::get('validation.custom.user_already_deleted'));
            } else {
                if ($is_exist->username == $request->username && $is_exist->id != $userid)
                    return error(Lang::get('validation.custom.user_already_exist_username'));
                elseif ($is_exist->email == $request->email && $is_exist->id != $userid)
                    return error(Lang::get('validation.custom.user_already_exist_email'));
            }
        }
    }

    // Delete user- Change user status to 0 , If need to permenant delete,pass permanentDelete = 1
    public function deleteuser(Request $request, $userid)
    {
        $check_user = $request->get('current_user');
        $delete_user = User::find($userid);
        $find_falogs = TwoFALogs::where('user_id', $userid)->delete();
        if (!empty($delete_user)) {
            if ($request->has('permanentDelete') && $request->permanentDelete == 1) {
                // permenant delete
                $name = $delete_user->username;
                if ($delete_user->delete()) {
                    $fileids = $projectids = $investorids = $platformids = $templateids = array();
                    $find_files = Files::where('created_by', $userid)->get();
                    if ($find_files->count()) {
                        $fileids = $find_files->pluck('id')->toArray();
                    }

                    #08-11-2022
                    #task ids
                    $userWorkedOnProjectIds = Task::select('project_id')
                    ->where(function ($q) use ($userid) {
                        $q->where('record_owner', $userid)
                        ->orWhere('last_modified_by', $userid);
                    })
                    ->get()->unique('project_id')->pluck('project_id')->toArray();

                    $taskIds = Task::select('row_id')->where('record_owner', $userid)->get()->pluck('row_id')->toArray();
                    $taskIds1 = Task::select('row_id')->where('last_modified_by', $userid)->get()->pluck('row_id')->toArray();
                    $finalTaskIds = array_unique(array_merge($taskIds,$taskIds1), SORT_REGULAR);
                    $statusTaskChange = ImportData::whereIn('id', $finalTaskIds)->update(['task_status' => '0']);

                    $find_tasks = Task::where('record_owner', $userid)->delete();
                    // $find_tasks1 = Task::where('last_review_doneby', $userid)->delete();
                    $find_tasks1 = Task::where('last_modified_by', $userid)->delete();
                    // $find_falogs = TwoFALogs::where('user_id', $userid)->delete();

                    if($userWorkedOnProjectIds){
                        foreach($userWorkedOnProjectIds as $eachProjectId){
                            $projectDetails = Project::where('id', $eachProjectId)->get()->first();
                            $count_task = Task::where('project_id', $projectDetails->id)->where('status', 2)->count();
                            if ($count_task > 0) {
                                $find_file = Files::where('project_id', $projectDetails->id)->where('type', 'Import')->first();
                                $total_imported = $find_file->imported_rows;
                                if ($total_imported > 0) {
                                    $project_percent = number_format(($count_task / ($total_imported - 1)) * 100, 2);
                                    $projectDetails->percentage_completed = $project_percent;
                                    $projectDetails->save();
                                }
                            }
                            else{
                                $projectDetails->percentage_completed = '0';
                                $projectDetails->save();
                            }
                        }
                    }

                        

                    #integrity ids
                    $userWorkedOnProjectIds1 = Integrity::select('project_id')
                    ->where(function($q) use ($userid){
                        $q->where('record_owner', $userid)
                        ->orWhere('last_modified_by', $userid);
                    })
                    ->get()->unique('project_id')->pluck('project_id')->toArray();

                    $integrityIds = Integrity::select('row_id')->where('record_owner', $userid)->get()->pluck('row_id')->toArray();
                    $integrityIds1 = Integrity::select('row_id')->where('last_modified_by', $userid)->get()->pluck('row_id')->toArray();
                    $finalIds = array_unique(array_merge($integrityIds,$integrityIds1), SORT_REGULAR);
                    $statusChange = ImportData::whereIn('id', $finalIds)->update(['integrity_status' => '0']);

                    $find_integrity = Integrity::where('record_owner', $userid)->delete();
                    $find_integrity1 = Integrity::where('last_modified_by', $userid)->delete();

                    foreach($userWorkedOnProjectIds1 as $eachProjectId){
                        $projectDetails = Project::where('id', $eachProjectId)->get()->first();
                        $completedPercent = Integrity::where('status','2')->where('project_id',$projectDetails->id)->get();
                        $totalRows = Files::select('imported_rows')->where('project_id',$projectDetails->id)->get();
                        if (count($completedPercent) > 0) {
                            $countCompletedPercent = count($completedPercent);
                            $totalRowsCount = ($totalRows[0]->imported_rows == 1)? 1 : ($totalRows[0]->imported_rows - 1);
                            // $percentCompleted = ($countCompletedPercent * 100)/$totalRows[0]->imported_rows;
                            $percentCompleted = ($countCompletedPercent * 100)/$totalRowsCount;
                            $projectDetails->integrity_precentage_completed = $percentCompleted;
                            $projectDetails->save();
                        }
                        else{
                            $projectDetails->integrity_precentage_completed = '0';
                            $projectDetails->save();
                        }
                    }

                    

                    
                    #08-11-2022


                    $find_project = Project::where('created_by', $userid)->get();
                    if ($find_project->count()) {
                        $projectids = $find_project->pluck('id')->toArray();

                        $find_files = Files::whereIn('project_id', $projectids)->get();
                        if ($find_files->count()) {
                            $temp_fileids = $find_files->pluck('id')->toArray();
                            $fileids = array_merge($fileids, $temp_fileids);
                        }
                        $find_project1 = Project::whereIn('id', $projectids)->delete();
                        $find_cols = ImportColumns::whereIn('project_id', $projectids)->delete();
                        $find_data = ImportData::whereIn('project_id', $projectids)->delete();
                    }
                    if (!empty($fileids)) {
                        $find_files1 = Files::whereIn('id', $fileids)->delete();
                        $find_cols = ImportColumns::whereIn('file_id', $fileids)->delete();
                        $find_data = ImportData::whereIn('file_id', $fileids)->delete();
                    }

                    $find_inv = Investor::where('created_by', $userid)->get();
                    if ($find_inv->count()) {
                        $investorids = $find_inv->pluck('id')->toArray();
                        $find_inv = Investor::whereIn('id', $investorids)->delete();
                        $update_prpject = Project::whereIn('investor_id', $investorids)->update(['investor_id' => NULL]);
                    }
                    $find_plt = Platform::where('created_by', $userid)->get();
                    if ($find_plt->count()) {
                        $platformids = $find_plt->pluck('id')->toArray();
                        $find_plt = Platform::whereIn('id', $platformids)->delete();
                        $update_prpject = Project::whereIn('platform_id', $platformids)->update(['platform_id' => NULL]);
                    }
                    $find_template = Templates::where('created_by', $userid)->get();
                    if ($find_template->count()) {
                        $templateids = $find_template->pluck('id')->toArray();
                        $find_questions1 = Questions::whereIn('template_id', $templateids)->delete();
                        $find_template1 = Templates::whereIn('id', $templateids)->delete();
                        $update_prpject = Project::whereIn('template_id', $templateids)->update(['template_id' => NULL]);
                    }
                    $find_logs = Logs::where('created_by', $userid)->delete();
                    $find_projectusers = ProjectsUsers::where('user_id', $userid)->delete();
                    $find_questions = Questions::where('created_by', $userid)->delete();




                    

                    $find_session = User_Sessions::where('user_id', $userid)->delete();

                    addlog('Permanent Delete', 'User', Lang::get('validation.logs.permanentdelete_success', ['module' => 'User', 'name' => $name, 'username' => $check_user->username]), $check_user->id);
                    return success([], Lang::get('validation.custom.permanent_delete_success', ['module' => 'User', 'name' => $name]));
                } else {
                    addlog('Permanent Delete', 'User', Lang::get('validation.logs.permanentdelete_failed', ['module' => 'user', 'name' => $name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.permanent_delete_failed', ['module' => 'user', 'name' => $name]));
                }
            } else {
                $update_data = User::where('id', $userid)->update(['status' => 0, 'authtoken' => NULL, 'is_loggedin' => 0]);
                if ($update_data) {
                    addlog('Delete', 'User', Lang::get('validation.logs.userdelete_success', ['email' => $delete_user->email, 'username' => $check_user->username]), $check_user->id);
                    return success([], Lang::get('validation.custom.user_delete_success'));
                } else {
                    addlog('Delete', 'User', Lang::get('validation.logs.userdelete_failed', ['email' => $delete_user->email]), $check_user->id);
                    return error(Lang::get('validation.custom.user_delete_success'));
                }
            }
        } else {
            return error(Lang::get('validation.custom.invalid_userid'));
        }
    }

    //Resend email verification link
    public function resendverifyemail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userid' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $find_data = User::find($request->userid);
        if (!empty($find_data)) {
            if ($find_data->email_verified_at != '') {
                return error(Lang::get('validation.custom.email_already_verified'));
            } else {
                $token = Crypt::encrypt($find_data->email);
                $check_user = $request->get('current_user');
                Mail::send('emails.VerifyEmail', ['token' => $token, 'data' => $find_data], function ($message) use ($find_data) {
                    $message->to($find_data->email);
                    $message->subject('Qflow - Verify your email');
                });
                if (Mail::failures()) {
                    addlog('Add', 'User', Lang::get('validation.logs.verifyemail_failed', ['email' => $find_data->email]), $check_user->id);
                    return error(Lang::get('validation.custom.verify_email_failed'));
                } else {
                    $find_data->updated_at = currenthumantime();
                    $find_data->save();
                    addlog('Add', 'User', Lang::get('validation.logs.verifyemail_success', ['email' => $find_data->email]), $check_user->id);
                    return success($find_data, Lang::get('validation.custom.verify_email_success'));
                }
            }
            // sleep(3);
            return success($find_data, Lang::get('validation.custom.verify_email_success'));
        } else {
            return error(Lang::get('validation.custom.invalid_userid'));
        }
    }

    //Restore User Account
    public function restoreuser(Request $request, $userid)
    {
        $find_data = User::find($userid);
        if (!empty($find_data)) {
            if ($find_data->status != 0) {
                return error(Lang::get('validation.custom.data_already_active', ['module' => 'User']));
            } else {
                $check_user = $request->get('current_user');
                $find_data->status = 1;
                if ($find_data->save()) {
                    addlog('Restore', 'User', Lang::get('validation.logs.userrestore_success', ['email' => $find_data->email, 'username' => $check_user->username]), $check_user->id);
                    return success($find_data, Lang::get('validation.custom.data_restore_success', ['module' => 'User']));
                } else {
                    addlog('Restore', 'User', Lang::get('validation.logs.userrestore_failed', ['email' => $find_data->email, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.data_restore_failed', ['module' => 'user']));
                }
            }
        } else {
            return error(Lang::get('validation.custom.invalid_userid'));
        }
    }

    public function verifyemaildelete(Request $request){
        $validator = Validator::make($request->all(), [
            'token' => 'required',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }

        $usermail = Crypt::decrypt($request->token);
        if (filter_var($usermail, FILTER_VALIDATE_EMAIL)) {
            $current_time = currenthumantime();

            $verfiy_confirmation_deletion = EmailLogs::where('sent_to', $usermail)->update(['delete_date' => $current_time, 'is_deleted' => 1]);
            if ($verfiy_confirmation_deletion) {
                $user_id = User::select('id')->where('email',$usermail)->first();
                DownloadLogs::where('downloaded_by', $user_id['id'])->update(['delete_status' => '1']);
                return success([], Lang::get('validation.logs.filedonwloaddelete_success'));
            } else {
                return error(Lang::get('validation.logs.filedonwloaddelete_failed'));
            }
        } else {
            return error(Lang::get('validation.custom.invalid_email'));
        }

    }

    //Testing Function route
    public function testing(Request $request)
    {
        $test = currenthumantime();
        return $test;
        $now = Carbon::now();
        echo $now->timezoneName;
        //or 
        // Carbon::
        // return $test;

    }

    public function temptesting(){

        $sixMonthsAgo = Carbon::now()->subMonths(6);
        $projects = Project::where('created_at', '<=', $sixMonthsAgo)->where('is_archived',1)->where('status','!=',0)->update(['is_archived' => 0]);
        
        return $projects;
    }

}
