<?php

namespace App\Http\Controllers;

use App\Models\DownloadLogs;
use App\Models\Files;
use App\Models\FilesShared;
use App\Models\Folders;
use App\Models\FoldersShared;
use App\Models\User;
use App\Models\DeleteDownloadFiles;
use App\Models\ImportColumns;
use App\Models\ImportData;
use App\Models\ImportLogs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\File;

class FilesController extends Controller
{
    // Get all files
    public function allfiles(Request $request)
    {
        $getdata = Files::query();
        $getfolderdata = Folders::query();
        $check_user = $request->get('current_user');
        if ($request->has('status')) {
            $getdata = $getdata->where('status', $request->status);
        } else {
            $getdata = $getdata->where('status', '!=', 0);
        }

        if ($request->has('exclude_fileid') && $request->exclude_fileid != '') {
            $getdata = $getdata->where('id', '!=', $request->exclude_fileid);
        }

        if ($request->has('parent_only') && $request->parent_only == 1) {
            $getdata = $getdata->where('parent_fileid', 0);
            // $getdata = $getdata->where('id','!=', );
            if ($check_user->role == 2 || $check_user->role == 3) {
                $shared_withme = FilesShared::where('user_id', $check_user->id)->get();
                if ($shared_withme->count()) {
                    $shared_fileids = $shared_withme->pluck('file_id')->toArray();
                    $getdata = $getdata->orWhereIn('id', $shared_fileids);
                }
                $getdata = $getdata->orWhere('created_by', $check_user->id);
            }
        } else {
            if ($request->has('shared_only') && $request->shared_only == 1) {
                $shared_withme = FilesShared::where('user_id', $check_user->id)->get();
                $shared_folders_withme = FoldersShared::where('user_id', $check_user->id)->get();
                if ($shared_withme->count()) {
                    $shared_fileids = $shared_withme->pluck('file_id')->toArray();
                    $getdata = $getdata->whereIn('id', $shared_fileids);
                } else {
                    return success(array());
                }
                if($shared_folders_withme->count()){
                    $shared_folderids = $shared_withme->pluck('folder_id')->toArray();
                    $getfolderdata = $getfolderdata->whereIn('id', $shared_folderids);
                }

            } else {
                if ($check_user->role == 2 || $check_user->role == 3) {
                    $getdata = $getdata->where('created_by', $check_user->id);
                } else {
                    if ($request->has('mine_only') && $request->mine_only == 1) {
                        $getdata = $getdata->where('created_by', $check_user->id);
                    }

                }
            }
        }

        $folder_data = $getfolderdata->with('UserDetails')->get();
        $all_data = $getdata->with('ProjectDetails')->with('UserDetails')->with('SharedDetails')->get();
        foreach ($all_data as $sd) {
            $sd->project_name = '';
            $sd->owner_name = '';
            $sd->parent_filename = '';
            $sd->shared_with = [];
            if ($sd->parent_fileid > 0) {
                $parent_file = Files::find($sd->parent_fileid);
                if (!empty($parent_file)) {
                    $sd->parent_filename = $parent_file->original_filename;
                }

            }
            if (!empty($sd->ProjectDetails)) {
                $sd->project_name = $sd->ProjectDetails->project_name;
            }
            if (!empty($sd->UserDetails)) {
                $sd->owner_name = $sd->UserDetails->firstname . ' ' . $sd->UserDetails->lastname;
            }
            if (!empty($sd->SharedDetails)) {
                $shared_userids = $sd->SharedDetails->pluck('user_id')->toArray();
                $sd->shared_with = User::whereIn('id', $shared_userids)->get();
                $sd->shared_with->makeHidden(['email_verified_at', 'role', 'status', 'is_loggedin', 'authtoken', 'created_by']);
            }
            unset($sd->ProjectDetails);
            unset($sd->UserDetails);
            unset($sd->SharedDetails);
        }
        return ($all_data) ? success($all_data) : error();
    }

