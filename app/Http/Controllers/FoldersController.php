<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Files;
use App\Models\FilesShared;
use App\Models\Folders;
use App\Models\FoldersShared;
use App\Models\DeleteDownloadFiles;
use App\Models\DownloadLogs;
use App\Models\ImportColumns;
use App\Models\ImportData;
use App\Models\ImportLogs;
use App\Models\User;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\File;
use PDO;

class FoldersController extends Controller
{
    //
    public function addfolder(Request $request){
        $check_user = $request->get('current_user');
        $validator = Validator::make($request->all(), [
            'folder_name' => 'required',
            'shared_with' => 'array',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        if (Folders::where('foldername', $request->folder_name)->where('parent_folder_id',$request->parent_folder_id)->where('created_by',$check_user->id)->exists())
            return error(Lang::get('validation.custom.folder_name_already_exists'));
        

        $folder = Folders::create(['foldername' => $request->folder_name, 'created_by' => $check_user->id, 'parent_folder_id' => $request->parent_folder_id]);
        if($folder){
            // $shared_with= json_decode($request->shared_with);
            // print_r($request->shared_with);return;
            // print_r($request->folder_name);return;
            // if($request->shared_with){

                if($check_user->role != '1'){
                    FoldersShared::create(['folder_id' => $folder->id, 'user_id' => $check_user->id, 'shared_by'=> $check_user->id, 'permission_assigned' => '3']);
                }

                #inner folder upload with permission
                if ($request->has('parent_folder_id') && $request->parent_folder_id > 0){
                    $filteredSharedWith = removeNonePersimissionUsers($request->shared_with);
                    if (empty($filteredSharedWith)) {
                        $parentFolderSharedDetails = FoldersShared::select('user_id','permission_assigned')->where('folder_id', $request->parent_folder_id)->get()->pluck('permission_assigned','user_id');
                        if($parentFolderSharedDetails){
                            foreach($parentFolderSharedDetails as $detailsUserId => $detailsPermission){
                                FoldersShared::create(['folder_id' => $folder->id, 'user_id' => $detailsUserId, 'shared_by'=> $check_user->id, 'only_folder_shared'=> '0', 'permission_assigned' => $detailsPermission]);
                            }
                        }
                    }
                }
                //

                $shared_with= removeNonePersimissionUsers($request->shared_with);
                if(!empty($shared_with)){
                    $folder_id = $folder->id;
                    $shared_with_temp_arr = array();
                    foreach($shared_with as $each_user){
                        $is_only_folder_shared = '0';
                        if($request->parent_folder_id != '0'){
                            $is_only_folder_shared = '1';
                        }
                        $folder_shared_with = FoldersShared::create(['folder_id' => $folder_id, 'user_id' => $each_user['user_id'], 'shared_by'=> $check_user->id, 'only_folder_shared'=> $is_only_folder_shared, 'permission_assigned' => $each_user['permission']]);
                        $useremail = User::select('email')->where('id',$each_user['user_id'])->get();
                        Mail::send('emails.MailFileShared', ['file_owner' =>$check_user->firstname.' '.$check_user->lastname, 'file_name' => $folder->foldername], function ($message) use ($useremail) {
                            $message->to($useremail[0]['email']);
                            $message->subject('New files/folders Shared with you.');
                        });
                    }
                }
            // }
        }
        return success($folder, Lang::get('validation.custom.folder_added_success'));
    }
    

    public function viewfolder(Request $request, $folderid){
        $check_user = $request->get('current_user');
        $folderexists = Folders::find($folderid);
        if(empty($folderexists)) 
            return error(Lang::get('validation.custom.invalid_folderid'));

        $folder = Folders::where('id',$folderid)->first();
        $folder->owner_name = '';
        $owner = User::select('firstname','lastname','email','username')->where('id',$folder->created_by)->first();
        if($owner){
            $folder->owner_name = $owner->firstname. ' '. $owner->lastname;
        }
        $folder->bread_crumbs = $folder->foldername;

        $rootFolderPermission = FoldersShared::select('permission_assigned')->where('folder_id', $folderid)->where('user_id',$check_user->id)->first();
        if($rootFolderPermission)
            $folder->permission = $rootFolderPermission->permission_assigned.'';
        else
            $folder->permission = '3';
        
        
        $files_and_folders = $myFolders = $myFiles = array();
        if ($request->has('shared_only') && $request->shared_only == 1) {
            $myFolders = Folders::where('parent_folder_id', $folderid)->get()->toArray();
            $myFiles = Files::where('folder_id',$folderid)->get()->toArray();
        }
        else if ($request->has('mine_only') && $request->mine_only == 1) {
            $myFolders = Folders::where('parent_folder_id', $folderid)->get()->toArray();
            $myFiles = Files::where('folder_id',$folderid)->get()->toArray();
        }
        else{
            $childFoldersIds = Folders::select('id')->where('parent_folder_id', $folderid)->get()->pluck('id')->toArray();
            $childFoldersShared = FoldersShared::select('folder_id')->whereIn('folder_id',$childFoldersIds)->get()->pluck('folder_id')->toArray();
            $myFolderIds = array_diff($childFoldersIds,$childFoldersShared);
            $myFolders = Folders::whereIn('id',$myFolderIds)->get()->toArray();

            $childFilesIds = Files::select('id')->where('folder_id',$folderid)->get()->pluck('id')->toArray();
            $childFilesShared = FilesShared::select('file_id')->whereIn('file_id',$childFilesIds)->get()->pluck('file_id')->toArray();
            $myFilesIds = array_diff($childFilesIds,$childFilesShared);
            $myFiles = Files::whereIn('id',$myFilesIds)->get()->toArray();

        }

        $foldersharedwith = FoldersShared::where('folder_id',$folderid)->with('userDetails')->get();
        $userdetails= array();
        foreach($foldersharedwith as $eachFolder){
            if(!$eachFolder->userDetails->isEmpty())
                $userdetails[] = array('id'=>$eachFolder->userDetails[0]['id'],'name' => $eachFolder->userDetails[0]['firstname'].' ' .$eachFolder->userDetails[0]['lastname'],'email' => $eachFolder->userDetails[0]['email'], 'username' => $eachFolder->userDetails[0]['username'], 'permission' => $eachFolder['permission_assigned']);
        }
        $folder['shared_with'] = $userdetails;

        foreach($myFolders as $eachMyFolder){
            $folder_shared_with = FoldersShared::where('folder_id',$eachMyFolder['id'])->with('UserDetails')->where('user_id','!=',$check_user->id)->where('shared_by','!=',$check_user->id)->get();
            $folder_shared_with_permission = $folder_shared_with->pluck('permission_assigned','user_id')->toArray();
            $folderPermission = FoldersShared::select('permission_assigned')->where('folder_id',$eachMyFolder['id'])->where('user_id',$check_user->id)->first();
            if($folderPermission)
                $eachMyFolder['permission'] = $folderPermission->permission_assigned.'';
            else
                $eachMyFolder['permission'] = '3';

            $shared_arr =array();
            if(!empty($folder_shared_with)){
                foreach($folder_shared_with as $each){
                    if(isset($each->UserDetails[0])){
                        array_push($shared_arr, array(
                            'id' => $each->UserDetails[0]['id'],
                            'firstname' => $each->UserDetails[0]['firstname'],
                            'lastname' => $each->UserDetails[0]['lastname'],
                            'email' => $each->UserDetails[0]['email'],
                            'permission' => $folder_shared_with_permission[$each->UserDetails[0]['id']],
                        ));
                    }
                }
            }

            $eachMyFolder['owner_name'] = '';
            $owner = User::select('firstname','lastname')->where('id',$eachMyFolder['created_by'])->first();
            if($owner){
                $eachMyFolder['owner_name'] = $owner['firstname']. ' '. $owner['lastname'];
            }

            $eachMyFolder['parent_filename'] = '';
            if($eachMyFolder['parent_folder_id'] > 0){
                $foldername = Folders::select('foldername')->where('id', $eachMyFolder['parent_folder_id'])->first()->get();
                if($foldername->count()){
                    $eachMyFolder['parent_filename'] = $foldername[0]['foldername'];
                }
            }
            $eachMyFolder['shared_with'] = $shared_arr;
            $eachMyFolder['document_type'] = 'folder';
            $eachMyFolder['type'] = '';
            $eachMyFolder['parent_folderid'] = '';
            $eachMyFolder['total_rows'] = '';
            $eachMyFolder['imported_rows'] = '';
            $eachMyFolder['import_status'] = '';
            $eachMyFolder['import_start_time'] = '';
            $eachMyFolder['import_end_time'] = '';
            $eachMyFolder['status'] = '';
            $eachMyFolder['note'] = '';
            $eachMyFolder['project_name'] = '';
            $eachMyFolder['parent_filename'] = '';
            array_push($files_and_folders, $eachMyFolder);
        }

        foreach($myFiles as $eachMyFile){
            $files_shared_with = FilesShared::where('file_id',$eachMyFile['id'])->with('UserDetails')->where('user_id','!=',$check_user->id)->where('shared_by','!=',$check_user->id)->get();
            $files_shared_with_permission = $files_shared_with->pluck('permission_assigned','user_id')->toArray();
            $filePermission = FilesShared::select('permission_assigned')->where('file_id',$eachMyFile['id'])->where('user_id',$check_user->id)->first();
            if($filePermission)
                $eachMyFile['permission'] = $filePermission->permission_assigned.'';
            else
                $eachMyFile['permission'] = '3';

            $shared_arr =array();
            if(!empty($files_shared_with)){
                foreach($files_shared_with as $each){
                    if(isset($each->UserDetails[0]['id'])){
                        array_push($shared_arr, array(
                            'id' => $each->UserDetails[0]['id'],
                            'firstname' => $each->UserDetails[0]['firstname'],
                            'lastname' => $each->UserDetails[0]['lastname'],
                            'email' => $each->UserDetails[0]['email'],
                            'permission' => $files_shared_with_permission[$each->UserDetails[0]['id']]
                        ));    
                    }
                }
            }
            $eachMyFile['owner_name'] = '';
            $owner = User::select('firstname','lastname')->where('id',$eachMyFile['created_by'])->first();
            if($owner){
                $eachMyFile['owner_name'] = $owner['firstname']. ' '. $owner['lastname'];
            }
            $eachMyFile['parent_filename'] = '';
            if($eachMyFile['parent_fileid'] > 0){
                $filename = Files::select('filename')->where('id', $eachMyFile['parent_fileid'])->first();
                if($filename){
                    $eachMyFile['parent_filename'] = $filename->filename;
                }
            }
            $eachMyFile['shared_with'] = $shared_arr;
            $eachMyFile['document_type'] = 'file';
            array_push($files_and_folders, $eachMyFile);
        }

        $folder['files_and_folders'] = $files_and_folders;
        return success($folder);
        
    }

    public function viewfolder_old(Request $request, $folderid){
        $check_user = $request->get('current_user');
        $folderexists = Folders::find($folderid);
        if(empty($folderexists)) 
            return error(Lang::get('validation.custom.invalid_folderid'));

        $folder = Folders::where('id',$folderid)->first();
        $folder->owner_name = '';
        $owner = User::select('firstname','lastname','email','username')->where('id',$folder->created_by)->first();
        if($owner){
            $folder->owner_name = $owner->firstname. ' '. $owner->lastname;
        }

        if($check_user->role == 1){
            $folder->permission = '3';
        }
        else{
            $rootFolderPermission = FoldersShared::select('permission_assigned')->where('folder_id', $folderid)->where('user_id',$check_user->id)->first();
            if($rootFolderPermission)
                $folder->permission = $rootFolderPermission->permission_assigned.'';
            else
                $folder->permission = '3';
        }
        // $folderPermission = FoldersShared::select('permission_assigned')->where('folder_id', $folderid)->where('user_id',$check_user->id)->first();
        // echo "<pre>";print_r($folder);die;
        $childFolders = Folders::where('parent_folder_id', $folderid)->get()->toArray();
        $childFiles = Files::where('folder_id',$folderid)->get()->toArray();
        $foldersharedwith = FoldersShared::where('folder_id',$folderid)->with('userDetails')->get();
        $userdetails= array();
        foreach($foldersharedwith as $eachFolder){
            if(!$eachFolder->userDetails->isEmpty())
                $userdetails[] = array('id'=>$eachFolder->userDetails[0]['id'],'name' => $eachFolder->userDetails[0]['firstname'].' ' .$eachFolder->userDetails[0]['lastname'],'email' => $eachFolder->userDetails[0]['email'], 'username' => $eachFolder->userDetails[0]['username'], 'permission' => $eachFolder['permission_assigned']);
        }
        $files_and_folders = array();
        $pluckedFolderIds = array_column($childFolders, 'id');
        $doExistsFolderPluckedIds = FoldersShared::whereIn('folder_id',$pluckedFolderIds)->where('user_id',$check_user->id)->pluck('folder_id')->toArray();
        foreach($childFolders as $eachFolder){
            if($check_user->role != 1 && (!in_array($eachFolder['id'], $doExistsFolderPluckedIds))){
                continue;
            }
            $folder_shared_with = FoldersShared::where('folder_id',$eachFolder['id'])->with('UserDetails')->get();
            $folder_shared_with_permission = $folder_shared_with->pluck('permission_assigned','user_id')->toArray();
            // echo "<pre>";print_r($folder_shared_with_permission);die;
            // $eachFolder->permission = $folder_shared_with->permission_assigned;
            if($check_user->role == 1){
                $eachFolder['permission'] = '3';
            }
            else{
                $folderPermission = FoldersShared::select('permission_assigned')->where('folder_id',$eachFolder['id'])->where('user_id',$check_user->id)->first();
                if($folderPermission)
                    $eachFolder['permission'] = $folderPermission->permission_assigned.'';
                else
                    $eachFolder['permission'] = '3';
            }

            $shared_arr =array();
            if(!empty($folder_shared_with)){
                foreach($folder_shared_with as $each){
                    if(isset($each->UserDetails[0])){
                        array_push($shared_arr, array(
                            'id' => $each->UserDetails[0]['id'],
                            'firstname' => $each->UserDetails[0]['firstname'],
                            'lastname' => $each->UserDetails[0]['lastname'],
                            'email' => $each->UserDetails[0]['email'],
                            'permission' => $folder_shared_with_permission[$each->UserDetails[0]['id']],
                        ));
                    }
                }
            }

            $eachFolder['owner_name'] = '';
            $owner = User::select('firstname','lastname')->where('id',$eachFolder['created_by'])->first();
            if($owner){
                $eachFolder['owner_name'] = $owner['firstname']. ' '. $owner['lastname'];
            }

            $eachFolder['parent_filename'] = '';
            if($eachFolder['parent_folder_id'] > 0){
                $foldername = Folders::select('foldername')->where('id', $eachFolder['parent_folder_id'])->first()->get();
                if($foldername->count()){
                    $eachFolder['parent_filename'] = $foldername[0]['foldername'];
                }
            }

            $eachFolder['shared_with'] = $shared_arr;
            $eachFolder['document_type'] = 'folder';
            $eachFolder['type'] = '';
            $eachFolder['parent_folderid'] = '';
            $eachFolder['total_rows'] = '';
            $eachFolder['imported_rows'] = '';
            $eachFolder['import_status'] = '';
            $eachFolder['import_start_time'] = '';
            $eachFolder['import_end_time'] = '';
            $eachFolder['status'] = '';
            $eachFolder['note'] = '';
            $eachFolder['project_name'] = '';
            $eachFolder['parent_filename'] = '';
            array_push($files_and_folders, $eachFolder);
        }
        $pluckedIds = array_column($childFiles, 'id');
        $doExistsPluckedIds = FilesShared::whereIn('file_id',$pluckedIds)->where('user_id',$check_user->id)->pluck('file_id')->toArray();
        foreach($childFiles as $eachFile){
            if($check_user->role != 1 && (!in_array($eachFile['id'], $doExistsPluckedIds))){
                continue;
            }
            $files_shared_with = FilesShared::where('file_id',$eachFile['id'])->with('UserDetails')->get();
            $files_shared_with_permission = $files_shared_with->pluck('permission_assigned','user_id')->toArray();
            // $eachFile->permission = $files_shared_with->permission_assigned;
            if($check_user->role == 1){
                $eachFile['permission'] = '3';
            }
            else{
                $filePermission = FilesShared::select('permission_assigned')->where('file_id',$eachFile['id'])->where('user_id',$check_user->id)->first();
                if($filePermission)
                    $eachFile['permission'] = $filePermission->permission_assigned.'';
                else
                    $eachFile['permission'] = '3';
            }
            
            $shared_arr =array();
            if(!empty($files_shared_with)){
                foreach($files_shared_with as $each){
                    if(isset($each->UserDetails[0]['id'])){
                        array_push($shared_arr, array(
                            'id' => $each->UserDetails[0]['id'],
                            'firstname' => $each->UserDetails[0]['firstname'],
                            'lastname' => $each->UserDetails[0]['lastname'],
                            'email' => $each->UserDetails[0]['email'],
                            'permission' => $files_shared_with_permission[$each->UserDetails[0]['id']]
                        ));    
                    }
                }
            }
            $eachFile['owner_name'] = '';
            $owner = User::select('firstname','lastname')->where('id',$eachFile['created_by'])->first();
            if($owner){
                $eachFile['owner_name'] = $owner['firstname']. ' '. $owner['lastname'];
            }
            $eachFile['parent_filename'] = '';
            if($eachFile['parent_fileid'] > 0){
                $filename = Files::select('filename')->where('id', $eachFile['parent_fileid'])->first();
                if($filename){
                    // echo "<pre>";print_r($filename->filename);
                    $eachFile['parent_filename'] = $filename->filename;
                }
            }
            $eachFile['shared_with'] = $shared_arr;
            $eachFile['document_type'] = 'file';
            array_push($files_and_folders, $eachFile);
        }
        // die;
        $folder['files_and_folders'] = $files_and_folders;
        $folder['shared_with'] = $userdetails;
        $folderSharedDetails = FoldersShared::select('only_folder_shared')->where('folder_id',$folderid)->where('user_id', $check_user->id)->get();
        if($folder->folderpath == ''){
            // $folderSharedDetails = FoldersShared::select('only_folder_shared')->where('folder_id',$folderid)->where('user_id', $check_user->id)->get();
            if($check_user->role != '1'){
                
                $folder['bread_crumbs'] = $folder->foldername;
            }
            else{
                $folder['bread_crumbs'] = $this->getParentFolderPath($folder, $folder->foldername);
            }
            
            // $folder['bread_crumbs'] = $this->getParentFolderPath($folder, $folder->foldername);
            
        }
        else{
            // if($check_user->role != '1' && !empty($folderSharedDetails[0]) && $folderSharedDetails[0]->only_folder_shared == '1'){
            if($check_user->role != '1'){
                $folder['bread_crumbs'] = $folder->foldername;
            }
            else{
                $folder['bread_crumbs'] = $this->getParentFolderPath($folder, $folder->foldername);
            }
        }
        return success($folder);
        
    }

    public function getParentFolderPath($folder, $breadcrumb) {
        $bc = $breadcrumb;
        if($folder->parent_folder_id == 0){
            return $bc;
        }
        else{
            $folderDetails = Folders::where('id',$folder->parent_folder_id)->first();
            $bc = $folderDetails->foldername.'/'.$bc;
            if($folderDetails->parent_folder_id != 0)
                $this->getParentFolderPath($folderDetails, $bc);
            
            return $bc;
        }
    }

    public function editfolder(Request $request){
        $check_user = $request->get('current_user');
        $validator = Validator::make($request->all(), [
            'folder_name' => 'required',
            'folder_id' => 'required',
            'shared_with' => 'array',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $folder_id = $request->folder_id;
        $folder = Folders::where('id', $folder_id)->update(['foldername' => $request->folder_name]);
        $shared_with = removeNonePersimissionUsers($request->shared_with);
        /*
        $not_shared_with = getNonePersimissionUsers($request->shared_with);
        $folders_shared_with = FoldersShared::where('folder_id', $folder_id)->pluck('user_id')->toArray();
        if($folders_shared_with){
            $to_remove = array_intersect($folders_shared_with, $not_shared_with);
            if($to_remove)
                FoldersShared::where('folder_id', $folder_id)->whereIn('user_id',$to_remove)->delete();
        }
        */
        $folderDetails = Folders::where('id', $folder_id)->get();
        if(!empty($shared_with)){
            $folders_shared_with = FoldersShared::where('folder_id', $folder_id)->pluck('user_id')->toArray();
            $childFolderIds = $this->getChildFolderIds(array($folder_id));
            $allFolderIds = array_merge($childFolderIds,array($folder_id));
            if($check_user->role == '1')
                FoldersShared::whereIn('folder_id', $allFolderIds)->delete();
            else
                FoldersShared::whereIn('folder_id', $allFolderIds)->where('user_id', '<>', $check_user->id)->delete();

            $allFiles = Files::whereIn('folder_id', $allFolderIds)->get();
            $allFileIds = $allFiles->pluck('id')->toArray();
            FilesShared::whereIn('file_id',$allFileIds)->where('user_id', '<>', $check_user->id)->delete();

            $shared_userids = array();
            $shared_arr_with_key_as_userid = array();
            foreach($shared_with as $userKey => $eachShareduserId){
                array_push($shared_userids, $eachShareduserId['user_id']);
                $shared_arr_with_key_as_userid[$eachShareduserId['user_id']] = $eachShareduserId;
            }
            $new_users = array_diff($shared_userids,$folders_shared_with);
            foreach($new_users as $each_user){
                $userDetails = User::select('email')->where('id', $each_user)->first();
                Mail::send('emails.MailFileShared', ['file_owner' =>$check_user->firstname.' '.$check_user->lastname, 'file_name' => $request->folder_name], function ($message) use ($userDetails) {
                    $message->to($userDetails->email);
                    $message->subject('New files/folders Shared with you.');
                });
            }

            foreach($shared_with as $eachSharedWith){
                $onlyFolderShared = '0';
                if($folderDetails[0]->parent_folder_id != '0'){
                    $onlyFolderShared = '1';
                }
                // echo $onlyFolderShared;return;
                FoldersShared::create(['folder_id' => $folder_id, 'user_id' => $eachSharedWith['user_id'], 'shared_by'=> $check_user->id, 'only_folder_shared'=> $onlyFolderShared, 'permission_assigned' => $eachSharedWith['permission']]);
                foreach($childFolderIds as $eachChildFolderId){
                    FoldersShared::create(['folder_id' => $eachChildFolderId, 'user_id' => $eachSharedWith['user_id'], 'shared_by'=> $check_user->id, 'permission_assigned' => $eachSharedWith['permission']]);
                }

                foreach($allFileIds as $eachFileId){
                    FilesShared::create(['file_id' => $eachFileId, 'user_id' => $eachSharedWith['user_id'], 'permission_assigned' => $eachSharedWith['permission'],'only_file_shared' => '0']);
                }
            }
        }
        else{
            // if ($check_user->role == 2 || $check_user->role == 3){
                $folder_id = $request->folder_id;
                $childFolderIds = $this->getChildFolderIds(array($folder_id));
                $allFolderIds = array_merge($childFolderIds,array($folder_id));
                $allFiles = Files::whereIn('folder_id', $allFolderIds)->get();
                $allFileIds = $allFiles->pluck('id')->toArray();
                FilesShared::whereIn('file_id',$allFileIds)->where('user_id', '<>', $check_user->id)->delete();
                if($check_user->role != '1')
                    FoldersShared::whereIn('folder_id', $allFolderIds)->where('user_id', '<>', $folderDetails[0]->created_by)->where('user_id', '<>', $check_user->id)->delete();
                else
                    FoldersShared::whereIn('folder_id', $allFolderIds)->delete();
            // }
        }
        
        return success($folder, Lang::get('validation.custom.folder_updated_success'));
    }

    public function editfolder_old(Request $request){
        $check_user = $request->get('current_user');
        $validator = Validator::make($request->all(), [
            'folder_name' => 'required',
            'folder_id' => 'required',
            'shared_with' => 'array',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $folder_id = $request->folder_id;
        $folder = Folders::where('id', $folder_id)->update(['foldername' => $request->folder_name]);
        // $shared_with = $request->shared_with;
        $shared_with = removeNonePersimissionUsers($request->shared_with);
        $not_shared_with = getNonePersimissionUsers($request->shared_with);
        $folders_shared_with = FoldersShared::where('folder_id', $folder_id)->pluck('user_id')->toArray();
        if($folders_shared_with){
            $to_remove = array_intersect($folders_shared_with, $not_shared_with);
            if($to_remove)
                FoldersShared::where('folder_id', $folder_id)->whereIn('user_id',$to_remove)->delete();
        }

        if(!empty($shared_with)){
            $shared_userids = array();
            $shared_arr_with_key_as_userid = array();
            foreach($shared_with as $userKey => $eachShareduserId){
                array_push($shared_userids, $eachShareduserId['user_id']);
                $shared_arr_with_key_as_userid[$eachShareduserId['user_id']] = $eachShareduserId;
            }
            // echo "<pre>";print_r($shared_arr_with_key_as_userid);die;
            $folders_shared_with = FoldersShared::where('folder_id', $folder_id)->pluck('user_id')->toArray();
            $new_users = array_diff($shared_userids,$folders_shared_with);
            $to_be_deleted_users = array_diff($folders_shared_with,$shared_userids);
            FoldersShared::whereIn('user_id',$to_be_deleted_users)->delete();
            #child nodes
            $nestedIds = $this->getChildFolderIds(array($folder_id));
            foreach($new_users as $each_user){
                FoldersShared::create(['folder_id' => $folder_id, 'user_id' => $each_user, 'shared_by'=> $check_user->id, 'permission_assigned' => $shared_arr_with_key_as_userid[$each_user]['permission']]);
                Mail::send('emails.MailFileShared', ['file_owner' =>$check_user->firstname.' '.$check_user->lastname, 'file_name' => $request->folder_name], function ($message) use ($check_user) {
                    $message->to($check_user->email);
                    $message->subject('New files/folders Shared with you.');
                });
            }
            foreach($shared_arr_with_key_as_userid as $each_permission_change){
                FoldersShared::where('user_id', $each_permission_change['user_id'])->where('folder_id',$folder_id)->update(['permission_assigned' => $each_permission_change['permission']]);

                array_push($nestedIds,$folder_id);
                if(is_array($nestedIds) && count($nestedIds) > 0){

                    FoldersShared::whereIn('folder_id',$nestedIds)->where('user_id', $each_permission_change['user_id'])->delete();
                    foreach($nestedIds as $id){
                        $tempFolderArr = array('folder_id' =>$id, 'user_id'=>$each_permission_change['user_id'], 'permission_assigned' => $each_permission_change['permission']);
                        FoldersShared::insert($tempFolderArr);
                    }
                    
                    $files = Files::whereIn('folder_id',$nestedIds)->pluck('id');
                    FilesShared::whereIn('file_id',$files)->where('user_id', $each_permission_change['user_id'])->delete();
                    foreach($files as $file){
                        $temp = array('file_id'=>$file, 'user_id' => $each_permission_change['user_id'], 'only_file_shared' => '0', 'permission_assigned' => $each_permission_change['permission']);
                        FilesShared::insert($temp);
                    }
                }
            }
        }
        else{

            // if ($check_user->role == 2 || $check_user->role == 3){
                $folder_id = $request->folder_id;
                $childFolderIds = $this->getChildFolderIds(array($folder_id));
                $allFolderIds = array_merge($childFolderIds,array($folder_id));
                $allFiles = Files::whereIn('folder_id', $allFolderIds)->get();
                $allFileIds = $allFiles->pluck('id')->toArray();
                FilesShared::whereIn('file_id',$allFileIds)->where('user_id', '<>', $check_user->id)->delete();
            // }
        }
        
        return success($folder, Lang::get('validation.custom.folder_updated_success'));
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

    public function deletefolder(Request $request, $folderid){
        $folderexists = Folders::find($folderid);
        if(empty($folderexists)) 
            return error(Lang::get('validation.custom.invalid_folderid'));

        $check_user = $request->get('current_user');
        
        #log
        addlog('Permanent Delete', 'Folder', Lang::get('validation.logs.permanentdelete_success', ['module' => 'Folder', 'folder_id' => $folderid, 'username' => $check_user->username]), $check_user->id);
        
        $files = Files::where('folder_id', $folderid)->get();
        $filenames = $files->pluck('filename','created_by');
        $fileids = $files->pluck('id')->toArray();

        $childFolderIds = $this->getChildFolderIds(array($folderid));
        $allFolderIds = array_merge($childFolderIds, array($folderid));
        FoldersShared::whereIn('folder_id', $allFolderIds)->delete();
        Folders::whereIn('id', $allFolderIds)->delete();

        $childFiles = Files::whereIn('folder_id', $childFolderIds)->get();
        $childFileids = $childFiles->pluck('id')->toArray();
        $fileids = array_merge($fileids,$childFileids);
        
        Files::whereIn('id', $fileids)->delete();
        #log
        addlog('Permanent Delete', 'File', Lang::get('validation.logs.permanentdelete_success', ['module' => 'File', 'file_details' => $filenames, 'username' => $check_user->username]), $check_user->id);
        FilesShared::whereIn('file_id', $fileids)->delete();
        DeleteDownloadFiles::whereIn('file_id', $fileids)->delete();
        ImportColumns::whereIn('file_id', $fileids)->delete();
        ImportData::whereIn('file_id', $fileids)->delete();
        ImportLogs::whereIn('file_id', $fileids)->delete();
        DownloadLogs::whereIn('file_id', $fileids)->delete();

        //delete files
        foreach($filenames as $user_id => $filename){
            $dir_filepath = env('PERSONAL_FILESPATH') . $user_id . '/' . $filename;
            $file = public_path($dir_filepath);
            if (file_exists($dir_filepath)) {
                unlink($file);
                #log
                addlog('Permanent Delete', 'File', Lang::get('validation.logs.permanentdelete_success', ['module' => 'File', 'name' => $filename, 'username' => $check_user->username]), $check_user->id);
            }
        }
        $folder_owner_id = $folderexists->created_by;
        // $deleteFolderPath = env('PERSONAL_FILESPATH') .$check_user->id .'/'.$folderexists->folderpath;
        $deleteFolderPath = env('PERSONAL_FILESPATH') .$folder_owner_id .'/'.$folderexists->folderpath;
        if (file_exists(public_path($deleteFolderPath))) {
            File::deleteDirectory(public_path($deleteFolderPath));
        }     
        return success(array(), Lang::get('validation.custom.folder_deleted_success'));
    }

    public function viewall(Request $request){
        $check_user = $request->get('current_user');
        $files= array();
        $folders = array();
        $shared_userids_with_permission_files = array();
        $shared_userids_with_permission_folders = array();
        // if ($check_user->role == 2 || $check_user->role == 3){
        //     if ($request->has('shared_only') && $request->shared_only == 1) {
        //         $files = FilesShared::where('user_id', $check_user->id)->where('only_file_shared','1');
        //         $fileIds = $files->pluck('file_id');
        //         $shared_userids_with_permission_files = $files->pluck('permission_assigned','user_id')->toArray();
        //         $files = Files::whereIn('id',$fileIds)->get();
        //         $folders = FoldersShared::where('user_id', $check_user->id);
                
        //         $folders2 = FoldersShared::where('only_folder_shared', '1');
        //         $folders2ids = $folders2->pluck('folder_id');
        //         $folderids = $folders->pluck('folder_id');
        //         $shared_userids_with_permission_folders = $folders->pluck('permission_assigned','user_id')->toArray();
        //         $folders = Folders::whereIn('id', $folderids)->where('parent_folder_id','0')->orWhereIn('id', $folders2ids)->get();
        //     }else{
        //         $files = Files::where('created_by', $check_user->id)->where('folder_id','0')->get();
        //         $folders = Folders::where('created_by', $check_user->id)->where('parent_folder_id','0')->get();
        //     } 
        // }
        // else{
            if ($request->has('shared_only') && $request->shared_only == 1) {
                $files = FilesShared::where('user_id', $check_user->id)->where('only_file_shared','1');
                $fileIds = $files->pluck('file_id');
                $shared_userids_with_permission_files = $files->pluck('permission_assigned','user_id')->toArray();
                $files = Files::whereIn('id',$fileIds)->get();
                $folders = FoldersShared::where('user_id', $check_user->id);
                $folderids = $folders->pluck('folder_id');
                $shared_userids_with_permission_folders = $folders->pluck('permission_assigned','user_id')->toArray();
                $folders = Folders::whereIn('id', $folderids)->where('parent_folder_id','0')->get();
            }
            else if ($request->has('mine_only') && $request->mine_only == 1) {
                // $files = Files::where('created_by', $check_user->id)->where('folder_id','0')->get();
                // $folders = Folders::where('created_by', $check_user->id)->where('parent_folder_id','0')->get();

                #new file list 
                $myFileIds = Files::select('id')->where('created_by',$check_user->id)->get()->pluck('id')->toArray();
                $myFilesShared = FilesShared::select('file_id')->whereIn('file_id', $myFileIds)->where('shared_by',$check_user->id)->where('user_id','!=',$check_user->id)->get()->pluck('file_id')->toArray();
                $files = Files::query()->whereIn('id',$myFilesShared)->where('folder_id','0')->get();

                // #new folder list 
                $myFolderIds = Folders::select('id')->where('created_by', $check_user->id)->get()->pluck('id')->toArray();
                $myFoldersShared = FoldersShared::select('folder_id')->whereIn('folder_id',$myFolderIds)->where('shared_by',$check_user->id)->where('user_id','!=',$check_user->id)->get()->pluck('folder_id')->toArray();
                $folders = Folders::query()->where('parent_folder_id','0')->whereIn('id',$myFoldersShared)->get();
            }
            else{
                // $files= Files::query()->where('folder_id','0')->get();
                // $folders = Folders::query()->where('parent_folder_id','0')->get();

                #new file list 
                $myFileIds = Files::select('id')->where('created_by',$check_user->id)->get()->pluck('id')->toArray();
                $myFilesShared = FilesShared::select('file_id')->whereIn('file_id', $myFileIds)->where('user_id','!=',$check_user->id)->get()->pluck('file_id')->toArray();
                $notSharedFileIds = array_diff($myFileIds, $myFilesShared);
                $files = Files::query()->whereIn('id',$notSharedFileIds)->where('folder_id','0')->get();

                #new folder list 
                $myFolderIds = Folders::select('id')->where('created_by', $check_user->id)->get()->pluck('id')->toArray();
                $myFoldersShared = FoldersShared::select('folder_id')->whereIn('folder_id',$myFolderIds)->get()->pluck('folder_id')->toArray();
                $notSharedFolderIds = array_diff($myFolderIds, $myFoldersShared);
                $folders = Folders::query()->where('parent_folder_id','0')->whereIn('id',$notSharedFolderIds)->get();
            }
        // }
        if(!empty($folders)){
            foreach($folders as $eachFolder){
                $eachFolder->owner_name = '';
                if($request->has('shared_only') && $request->shared_only == 1){
                    $eachFolder->foldername = $eachFolder->foldername;
                    $eachFolder->created_by = $eachFolder->created_by;
                    $folder_owner = User::select('firstname','lastname')->where('id',$eachFolder->created_by)->first();
                    if($folder_owner)
                        $eachFolder->owner_name = $folder_owner['firstname']. ' '. $folder_owner['lastname'];
                }
                else{
                    $eachFolder->foldername = $eachFolder->foldername;
                    $eachFolder->created_by = $eachFolder->created_by;
                    if($eachFolder->UserDetails)
                        $eachFolder->owner_name = $eachFolder->UserDetails->firstname. ' '. $eachFolder->UserDetails->lastname;
                }
                
                $eachFolder->shared_with = '';
                $eachFolder->type = '';
                $eachFolder->parent_folderid = '';
                $eachFolder->total_rows = '';
                $eachFolder->imported_rows = '';
                $eachFolder->import_status = '';
                $eachFolder->import_start_time = '';
                $eachFolder->import_end_time = '';
                $eachFolder->status = '';
                $eachFolder->note = '';
                $eachFolder->bread_crumbs = '';
                $eachFolder->parent_filename = '';
                if ($eachFolder->parent_folder_id > 0) {
                    $parent_file = Folders::find($eachFolder->parent_folder_id);
                    if (!empty($parent_file)) {
                        $eachFolder->parent_filename = $parent_file->foldername;
                    }
                }
                $eachFolder->document_type = 'folder';
                if($eachFolder->UserDetails){
                    $eachFolder->shared_with = $eachFolder->userDetails->makeHidden(['email_verified_at', 'role', 'status', 'is_loggedin', 'authtoken', 'created_by']);
                }
                    
                unset($eachFolder->FolderDetails);
                unset($eachFolder->userDetails);
                unset($eachFolder->UserDetails);
            }
        }
        if(!empty($files)){
            foreach($files as $eachFile){
                $eachFile->owner_name = '';
                $file_owner = User::select('firstname','lastname')->where('id',$eachFile->created_by)->first();
                if($file_owner)
                    $eachFile->owner_name = $file_owner['firstname']. ' '. $file_owner['lastname'];

                $eachFile->document_type = 'file';
                $shared_users = $eachFile->SharedDetails->pluck('user_id');
                $shared_users_details = User::select('id','firstname','lastname','email','created_at','updated_at')->whereIn('id',$shared_users)->get();
                if($check_user->role == 1){
                    $eachFile->permission = '3';
                }
                else{
                    $filePermission = FilesShared::select('permission_assigned')->where('file_id', $eachFile->id)->where('user_id', $check_user->id)->first();
                    if($filePermission)
                        $eachFile->permission = $filePermission->permission_assigned.'';
                    else
                        $eachFile->permission = '3';
                }
                foreach($shared_users_details as $eachuser){
                    $sharedUserPermission = FilesShared::select('permission_assigned')->where('file_id', $eachFile->id)->where('user_id', $eachuser->id)->first();
                    $eachuser->permission = $sharedUserPermission['permission_assigned'];
                }

                $eachFile->shared_with = $shared_users_details;
                $eachFile->project_name = '';
                if (!empty($eachFile->ProjectDetails)) {
                    $eachFile->project_name = $eachFile->ProjectDetails->project_name;
                }
                if ($eachFile->parent_fileid > 0) {
                    $parent_file = Files::find($eachFile->parent_fileid);
                    if (!empty($parent_file)) {
                        $eachFile->parent_filename = $parent_file->original_filename;
                    }
    
                }
                unset($eachFile->SharedDetails);
                unset($eachFile->ProjectDetails);
            }
        }
        if($files->count() > 0 || $folders->count() > 0 ){
            $tempArr['general'] = $files->toArray();
            $tempFolderArr['folder_list'] = $folders->toArray();
            $all_data = (array_merge($tempArr,$tempFolderArr));
            return ($all_data) ? success($all_data) : error();
        }
        else{
            return success(array());
        }
    }

    public function addnestedfolders(Request $request){

        $check_user = $request->get('current_user');
        $validator = Validator::make($request->all(), [
            'folder' => 'required',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }

        $folders = $request->file('folder');
        $paths = $request->path;
        if(count($paths) > 100){
            return error(Lang::get('validation.custom.files_more_than_100'));
        }
        $foldersWithParentInfo = array();
        $filesWithNameAndId = array();

        $mainParentFolderId = '';
        #main folder name
        $mainFolderName = explode('/', $paths[0])[0];
        $userId = $check_user->id;
        $userFolderPath = env('PERSONAL_FILESPATH') . $userId;
        $userFolderPath = public_path($userFolderPath);
        $mainFolderpath = $userFolderPath.'/'.$mainFolderName;
        if (Folders::where('foldername', $mainFolderName)->where('parent_folder_id',0)->where('created_by',$check_user->id)->exists())
            return error(Lang::get('validation.custom.folder_name_already_exists'));

        if (!file_exists($userFolderPath)) {
            File::makeDirectory($userFolderPath);
        }
        if (!file_exists($mainFolderpath)) {
            File::makeDirectory($mainFolderpath);
            $newFolder = Folders::create(['foldername' => $mainFolderName, 'folderpath' =>$mainFolderName, 'parent_folder_id' => '0', 'created_by' => $check_user->id]);
            $mainParentFolderId = $newFolder->id;
            $foldersWithParentInfo[$mainFolderName] = $newFolder->id;
        }
        else{
            return error(Lang::get('validation.custom.files_folders_already_exists'));
        }

        foreach($paths as $pathKey => $pathValue){
            // echo $pathValue;
            $explodedPath = explode('/',$pathValue);
            $uptoRootFile = array_slice($explodedPath, 0, -1);
            $implodedPath = implode('/',$uptoRootFile);
            $subfolderCount = count($explodedPath) -1;
            $fromRootPath = $mainFolderpath.'/';
            foreach($explodedPath as $explodedPathKey => $explodedPathValue){
                if($explodedPathKey == '0' || $explodedPathKey == $subfolderCount)
                    continue;

                $fromRootPath.= $explodedPathValue.'/';
                $newFolderPath = $explodedPathValue;
                if (!file_exists($fromRootPath)) {
                    File::makeDirectory($fromRootPath);
                    $tempPathKey = explode('/',$pathValue);
                    $tempPathCount = count($tempPathKey);
                    if($tempPathCount > 3){
                        $tempFolderName = $tempPathKey[$tempPathCount - 3];
                        $newSubfolderFolder = Folders::create(['foldername' => $explodedPathValue, 'folderpath'=> $implodedPath, 'parent_folder_id' => $foldersWithParentInfo[$tempFolderName], 'created_by' => $check_user->id]);
                    }
                    else{
                        // echo $pathValue;
                        $newSubfolderFolder = Folders::create(['foldername' => $explodedPathValue, 'folderpath'=> $implodedPath, 'parent_folder_id' => $mainParentFolderId, 'created_by' => $check_user->id]);
                        
                    }
                    if($check_user->role != '1'){
                        FoldersShared::create(['folder_id' => $newSubfolderFolder->id, 'user_id' => $check_user->id, 'shared_by'=> $check_user->id, 'permission_assigned' => '3']);
                    }
                                    
                    // $newSubfolderFolder = Folders::create(['foldername' => $explodedPathValue, 'parent_folder_id' => $mainParentFolderId, 'created_by' => $check_user->id]);
                    $tempPathValue = explode('/',$pathValue);
                    array_pop($tempPathValue);
                    array_shift($tempPathValue);
                    $foldersWithParentInfo[implode('/',$tempPathValue)] = $newSubfolderFolder->id;
                    
                }
            }
        }
        
        if(count($foldersWithParentInfo) > 0){
            foreach($folders as $folderKey => $eachFolder){
                $tempFolderDetails = explode('/',$paths[$folderKey]);
                if(count($tempFolderDetails) > 2)
                    $parentFolderKey = array_slice($tempFolderDetails, 1, -1);
                else
                    $parentFolderKey = array_slice($tempFolderDetails, 0, -1);
                
                $parentFolderKey = implode('/', $parentFolderKey);
                $path = explode('/', $paths[$folderKey]);
                array_pop($path);
                $filePath = implode('/',$path);
                $filename = $paths[$folderKey];
                $fileFullPath = $userFolderPath.'/'.$filePath;
                $allowed_ext = array('xlsx', 'xls', 'txt', 'doc', 'docx', 'pdf', 'jpg', 'jpeg' , 'png', 'csv', 'pptx', 'ppt');
                $uploaded_file = $eachFolder;
                $filesize = $uploaded_file->getSize();
                // $file_size_mb = number_format($filesize / 1048576, 2);
                $filenameWithExt = $uploaded_file->getClientOriginalName();
                $extension = $uploaded_file->getClientOriginalExtension();
                $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME). '.' . $extension;
                $fileNameToStore = pathinfo($filenameWithExt, PATHINFO_FILENAME) . '_' . time() . '.' . $extension;
                // $fileNameToStore = $filename . '_' . time() . '.' . $extension;
                if (!in_array(strtolower($extension), $allowed_ext)) {
                    $this->deleteFoldersAndSubFolders($mainParentFolderId);
                    return error('file type must be from ' . implode(',', $allowed_ext));
                }
                // $fileNameToStore = $filename.'.'.$extension;
                
                $move_file = $uploaded_file->move($fileFullPath, $fileNameToStore);
                $fileCreated = Files::create(['original_filename' => $filename,'filename' => $fileNameToStore, 'folder_id' => $foldersWithParentInfo[$parentFolderKey], 'type' => 'Personal', 'parent_fileid' => '0', 'status' => '1', 'created_by' => $check_user->id, 'is_nested_upload' => '1' ]);
                $filesWithNameAndId[$fileNameToStore] = $fileCreated->id;
                if($check_user->role != '1'){
                    FilesShared::create(['file_id' => $fileCreated->id, 'user_id' => $check_user->id, 'only_file_shared'=> '0', 'permission_assigned' => '3','is_own' => '1']);
                }
            }

            $shared_userids = array();
            if ($request->has('shared_with') && !empty($request->shared_with)) {
                // $sharedUserIds = $request->shared_with;
                $sharedUserIds = removeNonePersimissionUsers($request->shared_with);
                foreach($sharedUserIds as $eachUserId){
                    foreach($foldersWithParentInfo as $eachFolderKey => $eachFolderValue){
                        FoldersShared::create(['folder_id' =>$eachFolderValue, 'user_id' => $eachUserId['user_id'], 'shared_by'=> $check_user->id, 'permission_assigned' => $eachUserId['permission']]);
                    }
                    foreach($filesWithNameAndId as $keyFilesWithNameAndId => $valueFilesWithNameAndId){
                        FilesShared::create(['file_id' =>$valueFilesWithNameAndId, 'user_id' => $eachUserId['user_id'],'permission_assigned' => $eachUserId['permission']]);
                    }

                    $toShareUserDetails = User::where('id', $eachUserId['user_id'])->first();
                    Mail::send('emails.MailFileShared', ['file_owner' =>$check_user->firstname.' '.$check_user->lastname, 'file_name' => $mainFolderName], function ($message) use ($toShareUserDetails) {
                        $message->to($toShareUserDetails['email']);
                        $message->subject('New files/folders Shared with you.');
                    });

                }
            }
            return success([], Lang::get('validation.custom.folder_added_success'));
            
        }
        else{
            return error(Lang::get('validation.custom.files_folders_already_exists'));
        }
        // echo "<pre>";print_r($foldersWithParentInfo);

    }
    public function deleteFoldersAndSubFolders($folderId){
        
        $folderDetails = Folders::where('id',$folderId)->get();
        Folders::where('id',$folderId)->orWhere('parent_folder_id', $folderId)->delete();
        Files::where('folder_id',$folderId)->delete();
        $folderPath = env('PERSONAL_FILESPATH') . $folderDetails[0]->created_by.'/'.$folderDetails[0]->foldername;
        if (file_exists(public_path($folderPath))) {
            File::deleteDirectory(public_path($folderPath));
            return true;
        }
    }

}