    // Download File
    public function downloadfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fileid' => 'required|numeric|exists:App\Models\Files,id',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $find_file = Files::find($request->fileid);
        $check_user = $request->get('current_user');
        $current_time = currenthumantime();
        if ($find_file->type == 'Project Import') {
            $dir_filepath = env('COMPLETE_PROJECT_IMPORT_FILESPATH') . $find_file->filename;
            $full_fileurl = env('PUBLIC_PATH_URL') . env('IMPORT_FILESPATH') . $find_file->filename;
        } else if ($find_file->type == 'Project Export') {
            $dir_filepath = env('COMPLETE_PROJECT_EXPORT_FILESPATH') . $find_file->filename;
            $full_fileurl = env('PUBLIC_PATH_URL') . env('EXPORT_FILESPATH') . $find_file->filename;
        } else {
            if($find_file->folder_id != '0' && $find_file->is_nested_upload == '1'){
                $folderPath = Folders::where('id', $find_file->folder_id)->first();
                if($folderPath->folderpath != ''){
                    $dir_filepath = env('COMPLETE_PROJECT_PERSONAL_FILESPATH') . $find_file->created_by . '/' .$folderPath->folderpath .'/' .$find_file->filename;
                    $full_fileurl = env('PUBLIC_PATH_URL') . env('PERSONAL_FILESPATH') . $find_file->created_by . '/' .$folderPath->folderpath .'/' . $find_file->filename;    
                }
                else{
                    $dir_filepath = env('COMPLETE_PROJECT_PERSONAL_FILESPATH') . $find_file->created_by . '/general/' .$find_file->filename;
                    $full_fileurl = env('PUBLIC_PATH_URL') . env('PERSONAL_FILESPATH') . $find_file->created_by . '/general/' . $find_file->filename;
                }
            }
            else{
                $dir_filepath = env('COMPLETE_PROJECT_PERSONAL_FILESPATH') . $find_file->created_by . '/general/' . $find_file->filename;
                $full_fileurl = env('PUBLIC_PATH_URL') . env('PERSONAL_FILESPATH') . $find_file->created_by . '/general/' . $find_file->filename;    
            }
        }
        if (!file_exists($dir_filepath)) {
            return error(Lang::get('validation.custom.file_not_available'));
        }
        if ($check_user->role != 1) {
            if ($find_file->created_by != $check_user->id) {
                $is_shared = FilesShared::where('file_id', $find_file->id)->where('user_id', $check_user->id)->first();
                if (empty($is_shared)) {
                    return error(Lang::get('validation.custom.file_no_access'));
                }
            }
        }
        $download_log = DownloadLogs::create(['file_id' => $find_file->id, 'download_date' => $current_time, 'downloaded_by' => $check_user->id, 'delete_status' => 0]);
        if ($download_log) {
            $download_log->download_url = $full_fileurl;
            $extension = explode('.',$find_file->filename);
            $download_log->extension = end($extension);
            addlog('Download', 'File', Lang::get('validation.logs.filedownload_success', ['filename' => $find_file->original_filename, 'username' => $check_user->username]), $check_user->id);
            return success($download_log);
        } else {
            return error(Lang::get('validation.custom.file_failed_download'));
        }
    }

    // View file details by ID
    public function viewfile(Request $request, $fileid)
    {
        $find_data = Files::where('id', $fileid)->with('ProjectDetails')->with('UserDetails')->with('SharedDetails')->first();
        if (!empty($find_data)) {
            $find_data->project_name = '';
            $find_data->owner_name = '';
            $find_data->parent_filename = '';
            $find_data->shared_with = [];
            if (!empty($find_data->ProjectDetails)) {
                $find_data->project_name = $find_data->ProjectDetails->project_name;
            }
            if (!empty($find_data->UserDetails)) {
                $find_data->owner_name = $find_data->UserDetails->firstname . ' ' . $find_data->UserDetails->lastname;
            }
            if (!empty($find_data->SharedDetails)) {
                $shared_userids = $find_data->SharedDetails->pluck('user_id')->toArray();
                $shared_userids_with_permission = $find_data->SharedDetails->pluck('permission_assigned','user_id')->toArray();
                // echo "<pre>";print_r($shared_userids_with_permission);die;
                $find_data->shared_with = User::whereIn('id', $shared_userids)->get();
                foreach($find_data->shared_with as $eachSharedId){
                    // echo $eachSharedId->id;
                    $eachSharedId->permission = $shared_userids_with_permission[$eachSharedId->id];
                }
                $find_data->shared_with->makeHidden(['email_verified_at', 'role', 'status', 'is_loggedin', 'authtoken', 'created_by']);
            }
            if ($find_data->parent_fileid > 0) {
                $parent_file = Files::find($find_data->parent_fileid);
                if (!empty($parent_file)) {
                    $find_data->parent_filename = $parent_file->original_filename;
                }

            }
            $find_childs = Files::where('parent_fileid', $fileid)->with('UserDetails')->get();
            if ($find_childs->count()) {
                foreach ($find_childs as $sc) {
                    $sc->owner_name = '';
                    if (!empty($sc->UserDetails)) {
                        $sc->owner_name = $sc->UserDetails->firstname . ' ' . $sc->UserDetails->lastname;
                    }
                    unset($sc->UserDetails);
                }
                $find_data->revision_files = $find_childs;

            } else {
                $find_data->revision_files = (object) array();
            }

            unset($find_data->ProjectDetails);
            unset($find_data->UserDetails);
            unset($find_data->SharedDetails);
            return success($find_data);
        } else {
            return error(Lang::get('validation.custom.invalid_fileid'));
        }
    }

    // Add file
    public function addfile(Request $request)
    {
        // echo "<pre>";print_r($request->shared_with);exit;
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'parent_fileid' => 'required|numeric',
            'shared_with' => 'array',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $note = null;
        if ($request->has('note') && trim($request->note) != '') {
            $note = $request->note;
        }

        $allowed_ext = array('xlsx', 'xls', 'txt', 'doc', 'docx', 'pdf', 'jpg', 'jpeg', 'png', 'csv', 'pptx', 'ppt');

        $uploaded_file = $request->file('file');
        $filesize = $uploaded_file->getSize();
        $file_size_mb = number_format($filesize / 1048576, 2);
        $filenameWithExt = $uploaded_file->getClientOriginalName();
        $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
        $extension = $uploaded_file->getClientOriginalExtension();
        if (!in_array(strtolower($extension), $allowed_ext)) {
            return error('file type must be from ' . implode(',', $allowed_ext));
        }

        $fileNameToStore = $filename . '_' . time() . '.' . $extension;
        $check_user = $request->get('current_user');
        $movefile = public_path() . env('PERSONAL_FILESPATH');
        $movefile = $movefile . $check_user->id . '/general/';
        $move_file = $uploaded_file->move($movefile, $fileNameToStore);
        if ($move_file) {
            $new_filepath = $movefile . $fileNameToStore;
            $folder_id = '0';
            if ($request->has('folder_id') && $request->folder_id > 0) 
                $folder_id = $request->folder_id;
            // echo $folder_id;die('---');

            $fileExists = Files::where('original_filename',$filenameWithExt)->where('folder_id', $folder_id)->get();
            if($fileExists->count())
                return error(Lang::get('validation.custom.files_folders_already_exists'));

            $add_file = Files::create(['original_filename' => $filenameWithExt, 'filename' => $fileNameToStore, 'folder_id' =>  $folder_id, 'type' => 'Personal', 'parent_fileid' => $request->parent_fileid, 'note' => $note, 'created_by' => $check_user->id]);
            if($check_user->role != '1'){
                FilesShared::create(['file_id' => $add_file->id, 'user_id' => $check_user->id, 'only_file_shared'=> '0', 'permission_assigned' => '3','is_own' => '1']);
            }
            if ($add_file) {

                #inner file upload with permission
                if ($request->has('folder_id') && $request->folder_id > 0){
                    $filteredSharedWith = removeNonePersimissionUsers($request->shared_with);
                    if (empty($filteredSharedWith)) {
                        $parentFolderSharedDetails = FoldersShared::select('user_id','permission_assigned')->where('folder_id', $request->folder_id)->get()->pluck('permission_assigned','user_id');
                        if($parentFolderSharedDetails){
                            foreach($parentFolderSharedDetails as $detailsUserId => $detailsPermission){
                                FilesShared::create(['file_id' => $add_file->id, 'user_id' => $detailsUserId, 'only_file_shared'=> '0', 'permission_assigned' => $detailsPermission,'is_own' => '0']);
                            }
                        }
                    }
                }
                else{
                    $filteredSharedWith = removeNonePersimissionUsers($request->shared_with);
                    if (!empty($filteredSharedWith)) {
                        foreach($filteredSharedWith as $eachSharedWith){
                            $toShareUserDetails = User::where('id', $eachSharedWith['user_id'])->first();
                            Mail::send('emails.MailFileShared', ['file_owner' =>$check_user->firstname.' '.$check_user->lastname, 'file_name' => $add_file->original_filename], function ($message) use ($toShareUserDetails) {
                                $message->to($toShareUserDetails['email']);
                                $message->subject('New files/folders Shared with you.');
                            });
                        }
                    }
                }
                

                addlog('Add', 'File', Lang::get('validation.logs.fileadd_success', ['filename' => $add_file->original_filename, 'username' => $check_user->username]), $check_user->id);
                $shared_userids = array();
                // $only_shared_user_ids = array();

                if ($request->has('shared_with') && !empty($request->shared_with)) {
                    $filteredSharedWith = removeNonePersimissionUsers($request->shared_with);
                    foreach($filteredSharedWith as $eachShareduserId){
                        array_push($shared_userids, $eachShareduserId['user_id']);
                    }
                }
                //Allow access to all users who have access to parent file
                if ($request->parent_fileid != 0) {
                    $find_parent = Files::find($request->parent_fileid);
                    if (!empty($find_parent)) {
                        array_push($shared_userids, $find_parent->created_by);
                        $already_shared = FilesShared::where('file_id', $find_parent->id)->get();
                        if ($already_shared->count()) {
                            $parent_shared = $already_shared->pluck('user_id')->toArray();
                            $shared_userids = array_merge($shared_userids, $parent_shared);
                        }
                    }
                }
                $shared_userids = array_unique($shared_userids);
                //remove current user from shared list,if present
                $find_pos = array_search($check_user->id, $shared_userids);
                if ($find_pos !== false) {
                    unset($shared_userids[$find_pos]);
                }
                if (!empty($shared_userids)) {
                    $current_time = currenthumantime();
                    $add_all = array();
                    foreach ($filteredSharedWith as $suid) {
                        $add_all[] = ['file_id' => $add_file->id, 'user_id' => $suid['user_id'], 'shared_by' => $check_user->id, 'only_file_shared'=> '1', 'permission_assigned' => $suid['permission'], 'created_at' => $current_time, 'updated_at' => $current_time];
                    }
                    if (!empty($add_all)) {
                        $add_many = FilesShared::insert($add_all);
                        return success($add_file, Lang::get('validation.custom.file_add_shared_success'));
                    }
                }
                return success($add_file, Lang::get('validation.custom.file_add_success'));
            } else {
                return error(Lang::get('validation.custom.add_data_failed'));
            }
        } else {
            return error(Lang::get('validation.custom.file_move_failed', ['filename' => $filenameWithExt]));
        }
    }

    // Update File
    public function updatefile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shared_with' => 'array',
            'parent_fileid' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        // $cur_data = Files::find($fileid);
        $cur_data = Files::find($request->id);
        if (!empty($cur_data)) {
            if ($request->has('note')) {
                $cur_data->note = $request->note;
            }

            if ($cur_data->parent_fileid != $request->parent_fileid) {
                $cur_data->parent_fileid = $request->parent_fileid;
            }

            $cur_data->save();
            $check_user = $request->get('current_user');
            FilesShared::where('file_id', $cur_data->id)->where('user_id', '!=' , $check_user->id)->delete();
            
            $shared_userids = array();
            if ($request->has('shared_with') && !empty($request->shared_with)) {
                // $shared_userids = $request->shared_with;
                $filteredSharedWith = removeNonePersimissionUsers($request->shared_with);
                // foreach($temp_shared_arr as $eachShareduserId){
                foreach($filteredSharedWith as $eachShareduserId){
                    array_push($shared_userids, $eachShareduserId['user_id']);
                }
            }
            //Allow access to all users who have access to parent file
            if ($request->parent_fileid != 0) {
                $find_parent = Files::find($request->parent_fileid);
                if (!empty($find_parent)) {
                    array_push($shared_userids, $find_parent->created_by);
                    $already_shared = FilesShared::where('file_id', $find_parent->id)->get();
                    if ($already_shared->count()) {
                        $parent_shared = $already_shared->pluck('user_id')->toArray();
                        $shared_userids = array_merge($shared_userids, $parent_shared);
                    }
                }
            }
            $shared_userids = array_unique($shared_userids);
            //remove current user from shared list,if present
            $find_pos = array_search($check_user->id, $shared_userids);
            if ($find_pos !== false) {
                unset($shared_userids[$find_pos]);
            }
            if (!empty($shared_userids)) {
                $current_time = currenthumantime();
                $add_all = array();
                $useremails = array();
                foreach ($filteredSharedWith as $suid) {
                    $add_all[] = ['file_id' => $cur_data->id, 'user_id' => $suid['user_id'], 'only_file_shared'=>'1', 'permission_assigned' => $suid['permission'], 'created_at' => $current_time, 'updated_at' => $current_time];
                    $user = User::select('email')->where('id',$suid['user_id'])->first()->get();
                    if($user->count()){
                        array_push($useremails,$user[0]['email']);
                    }
                }
                // echo "<pre>";print_r($add_all);die;
                if (!empty($add_all)) {
                    $add_many = FilesShared::insert($add_all);
                    foreach($useremails as $each_email){
                        $file_owner = User::select('firstname','lastname')->where('id',$cur_data->created_by)->first()->get();
                        Mail::send('emails.MailFileShared', ['file_owner' =>$file_owner[0]['firstname'].' '.$file_owner[0]['lastname'], 'file_name' => $cur_data->filename], function ($message) use ($each_email) {
                            $message->to($each_email);
                            $message->subject('New files Shared with you.');
                        });
                    }
                    
                }
            }
            return success($cur_data, Lang::get('validation.custom.file_update_success'));
        } else {
            return error(Lang::get('validation.custom.invalid_fileid'));
        }
    }


    // Delete File-Delete from server as well
    public function deletefile(Request $request, $fileid)
    {
        $cur_data = Files::find($fileid);
        if (!empty($cur_data)) {
            $check_user = $request->get('current_user');
            $filename = $cur_data->filename;
            $filetype = $cur_data->type;
            $createdby = $cur_data->created_by;
            if ($filetype == 'Project Import' && $cur_data->import_status == '2') {
                return error(Lang::get('validation.custom.file_import_in_progress'));
            }
            if ($cur_data->delete()) {
                if ($filetype == 'Project Import') {
                    $dir_filepath = env('IMPORT_FILESPATH') . $filename;
                } else if ($filetype == 'Project Export') {
                    $dir_filepath = env('EXPORT_FILESPATH') . $filename;
                } else {
                    $folderDetails = Folders::find($cur_data->folder_id);
                    if($cur_data->folder_id == 0 || $folderDetails->folderpath == '' || $cur_data->is_nested_upload == '0'){
                        $dir_filepath = env('PERSONAL_FILESPATH') . $createdby . '/general/' . $filename;
                    }
                    else{
                        $dir_filepath = env('PERSONAL_FILESPATH') . $createdby . '/'. $folderDetails->folderpath. '/' . $filename;
                    }
                    
                }
                $file = public_path($dir_filepath);
                if (file_exists($file)) {
                    $dlt_file = unlink($file);
                }
                $delete_shared = FilesShared::where('file_id', $fileid)->delete();
                $delete_logs = DownloadLogs::where('file_id', $fileid)->delete();
                $update_parents = Files::where('parent_fileid', $fileid)->update(['parent_fileid' => 0]);
                addlog('Permanent Delete', 'File', Lang::get('validation.logs.permanentdelete_success', ['module' => 'File', 'name' => $filename, 'username' => $check_user->username]), $check_user->id);
                return success([], Lang::get('validation.custom.permanent_delete_success', ['module' => 'File', 'name' => $filename]));
            } else {
                addlog('Permanent Delete', 'File', Lang::get('validation.logs.permanentdelete_failed', ['module' => 'file', 'name' => $filename, 'username' => $check_user->username]), $check_user->id);
                return error(Lang::get('validation.custom.permanent_delete_failed', ['module' => 'file', 'name' => $filename]));
            }
        } else {
            return error(Lang::get('validation.custom.invalid_fileid'));
        }
    }

    // Delete multiple files and folders
    public function deletemultiplefilesnfolders(Request $request){
        $validator = Validator::make($request->all(), [
            'file' => 'array',
            'folder' => 'array',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $check_user = $request->get('current_user');
        $fileIds = $request->file;
        $folderIds = $request->folder;
        $filesDeletedArr = $invalidFileIds = $inprogressFiles = $notExistFolder = array();
        if(empty($fileIds) && empty($folderIds)){
            return error(Lang::get('validation.custom.files_folders_empty'));
        }

        if(!empty($fileIds)){
            foreach($fileIds as $eachFileId){
                $cur_data = Files::find($eachFileId);
                if (!empty($cur_data) && $cur_data->type == 'Project Import' && $cur_data->import_status == '2') {
                    // return error(Lang::get('validation.custom.file_import_in_progress'));
                    array_push($inprogressFiles, $cur_data->filename);
                    continue;
                }
                if (!empty($cur_data)) {
                    $filename = $cur_data->filename;
                    $filetype = $cur_data->type;
                    $createdby = $cur_data->created_by;
                    
                    if ($cur_data->delete()) {
                        if ($filetype == 'Project Import') {
                            $dir_filepath = env('IMPORT_FILESPATH') . $filename;
                        } else if ($filetype == 'Project Export') {
                            $dir_filepath = env('EXPORT_FILESPATH') . $filename;
                        } else {
                            $folderDetails = Folders::find($cur_data->folder_id);
                            if($cur_data->folder_id == 0 || $folderDetails->folderpath == '' || $cur_data->is_nested_upload == '0'){
                                $dir_filepath = env('PERSONAL_FILESPATH') . $createdby . '/general/' . $filename;
                            }
                            else{
                                $dir_filepath = env('PERSONAL_FILESPATH') . $createdby . '/'. $folderDetails->folderpath. '/' . $filename;
                            }
                        }
                        $file = public_path($dir_filepath);
                        if (file_exists($file)) {
                            $dlt_file = unlink($file);
                        }
                        #delete files 
                        Files::where('id', $eachFileId)->delete();
                        #logs
                        addlog('Permanent Delete', 'File', Lang::get('validation.logs.permanentdelete_success', ['module' => 'File', 'name' => $filename, 'username' => $check_user->username]), $check_user->id);
                        FilesShared::where('file_id', $eachFileId)->delete();
                        DeleteDownloadFiles::where('file_id', $eachFileId)->delete();
                        ImportColumns::where('file_id', $eachFileId)->delete();
                        ImportData::where('file_id', $eachFileId)->delete();
                        ImportLogs::where('file_id', $eachFileId)->delete();
                        DownloadLogs::where('file_id', $eachFileId)->delete();
                        array_push($filesDeletedArr, $filename);
                        // return success([], Lang::get('validation.custom.permanent_delete_success', ['module' => 'File', 'name' => $filename]));
                    } else {
                        addlog('Permanent Delete', 'File', Lang::get('validation.logs.permanentdelete_failed', ['module' => 'file', 'name' => $filename, 'username' => $check_user->username]), $check_user->id);
                        // return error(Lang::get('validation.custom.permanent_delete_failed', ['module' => 'file', 'name' => $filename]));
                    }
                } else {
                    array_push($invalidFileIds, $eachFileId);
                    // return error(Lang::get('validation.custom.invalid_fileid'));
                }
            }
            
            
        }
        if(!empty($folderIds)){
            foreach($folderIds as $eachFolderId){
                $folderexists = Folders::find($eachFolderId);
                if(empty($folderexists)){
                    array_push($notExistFolder, $eachFolderId);
                    continue;
                }                    

                $check_user = $request->get('current_user');
                Folders::where('id', $eachFolderId)->delete();
                #log
                addlog('Permanent Delete', 'Folder', Lang::get('validation.logs.permanentdelete_success', ['module' => 'Folder', 'name' => $folderexists->foldername, 'username' => $check_user->username]), $check_user->id);
                FoldersShared::where('folder_id', $eachFolderId)->delete();

                $files = Files::where('folder_id', $eachFolderId)->get();
                $filenames = $files->pluck('filename','created_by');
                $fileids = $files->pluck('id')->toArray();


                $childFolderIds = $this->getChildFolderIds(array($eachFolderId));
                $childFiles = Files::whereIn('folder_id', $childFolderIds)->get();
                $childFileids = $childFiles->pluck('id')->toArray();
                $fileids = array_merge($fileids,$childFileids);
                

                Files::whereIn('id', $fileids)->delete();
                #log
                foreach($filenames as $eachFileName){
                    addlog('Permanent Delete', 'File', Lang::get('validation.logs.permanentdelete_success', ['module' => 'File', 'name' => $eachFileName, 'username' => $check_user->username]), $check_user->id);
                }
                
                FilesShared::whereIn('file_id', $fileids)->delete();
                DeleteDownloadFiles::whereIn('file_id', $fileids)->delete();
                ImportColumns::whereIn('file_id', $fileids)->delete();
                ImportData::whereIn('file_id', $fileids)->delete();
                ImportLogs::whereIn('file_id', $fileids)->delete();
                DownloadLogs::whereIn('file_id', $fileids)->delete();
                # delete child folders from db
                $nestedFolderIds = $this->getChildFolderIds(array($eachFolderId));
                Folders::whereIn('id', $nestedFolderIds)->delete();

                //delete files
                foreach($filenames as $user_id => $filename){
                    $dir_filepath = env('PERSONAL_FILESPATH') . $user_id . '/' . $filename;
                    $file = public_path($dir_filepath);
                    if (file_exists($dir_filepath)) {
                        // die('exists');
                        unlink($file);
                        #log
                        addlog('Permanent Delete', 'File', Lang::get('validation.logs.permanentdelete_success', ['module' => 'File', 'name' => $filename, 'username' => $check_user->username]), $check_user->id);
                    }
                }
                $folder_owner_id = $folderexists->created_by;
                $deleteFolderPath = env('PERSONAL_FILESPATH') .$folder_owner_id .'/'.$folderexists->folderpath;
                // echo $deleteFolderPath;die;
                if (file_exists(public_path($deleteFolderPath)) && $folderexists->folderpath != '') {
                    File::deleteDirectory(public_path($deleteFolderPath));
                }
                // return success(array(), Lang::get('validation.custom.folder_deleted_success'));
            }
        }

        $response['files_deleted'] = $filesDeletedArr;
        $response['invalid_file_ids'] = $invalidFileIds;
        $response['in_progress_files'] = $inprogressFiles;
        $response['invalid_folderids'] = $notExistFolder;
        if(empty($invalidFileIds) && empty($inprogressFiles) && empty($notExistFolder))
            return success(array(), Lang::get('validation.custom.files_folders_deleted'));
        else
            return error(Lang::get('validation.custom.files_folders_delete_error'), '',$response);
        
    }

    

    public function getChildFolderIds($folderId, $childFolderIds = array()){
        $childFIds = $childFolderIds;
        $folders = Folders::select('id')->whereIn('parent_folder_id', $folderId)->get();
        $arr = array();
        if($folders->count() > 0){
            $newIds = $folders->pluck('id')->toArray();
            array_push($childFIds,$newIds);
            return $this->getChildFolderIds($newIds, $childFIds);
        }
        else{
            foreach ($childFIds AS $data) {
                $arr = array_merge($arr, $data);
            }
        }
        return $arr;
    }

    // list of all download loags
    public function downloadlogslist(Request $request)
    {
        $getdata = DownloadLogs::query()->orderBy('id','desc');
        $getdata = $getdata->where('delete_status', 0);
        if ($request->has('userid')) {
            $getdata = $getdata->where('downloaded_by', $request->userid);
        }
        $all_data = $getdata->with('FileDetails')->get();
        if ($all_data->count()) {
            foreach ($all_data as $sd) {
                // if (!empty($sd->FileDetails)) {
                //     $sd->file_name = $sd->FileDetails->original_filename;
                // }
                
                if (!empty($sd->FileDetails)) {
                    $sd->FileDetails->owner_name = '';
                    $user = User::select('firstname', 'lastname')
                    ->where('id', '=', $sd->FileDetails->created_by)
                    ->first();
                    if(!empty($user)){
                        $sd->FileDetails->owner_name = $user->firstname. ' ' . $user->lastname;
                    }
                }


                if (!empty($sd->UserDetails)) {
                    $sd->downloaded_byuser = $sd->UserDetails->firstname . ' ' . $sd->UserDetails->lastname;
                }
                $sd->download_date = date("d/m/Y h:i:s A", strtotime($sd->download_date));
                // unset($sd->FileDetails);
                unset($sd->UserDetails);
            }
        }
        return ($all_data) ? success($all_data) : error();
    }
}
