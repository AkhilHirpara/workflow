<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Lang;
use App\Models\Project;
use App\Models\Files;
use App\Models\ImportColumns;
use App\Models\ImportData;
use App\Models\ProjectsUsers;
use App\Models\User;
use App\Models\Investor;
use App\Models\Platform;
use App\Models\Templates;
use App\Models\Questions;
// use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProjectImport;
use App\Models\FilesShared;
use App\Models\Integrity;
use App\Models\Task;
// use Maatwebsite\Excel\HeadingRowImport;
// use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;



use Nikazooz\Simplesheet\HeadingRowImport;
use Nikazooz\Simplesheet\Imports\HeadingRowFormatter;
use Nikazooz\Simplesheet\Concerns\Importable;
use Nikazooz\Simplesheet\Facades\Simplesheet;


use Maatwebsite\Excel\Facades\Excel;


class ProjectController extends Controller
{
    //Import xlsx sheet and copy to folder
    public function importsheet(Request $request)
    {
        $validate_message = array(
            'projectfile.mimes' => 'Project file type must be a : xlsx',
        );
        $validator = Validator::make($request->all(), [
            // 'projectfile' => 'required|file|mimes:xlsx',
            'numberofrows' => 'required|numeric',
        ], $validate_message);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $allwed_ext = array('xlsx');

        $check_user = $request->get('current_user');
    
        $uploaded_file = $request->file('projectfile');
        $filesize = $uploaded_file->getSize();
        $file_size_mb = number_format($filesize / 1048576, 2);
        $filenameWithExt = $uploaded_file->getClientOriginalName();
        $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
        $extension = $uploaded_file->getClientOriginalExtension();
        if (!in_array(strtolower($extension), $allwed_ext)) {
            return error('Project file type must be a : xlsx');
        }
        // $folderName = $filename.'_'.now()->timestamp;
        // $folderPathWithName = public_path() . env('IMPORT_FILESPATH').$folderName;
        // if (! File::exists($folderPathWithName)) {
        //     File::makeDirectory($folderPathWithName);
        // }
        $fileNameToStore = $filename . '_' . time() . '.' . $extension;
        // $movefile = public_path() . env('IMPORT_FILESPATH'). '/' .$folderName.'/';
        $movefile = public_path() . env('IMPORT_FILESPATH');
        $move_file = $uploaded_file->move($movefile, $fileNameToStore);
        if ($move_file) {
            $new_filepath = $movefile . $fileNameToStore;
            $fileurl = env('IMPORT_FILESPATH') . $fileNameToStore;
            $sheet_headers = array();
            HeadingRowFormatter::default('none');
            $headings = (new HeadingRowImport)->toArray($new_filepath);
            if (!empty($headings) && !empty($headings[0][0])) {
                $sheet_headers = $headings[0][0];
            }
            $sheet_headers = array_filter($sheet_headers);
            // if (empty($sheet_headers)) {
            //     // return error(Lang::get('validation.custom.empty_excel_file'));
            //     return error(Lang::get('validation.custom.empty_excel_file_headers'));
            // }
            // $importErrMsg = '';
            // if(!in_array("Account_Reference", $sheet_headers))
            //     $importErrMsg .= Lang::get('validation.custom.account_reference_not_found').'<br />';
            // if(!in_array("Delinquency_Flag", $sheet_headers))
            //     $importErrMsg .= Lang::get('validation.custom.deliquency_flag_not_found').'<br />';
            // if(!in_array("Current_Balance", $sheet_headers))
            //     $importErrMsg .= Lang::get('validation.custom.current_balance_not_found').'<br />';

            // if($importErrMsg !='')
            //     return error('There is a problem with your import, please correct the below issues and try again.<br />'.$importErrMsg);

            if(empty($sheet_headers) || !in_array("Account_Reference", $sheet_headers) || !in_array("Delinquency_Flag", $sheet_headers) || !in_array("Current_Balance", $sheet_headers)){
                return error(Lang::get('validation.custom.import_file_notes'));
            }


            #commented for removing default columns 
            /*
            $required_cols = array('Account_Reference', 'Current_Balance', 'Delinquency_Flag');
            $search_array = array_map('strtolower', $sheet_headers);
            foreach ($required_cols as $recol) {
                if (!in_array(strtolower($recol), $search_array)) {
                    return error(Lang::get('validation.custom.file_missing_reqcols'));
                }
            }*/
            $add_all = array();
            $current_time = currenthumantime();
            $add_file = Files::create(['original_filename' => $filenameWithExt, 'filename' => $fileNameToStore, 'type' => 'Import', 'total_rows' => $request->numberofrows,  'import_status' => 2, 'created_by' => $check_user->id]);

            // $default_selected_headers = array('Delinquency_Flag','Current_Balance','Account_Reference');
            $default_selected_headers = array();
            
            #removed columns from sheet headers for removing default columns 
            // $toBeRemovedColumns = array('Account_Reference', 'Current_Balance', 'Delinquency_Flag');
            // $sheet_headers = array_diff($sheet_headers, $toBeRemovedColumns);

            $header_count = count($sheet_headers);
            $header_counter = 0;
            foreach ($sheet_headers as $single_head) {
                $single_head = trim($single_head);
                // if($single_head == 'Delinquency_Flag' || $single_head == 'Current_Balance' || $single_head == 'Account_Reference')
                //     continue;
                if(in_array($single_head, $default_selected_headers) || ($header_counter <= 2) || ($header_counter >= ($header_count - 3)))
                    if($single_head == 'Delinquency_Flag' || $single_head == 'Current_Balance' || $single_head == 'Account_Reference')
                        $add_all[] = ['file_id' => $add_file->id, 'column_heading' => $single_head, 'status' => '0', 'created_at' => $current_time, 'updated_at' => $current_time];
                    else
                        $add_all[] = ['file_id' => $add_file->id, 'column_heading' => $single_head, 'status' => '1', 'created_at' => $current_time, 'updated_at' => $current_time];
                else
                    $add_all[] = ['file_id' => $add_file->id, 'column_heading' => $single_head, 'status' => '0', 'created_at' => $current_time, 'updated_at' => $current_time];
                $header_counter ++;
            }
            if (!empty($add_all)) {

                $add_many = ImportColumns::insert($add_all);

                // Set Queue Import = NOT USED NOW, Using root readexcelspout/readfile.php using schedular in /app/Console/Kernel.php
                // $new_filepath = '/Websites/www/html/stagingqflow.quadringroup.com/www/api/public/files/projectexcels/Large_file.xlsx';
                // $project_import = new ProjectImport($add_file, $check_user);
                // HeadingRowFormatter::default('none');
                // $set_queue = Simplesheet::import($project_import, $new_filepath);
                // Set Queue Import - End

                addlog('Add', 'Project', Lang::get('validation.logs.projectsheetimport_success', ['sheetname' => $fileNameToStore, 'username' => $check_user->username]), $check_user->id);
                return success($add_file, Lang::get('validation.custom.file_imported'));
            } else {
                $update_files = Files::where('id', $add_file->id)->update(['import_status' => 0]);
                addlog('Add', 'Project', Lang::get('validation.logs.projectsheetimport_failed', ['sheetname' => $fileNameToStore, 'username' => $check_user->username]), $check_user->id);
                return error(Lang::get('validation.custom.empty_excel_file'));
            }
        } else {
            addlog('Add', 'Project', Lang::get('validation.logs.projectsheetimport_failed', ['sheetname' => $fileNameToStore, 'username' => $check_user->username]), $check_user->id);
            return error(Lang::get('validation.custom.file_move_failed', ['filename' => $filenameWithExt]));
        }
    }


    // Add/Edit project 
    public function addeditproject(Request $request)
    {
        $validate_array = array(
            'projectid' => 'sometimes|required|numeric|min:1|exists:App\Models\Project,id',
            'project_name' => 'required|max:255',
            'identifier' => 'required|max:255',
            'investor_id' => 'required|numeric|exists:App\Models\Investor,id',
            'platform_id' => 'required|numeric|exists:App\Models\Platform,id',
            'fileid' => 'required|numeric|exists:App\Models\Files,id',
            'pll_flag' => 'in:0,1'
        );
        $validator = Validator::make($request->all(), $validate_array);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $find_file = Files::find($request->fileid);
        if ($find_file->project_id != '' && !$request->has('projectid')) {
            return error(Lang::get('validation.custom.file_already_used'));
        }
        if ($find_file->project_id != '' && $request->has('projectid') && trim($request->projectid) != '' && $find_file->project_id != $request->projectid) {
            return error(Lang::get('validation.custom.file_already_used'));
        }

        $pll_flag = ($request->has('pll_flag')) ? $request->pll_flag : 0;
        
        if ($request->has('projectid') && trim($request->projectid) != '') {
            $is_exist = Project::where('identifier', $request->identifier)->where('project_name', $request->project_name)->where('id', '!=', $request->projectid)->first();
        } else {
            $is_exist = Project::where('identifier', $request->identifier)->where('project_name', $request->project_name)->first();
        }
        if (empty($is_exist)) {
            $filecompletness = '0';
            if ($request->has('file_completeness') && trim($request->file_completeness) == '1') {
                $filecompletness = '1';
            }
            $check_user = $request->get('current_user');
            if ($request->has('projectid') && trim($request->projectid) != '') {
                //Update Project
                // $is_exist = Project::where('identifier', $request->identifier)->where('project_name', $request->project_name)->where('id', '!=', $request->projectid)->first();
                // echo "<pre>";print_r($is_exist);return;

                $addeditdata = Project::where('id', $request->projectid)->first();
                $status = $this->getstatusfromlastcompletedstep($request,$addeditdata['last_completed_step']);
                $addeditdata->update(['project_name' => $request->project_name, 'identifier' => $request->identifier, 'investor_id' => $request->investor_id, 'platform_id' => $request->platform_id, 'status' => $status, 'file_completeness' => $filecompletness, 'pll_flag'=>$pll_flag]);
                $cur_project = Project::find($request->projectid);
                addlog('Edit', 'Project', Lang::get('validation.logs.projectdetails_success', ['projectname' => $cur_project->project_name, 'username' => $check_user->username]), $check_user->id);
                $success_msg =  Lang::get('validation.custom.project_update_success');
            } else {
                //Add project
                $only_integrity = '0';
                if ($request->has('only_integrity') && trim($request->only_integrity) == '1') {
                    $only_integrity = '1';
                }
                $only_review = '0';
                if ($request->has('only_review') && trim($request->only_review) == '1') {
                    $only_review = '1';
                }
                $addeditdata = Project::create(['project_name' => $request->project_name, 'identifier' => $request->identifier, 'investor_id' => $request->investor_id, 'platform_id' => $request->platform_id, 'percentage_completed' => 0, 'integrity_precentage_completed' => 0, 'status' => 3, 'last_completed_step' => 2, 'created_by' => $check_user->id, 'file_completeness' => $filecompletness, 'only_integrity' => $only_integrity, 'only_review' => $only_review, 'pll_flag'=>$pll_flag]);
                $cur_project = Project::find($addeditdata->id);
                addlog('Add', 'Project', Lang::get('validation.logs.projectdetails_success', ['projectname' => $cur_project->project_name, 'username' => $check_user->username]), $check_user->id);
                $success_msg =  Lang::get('validation.custom.project_add_success');
            }
            if (!empty($cur_project)) {
                $update_files = Files::where('id', $request->fileid)->update(['project_id' => $cur_project->id]);
                if (!$update_files) {
                    addlog('Add', 'Project', Lang::get('validation.logs.projectdetails_failed', ['projectname' => $request->project_name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.project_add_failed'));
                }
                return success($cur_project, $success_msg);
            } else {
                addlog('Add', 'Project', Lang::get('validation.logs.projectdetails_failed', ['projectname' => $request->project_name, 'username' => $check_user->username]), $check_user->id);
                return error(Lang::get('validation.custom.project_add_failed'));
            }
        } else {
            if ($is_exist->status == 0)
                return error(Lang::get('validation.custom.project_already_deleted'));
            else
                return error(Lang::get('validation.custom.project__name_identifier_already_exist'));
        }
    }

    //get status from last completed step of project
    public function getstatusfromlastcompletedstep($request, $last_completed_step){
        if ($request->has('mark_complete') && trim($request->mark_complete) != '') {
            if($request->mark_complete == '0')
                if($last_completed_step == '5')
                    return '1';
                else
                    return '3';
            else if($request->mark_complete == '1')
                return '2';
        }
    }

    //Get sheet headers
    public function getsheetheaders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'projectid' => 'required|numeric|exists:App\Models\Project,id',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $find_file = Files::where('project_id', $request->projectid)->where('type', 'Import')->first();
        if (!empty($find_file)) {
            if ($find_file->import_status == 1) {
                //Import Done
                $sheet_columns = ImportColumns::where('project_id', $request->projectid)->get();
                if ($sheet_columns->count()) {
                    return success($sheet_columns);
                } else {
                    $find_row = ImportData::where('file_id', $find_file->id)->first();
                    $find_cols = ImportColumns::where('file_id', $find_file->id)->first();
                    if (!empty($find_cols) && empty($find_row)) {

                        Schema::disableForeignKeyConstraints();
                        ImportColumns::where('project_id', $request->projectid)->delete();
                        Project::where('id', $request->projectid)->delete();
                        Files::where('project_id', $request->projectid)->delete();
                        ImportData::where('file_id', $find_file->id)->delete();
                        Schema::enableForeignKeyConstraints();

                        return error(Lang::get('validation.custom.empty_excel_file'), "Failed");
                    }
                    if (!empty($find_row)) {
                        if (!empty($find_cols)) {
                            $is_data_existt = ImportColumns::where('file_id', $find_file->id)->where('project_id', $request->projectid)->first();
                            if (empty($is_data_existt)) {
                                $update_columns = ImportColumns::where('file_id', $find_file->id)->update(['project_id' => $request->projectid]);
                            }
                        } else {
                            $get_row = $find_row->toArray();
                            if (empty($get_row['row_details'])) {
                                Schema::disableForeignKeyConstraints();
                                ImportColumns::where('project_id', $request->projectid)->delete();
                                Project::where('id', $request->projectid)->delete();
                                Files::where('project_id', $request->projectid)->delete();
                                ImportData::where('file_id', $find_file->id)->delete();
                                Schema::enableForeignKeyConstraints();
                                return error(Lang::get('validation.custom.empty_excel_headers'), "Failed");
                            }
                            // return $get_row;
                            $sheet_headers = array_filter(array_keys((array)$get_row['row_details']));
                            $sheet_headers = array_map(function ($value) {
                                return str_replace(' ', '_', $value);
                            }, $sheet_headers);
                            $required_cols = array('Account_Reference', 'Current_Balance', 'Delinquency_Flag');
                            $search_array = array_map('strtolower', $sheet_headers);
                            foreach ($required_cols as $recol) {
                                if (!in_array(strtolower($recol), $search_array)) {

                                    Schema::disableForeignKeyConstraints();
                                    ImportColumns::where('project_id', $request->projectid)->delete();
                                    Project::where('id', $request->projectid)->delete();
                                    Files::where('project_id', $request->projectid)->delete();
                                    ImportData::where('file_id', $find_file->id)->delete();
                                    Schema::enableForeignKeyConstraints();

                                    return error(Lang::get('validation.custom.file_import_failed_reqcols'), "Failed");
                                }
                            }
                            $add_all = array();
                            $current_time = currenthumantime();
                            foreach ($sheet_headers as $single_head) {
                                $add_all[] = ['file_id' => $find_file->id, 'project_id' => $request->projectid, 'column_heading' => $single_head, 'created_at' => $current_time, 'updated_at' => $current_time];
                            }
                            if (!empty($add_all)) {
                                $add_many = ImportColumns::insert($add_all);
                            }
                        }

                        $is_data_exist = ImportData::where('file_id', $find_file->id)->where('project_id', $request->projectid)->first();
                        if (empty($is_data_exist)) {
                            $update_rows = ImportData::where('file_id', $find_file->id)->update(['project_id' => $request->projectid]);
                        }
                        $sheet_columns = ImportColumns::where('project_id', $request->projectid)->get();
                        if ($sheet_columns->count()) {
                            return success($sheet_columns);
                        } else {
                            return error(Lang::get('validation.custom.invalid_projectid'));
                        }
                    }
                }
            } elseif ($find_file->import_status == 2) {
                //Pending
                $extra_data = array('percentage' => 0.00);
                if ($find_file->total_rows > 0) {
                    // $get_imported = ImportData::where('file_id', $find_file->id)->get();
                    // $total_imported = $get_imported->count();
                    $total_imported = $find_file->imported_rows;
                    if ($total_imported > 0) {
                        $totalrows = $find_file->total_rows;
                        $percent = $total_imported / $totalrows;
                        $imported_percent = number_format($percent * 100, 2);
                        $extra_data = array('percentage' => $imported_percent);
                        if ($total_imported >= $totalrows) {
                            // $find_file->import_status = 1;
                            // $find_file->import_end_time = time();
                            // $find_file->save();
                            $extra_data = array('percentage' => 98.00);
                        }
                        return error(Lang::get('validation.custom.file_import_pending'), "Pending", $extra_data);
                    } else {
                        return error(Lang::get('validation.custom.file_import_pending'), "Pending", $extra_data);
                    }
                } else {
                    return error(Lang::get('validation.custom.file_import_pending'), "Pending", $extra_data);
                }
            } else {
                //Falied
                Schema::disableForeignKeyConstraints();
                ImportColumns::where('project_id', $request->projectid)->delete();
                Project::where('id', $request->projectid)->delete();
                Files::where('project_id', $request->projectid)->delete();
                ImportData::where('file_id', $find_file->id)->delete();
                Schema::enableForeignKeyConstraints();

                return error(Lang::get('validation.custom.file_import_failed'), "Failed");
            }
        } else {
            return error(Lang::get('validation.custom.invalid_projectid'));
        }
    }

    //update status for Include/Exclude of columns
    public function managesheetheaders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'projectid' => 'required|numeric|exists:App\Models\Project,id',
            'headerids' => 'required|array',
            // 'review_header_ids' => 'required|array',
            // 'review_header_ids' => 'required_if:only_integrity,1',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $colids = $request->headerids;
        $colids = array_map('intval', $colids);
        if(!$request->only_integrity){
            $review_header_ids = $request->review_header_ids;
            $review_header_ids = array_map('intval', $review_header_ids);    
        }
        
        $alreadyActiveHeaderIds = ImportColumns::select('id')->where('project_id', $request->projectid)
            ->where(function($q) {
                $q->where('status', 1)
                ->orWhere('column_heading','Account_Reference')
                ->orWhere('column_heading','Current_Balance')
                ->orWhere('column_heading','Delinquency_Flag');
            })
        ->get()->pluck('id')->toArray();

        sort($alreadyActiveHeaderIds);
        sort($colids);

        $implodedActiveArr = implode(',', array_values($alreadyActiveHeaderIds));
        $implodedColIds = implode(',', array_values($colids));
        // echo "<pre>";print_r(count($alreadyActiveHeaderIds));
        // echo "<pre>";print_r(count($colids));
        // return;
        if (((count($alreadyActiveHeaderIds) == count($colids)) && $implodedActiveArr != $implodedColIds) || count($alreadyActiveHeaderIds) < count($colids) ){
            // echo "errr";return;
            Integrity::where('project_id', $request->projectid)
            ->where('status', '2')
            ->update([
                'status' => '3',
                'last_review_status' => '3'
            ]);

            ImportData::where('project_id', $request->projectid)
            ->where('integrity_status', '2')
            ->update([
                'integrity_status' => '3'
            ]);

            Project::where('id', $request->projectid)
            ->update([
                'integrity_precentage_completed' => 0
            ]);
        }

        $update_data = ImportColumns::where('project_id', $request->projectid)->update(['status' => 1]);
        $update_data = ImportColumns::where('project_id', $request->projectid)->whereNotIn('id', $colids)->update(['status' => 0]);
        if(!$request->only_integrity){
            ImportColumns::where('project_id', $request->projectid)->update(['review_status' => 0]);
            $update_review_cols = ImportColumns::where('project_id', $request->projectid)->whereIn('id', $review_header_ids)->update(['review_status' => 1]);
        }
        #disable columns - Account_Reference, Current_Balance, Delinquency_Flag
        ImportColumns::whereIn('column_heading',array('Account_Reference','Current_Balance','Delinquency_Flag'))->update(['status' => 0]);

        if(count($colids) < count($alreadyActiveHeaderIds)){
            $activeheadersDb = ImportColumns::select('column_heading')->where('project_id', $request->projectid)->where('status','1')->get()->pluck('column_heading')->toArray();
            $integratedRecords = Integrity::select('id', 'row_id', 'answers')->where('project_id', $request->projectid)
                // ->where('status','2')
                ->where(function($q) {
                      $q->where('status', 2)
                        ->orWhere('status', 3);
                  })
                ->get()->toArray();
            $allData = array();
            foreach ($integratedRecords as $eachIntegratedrecord) {
                $decodedAnswers = json_decode($eachIntegratedrecord['answers']);
                $newAnswers = array();
                $answerCounter = 0;
                foreach($decodedAnswers as $eachDecodedAnswer){
                    if(in_array($eachDecodedAnswer->column_name, $activeheadersDb) && ($eachDecodedAnswer->status == 'Match' || ($eachDecodedAnswer->status == 'No Match'  && $eachDecodedAnswer->comment != ''))){
                        array_push($newAnswers, $eachDecodedAnswer);
                        $answerCounter++;
                    }
                }
                Integrity::where('row_id',$eachIntegratedrecord['row_id'])->update(['answers' => $newAnswers]);
                if(count($colids) == ($answerCounter+3)){
                    Integrity::where('row_id',$eachIntegratedrecord['row_id'])->where('status','3')->update(['status'=> '2','last_review_status'=> '2']);
                    ImportData::where('id',$eachIntegratedrecord['row_id'])->where('project_id', $request->projectid)->where('integrity_status','3')->update(['integrity_status'=> '2']);
                    $completedPercent = Integrity::where('status','2')->where('project_id',$request->projectid)->get();
                    $totalRows = Files::select('imported_rows')->where('project_id',$request->projectid)->get();
                    if (count($completedPercent) > 0) {
                        $find_project = Project::find($request->projectid);
                        $countCompletedPercent = count($completedPercent);
                        $totalRowsCount = ($totalRows[0]->imported_rows == 1)? 1 : ($totalRows[0]->imported_rows - 1);
                        // $percentCompleted = ($countCompletedPercent * 100)/$totalRows[0]->imported_rows;
                        $percentCompleted = ($countCompletedPercent * 100)/$totalRowsCount;
                        $find_project->integrity_precentage_completed = $percentCompleted;
                        $find_project->save();
                    }
                    else{
                        $find_project->integrity_precentage_completed = '0';
                        $find_project->save();
                    }
                }
                else{
                    Integrity::where('id',$eachIntegratedrecord['row_id'])->update(['answers' => $newAnswers]);
                }
                
            }
        }

        $find_data = Project::find($request->projectid);
        if ($find_data->last_completed_step == 2) {
            if($find_data->only_integrity)
                $find_data->last_completed_step = 4;
            else
                $find_data->last_completed_step = 3;
            $find_data->save();
        }
        $check_user = $request->get('current_user');

        //import sheet data when user edits columns,after project create completed-Removed in new logic
        // if ($find_data->last_completed_step == 5) {
        //     $import_data = $this->importsheetdata($request->projectid);
        //     if ($import_data != 1) {
        //         addlog('Edit', 'Project', Lang::get('validation.logs.projectdataimport_failed', ['projectname' => $find_data->project_name, 'username' => $check_user->username]), $check_user->id);
        //         return $import_data;
        //     } else {
        //         addlog('Edit', 'Project', Lang::get('validation.logs.projectdataimport_success', ['projectname' => $find_data->project_name, 'username' => $check_user->username]), $check_user->id);
        //     }
        // }
        //End - import sheet data when user edits columns,after project create completed
        if ($find_data->last_completed_step == 5) {
            addlog('Edit', 'Project', Lang::get('validation.logs.projectdata_success', ['projectname' => $find_data->project_name, 'username' => $check_user->username]), $check_user->id);
        } else {
            addlog('Add', 'Project', Lang::get('validation.logs.projectdata_success', ['projectname' => $find_data->project_name, 'username' => $check_user->username]), $check_user->id);
        }
        $activecolumns = ImportColumns::where('project_id', $request->projectid)->where('status', 1)->get();
        return success($activecolumns, Lang::get('validation.custom.project_column_success'));
        // } else {
        //     return error();
        // }
    }


    //Add Template and assign questions
    public function addtemplatequestions(Request $request)
    {
        $validate_array = array(
            'templateid' => 'sometimes|required|numeric|min:1|exists:App\Models\Templates,id',
            'projectid' => 'required|numeric|exists:App\Models\Project,id',
            'template_name' => 'required|max:255',
            'questionids' => 'required|array',
        );
        $validator = Validator::make($request->all(), $validate_array);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        if ($request->has('templateid') && trim($request->templateid) != ''){
            $is_exist = Templates::where('name', $request->template_name)->where('id', '!=', $request->templateid)->first();
        }
        else{
            $is_exist = Templates::where('name', $request->template_name)->first();

        }
        if (empty($is_exist)) {
            $check_user = $request->get('current_user');
            if ($request->has('templateid') && trim($request->templateid) != '') {
                $cur_template = Templates::find($request->templateid);
                $cur_template->name = $request->template_name;
                $cur_template->save();
            } else {
                $add_data = Templates::create(['name' => $request->template_name, 'status' => 1, 'created_by' => $check_user->id]);
                if ($add_data) {
                    addlog('Add', 'Template', Lang::get('validation.logs.templateadd_success', ['template' => $request->template_name, 'username' => $check_user->username]), $check_user->id);
                } else {
                    addlog('Add', 'Template', Lang::get('validation.logs.templateadd_failed', ['template' => $request->template_name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.template_add_failed'));
                }
                $cur_template = Templates::find($add_data->id);
            }
            if (!empty($cur_template)) {
                $past_questions = $cur_template->relatedAllQuestions;
                if (!empty($past_questions)) {
                    $pastquestions = $past_questions->pluck('id')->toArray();
                    $questionsDifference = array_diff($past_questions->pluck('id')->toArray(),$request->questionids);
                    $deletedQuestions = array_diff($request->questionids,$past_questions->pluck('id')->toArray());
                    
                    // echo count($questionsDifference)."<br>";echo "<pre>";print_r($deletedQuestions);return;
                    if(count($questionsDifference) > 0 && !empty($deletedQuestions)){
                        Task::where('project_id',$request->projectid)->where('status','2')->update(['status' => '3','last_review_status'=>'3']);
                        ImportData::where('project_id',$request->projectid)->where('task_status','2')->update(['task_status' => '3']);
                    }
                    if (!$request->has('templateid'))
                        Task::where('project_id',$request->projectid)->update(['status' => '0']);

                    #
                    $alreadyActiveQuestionsids = Questions::select('id')->where('template_id',$request->templateid)->where('status','1')->get()->pluck('id')->toArray();
                    $questionIds = $request->questionids;
                    $implodedActiveArr = implode(',', array_values($questionIds));
                    $implodedColIds = implode(',', array_values($alreadyActiveQuestionsids));
                    if(count($alreadyActiveQuestionsids) > count($questionIds)){
                        $allTasks = Task::select('row_id','answers')->where('project_id',$request->projectid)->where('status','3')->get();
                        foreach($allTasks as $eachTaskDetails){
                            $decodedAns = json_decode($eachTaskDetails->answers);
                            $tempFlag = '1';
                            // echo "<pre>";print_r($alreadyActiveQuestionsids);
                            // echo "<pre>";print_r($questionIds);
                            foreach($decodedAns as $eachDecodedAns){
                                // echo "<pre>";print_r($eachDecodedAns);
                                if(in_array($eachDecodedAns->questionid, $alreadyActiveQuestionsids) && $eachDecodedAns->selected_choice != '' && ($eachDecodedAns->comment != '' && !empty($eachDecodedAns->comment)) && ($eachDecodedAns->comment == '' || empty($eachDecodedAns->comment))){
                                    // $tempFlag='0';
                                    // Task::where('project_id',$request->projectid)->where('row_id',$eachTaskDetails->row_id)->update(['status'=>'2','last_review_status'=>'2']);
                                    // ImportData::where('project_id',$request->projectid)->where('id',$eachTaskDetails->row_id)->update(['task_status'=>'2']);
                                }
                                // if($eachDecodedAns->comment == '' || empty($eachDecodedAns->comment)){
                                //     $tempFlag='0';
                                // }

                                
                                // echo "questionid - ".$eachDecodedAns->questionid."<br>";
                                // echo "selected_choice - ".$eachDecodedAns->selected_choice."<br>";
                                // echo "comment - ".$eachDecodedAns->comment."<br>";
                                // return;
                                

                                if(($eachDecodedAns->comment == '' || empty($eachDecodedAns->comment)) || !in_array($eachDecodedAns->questionid, $questionIds) || $eachDecodedAns->selected_choice == '' || $eachDecodedAns->comment == ''){
                                    $tempFlag='0';
                                }

                            }
                            if($tempFlag){
                                Task::where('project_id',$request->projectid)->where('row_id',$eachTaskDetails->row_id)->update(['status'=>'2','last_review_status'=>'2']);
                                ImportData::where('project_id',$request->projectid)->where('id',$eachTaskDetails->row_id)->update(['task_status'=>'2']);
                            }                            
                        }
                        // return;
                    }
                    else if(count($alreadyActiveQuestionsids) < count($questionIds)){
                        Task::where('project_id',$request->projectid)->where('status','2')->update(['status' => '3','last_review_status'=>'3']);
                        ImportData::where('project_id',$request->projectid)->where('task_status','2')->update(['task_status' => '3']);
                    }
                    #

                    foreach ($pastquestions as $sq) {
                        if (!in_array($sq, $request->questionids)) {
                            $check_q = Questions::find($sq);
                            $check_q->status = 0;
                            $check_q->save();
                        }
                    }

                    

                }
                $add_all = array();
                $current_time = currenthumantime();
                $cur_project = Project::find($request->projectid);
                $cur_project->template_id = $cur_template->id;
                if ($cur_project->last_completed_step == 3) {
                    $cur_project->last_completed_step = 4;
                }
                #
                $count_task = Task::where('project_id', $cur_project->id)->where('status', 2)->count();
                if ($count_task > 0) {
                    $find_file = Files::where('project_id', $cur_project->id)->where('type', 'Import')->first();
                    $total_imported = $find_file->imported_rows;
                    if ($total_imported > 0) {
                        $project_percent = number_format(($count_task / ($total_imported - 1)) * 100, 2);
                        $cur_project->percentage_completed = $project_percent;
                    }
                }
                else{
                    $cur_project->percentage_completed = '0';
                }
                #

                $cur_project->save();
                $add_all = array();
                $current_time = currenthumantime();
                foreach ($request->questionids as $qid) {
                    if (is_numeric($qid)) {
                        $cur_question = Questions::find($qid);
                        if (!empty($cur_question) && ($cur_question->template_id != $cur_template->id)) {
                            $is_exist = Questions::where('question', '=', $cur_question->question)->where('template_id', $cur_template->id)->where('status', 1)->first();
                            if (empty($is_exist)) {
                                $add_all[] = array(
                                    'category' => $cur_question->category,
                                    'export_heading' => $cur_question->export_heading,
                                    'comment_required' => $cur_question->comment_required,
                                    'question' => $cur_question->question,
                                    'choices' => json_encode($cur_question->choices),
                                    'status' => 1,
                                    'template_id' => $cur_template->id,
                                    'created_by' => $check_user->id,
                                    'created_at' => $current_time,
                                    'updated_at' => $current_time,
                                );
                            }
                        }
                    }
                }
                if (!empty($add_all)) {

                    $add_many = Questions::insert($add_all);
                }
                // $cur_template = Templates::find($cur_template->id);
                addlog('Edit', 'Project', Lang::get('validation.logs.projectquestionassign_success', ['templatename' => $cur_template->name, 'projectname' => $cur_project->project_name, 'username' => $check_user->username]), $check_user->id);
                return success($cur_template, Lang::get('validation.custom.template_question_assigned'));
            } else {
                return error(Lang::get('validation.custom.invalid_templateid'));
            }
        } else {
            if ($is_exist->status == 0)
                return error(Lang::get('validation.custom.template_already_deleted'));
            else
                return error(Lang::get('validation.custom.template_already_exist'));
        }
    }


    // Assign users to project
    public function assignusers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'projectid' => 'required|numeric|min:1|exists:App\Models\Project,id',
            'userids' => 'required|array',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $find_data = Project::find($request->projectid);
        $check_user = $request->get('current_user');
        $already_assigned = $find_data->relatedUsers->pluck('user_id')->toArray();
        $userids = array_unique($request->userids);
        $add_all = array();
        $current_time = currenthumantime();
        foreach ($userids as $uid) {
            if (is_numeric($uid)) {
                $find_user = User::find($uid);
                if (!empty($find_user) && !in_array($uid, $already_assigned)) {
                    $add_all[] = ['project_id' => $find_data->id, 'user_id' => $find_user->id, 'created_at' => $current_time, 'updated_at' => $current_time];
                }
            }
        }
        foreach ($already_assigned as $sid) {
            if (!in_array($sid, $userids)) {
                ProjectsUsers::where('project_id', $find_data->id)->where('user_id', $sid)->delete();
            }
        }
        if (!empty($add_all)) {
            $add_many = ProjectsUsers::insert($add_all);
        }

        //import sheet data only after users are assigned when project is created for first time-Removed in new logic
        // if ($find_data->last_completed_step != 5) {
        //     $find_data->last_completed_step = 5;
        //     $find_data->status = 1;
        //     $import_data = $this->importsheetdata($request->projectid);
        //     if ($import_data != 1) {
        //         addlog('Edit', 'Project', Lang::get('validation.logs.projectdataimport_failed', ['projectname' => $find_data->project_name, 'username' => $check_user->username]), $check_user->id);
        //         return $import_data;
        //     } else {
        //         addlog('Edit', 'Project', Lang::get('validation.logs.projectdataimport_success', ['projectname' => $find_data->project_name, 'username' => $check_user->username]), $check_user->id);
        //     }
        // }
        //End - import sheet data only after users are assigned when project is created for first time
        if ($find_data->last_completed_step != 5) {
            $find_data->last_completed_step = 5;
            $find_data->status = 1;
        }
        if ($find_data->save()) {
            addlog('Edit', 'Project', Lang::get('validation.logs.projectuserassigned_success', ['projectname' => $find_data->project_name, 'username' => $check_user->username]), $check_user->id);
            return success($find_data, Lang::get('validation.custom.project_users_assigned_success'));
        } else {
            addlog('Add', 'Project', Lang::get('validation.logs.projectuserassigned_failed', ['projectname' => $find_data->project_name, 'username' => $check_user->username]), $check_user->id);
            return success($find_data, Lang::get('validation.custom.project_users_assigned_failed'));
        }
    }

    //import all data from file based on file- Common function,not for direct url access-Removed in new logic
    public function importsheetdata($projectid)
    // public function importsheetdata(Request $request)
    {
        // $projectid = $request->projectid;
        $find_file = Files::where('project_id', $projectid)->where('type', 'Import')->first();
        if (empty($find_file)) {
            return error(Lang::get('validation.custom.file_not_found'));
        } else {
            $sheet_headers = array();
            $all_columns = ImportColumns::where('file_id', $find_file->id)->get()->toArray();
            $filedirpath = public_path() . env('IMPORT_FILESPATH') . $find_file->filename;
            $start_time = time();

            // if (!empty($collection)) {
            // $rows = Excel::toArray(new ProjectImport, $filedirpath);
            $final_data = array();
            // if (!empty($all_columns) && !empty($sheet_headers)) {
            if (!empty($all_columns)) {
                $col_ids = array_column($all_columns, 'id');
                $col_status_array = array_combine($col_ids, array_column($all_columns, 'status'));
                $only_activecols = array_keys(array_filter($col_status_array));
                $collection = (new FastExcel)->import($filedirpath);
                $sheet_data = $collection;
                foreach ($sheet_data as $single_row) {
                    $temp_row = array();
                    foreach ($single_row as $srow) {
                        if (is_a($srow, 'DateTime'))
                            $srow = $srow->format('Y-m-d H:i:s');
                        $temp_row[] = $srow;
                    }
                    $final_data[] = array_combine($col_ids, $temp_row);
                }
                if (!empty($final_data)) {
                    // unset($final_data[0]);
                    ImportData::where('project_id', $projectid)->delete();
                    $add_all = array();
                    $current_time = currenthumantime();
                    foreach ($final_data as $sdata) {
                        foreach ($sdata as $colid => $colvalue) {
                            if (!in_array($colid, $only_activecols))
                                unset($sdata[$colid]);
                        }
                        $add_all[] = ['project_id' => $projectid, 'row_details' => json_encode($sdata), 'created_at' => $current_time, 'updated_at' => $current_time];
                    }
                    if (!empty($add_all)) {
                        $small_arr = array_chunk($add_all, 20000);
                        foreach ($small_arr as $sarr) {
                            $add_many = ImportData::insert($sarr);
                        }
                        $end_time = time();
                        $totalSecondsDiff = abs($start_time - $end_time);
                        Project::where('id', $projectid)->update(['import_time_taken' => $totalSecondsDiff]);
                        if ($add_many)
                            return 1;
                    }
                } else {
                    return error(Lang::get('validation.custom.empty_excel_file'));
                }
            } else {
                return error(Lang::get('validation.custom.empty_excel_file'));
            }
        }
    }


    // Get all projects
    public function allprojects(Request $request)
    {

        $check_user = $request->get('current_user');
        $getdata = Project::query()
        ->orderBy('id', 'desc');

        if ($check_user->role == 2) {
            //For standard users
            // $getdata = $getdata->where('created_by', $check_user->id);
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

        //ignore archived projects
        $getdata->where('is_archived', 0);

        $all_data = $getdata->get();
        if (!empty($all_data)) {
            foreach ($all_data as $sdata) {
                $sdata->import_status = 0;
                $sdata->imported_rows = 0;
                $find_data = Files::where('project_id', $sdata->id)->where('type', 'Import')->first();
                if (!empty($find_data)) {
                    $sdata->import_status = $find_data->import_status;
                    if ($find_data->imported_rows > 1)
                        $sdata->imported_rows = $find_data->imported_rows - 1;
                }
                $find_data = User::find($sdata->created_by);
                if (!empty($find_data))
                    $sdata->created_user = $find_data->firstname . ' ' . $find_data->lastname;
                else
                    $sdata->created_user = '';
            }
        }

        foreach ($all_data as $k => $pdata) 
        {
            $totalimportdata = ImportData::select(DB::raw('SUM(CASE WHEN integrity_status = 2 THEN 1 ELSE 0 END) as integrity_statustotal'),DB::raw('SUM(CASE WHEN task_status = 2 THEN 1 ELSE 0 END) as task_statustotal'))
                ->where('project_id', $pdata->id)->first();

            if ($totalimportdata->count() > 0) {
                // Add integrity_statustotal to the current project
                $all_data[$k]['integrity_statustotal'] = (int) $totalimportdata->integrity_statustotal;
                $all_data[$k]['task_statustotal'] = (int) $totalimportdata->task_statustotal;
            } 
        }

        return ($all_data) ? success($all_data) : error();
    }

    // Get single project details
    public function viewproject(Request $request, $projectid)
    {
        $find_data = Project::find($projectid);
        if (!empty($find_data)) {
            $check_user = $request->get('current_user');
            $final_details = $find_data;
            $find_user = User::find($find_data->created_by);
            if (!empty($find_user))
                $final_details->created_user = $find_user->firstname . ' ' . $find_user->lastname;
            else
                $final_details->created_user = '';

            #review details
            $find_task = Task::where('project_id', $projectid)->where('status', 2)->count();
            $final_details->completed_rows = 0;
            if ($find_task > 0)
                $final_details->completed_rows = $find_task;
            $find_task1 = Task::where('project_id', $projectid)->where('status', 2)->where('record_owner', $check_user->id)->count();
            $final_details->completed_by_currentuser = 0;
            if ($find_task1 > 0)
                $final_details->completed_by_currentuser = $find_task1;

            #integrity details
            $integrity_completed = Integrity::where('project_id', $projectid)->where('status', 2)->count();
            $final_details->completed_integrity = 0;
            if ($integrity_completed > 0)
                $final_details->completed_integrity = $integrity_completed;
            $integrity_completed_by_current_user = Integrity::where('project_id', $projectid)->where('status', 2)->where('record_owner', $check_user->id)->count();
            $final_details->integrity_completed_by_current_user = 0;
            if($integrity_completed_by_current_user > 0)
                $final_details->integrity_completed_by_current_user = $integrity_completed_by_current_user;

            $all_questions = array();
            if (!empty($find_data->relatedQuestions)) {
                $final_details->relatedquestions = $find_data->relatedQuestions;
            } else {
                $final_details->relatedquestions = [];
            }
            $assigned_users = $find_data->relatedUsers->pluck('user_id')->toArray();
            if (!empty($assigned_users)) {
                $as_users = User::whereIn('id', $assigned_users)->get();
                $as_users->makeHidden(['authtoken']);
                foreach ($as_users as $suser) {
                    $find_task2 = Task::where('project_id', $projectid)->where('status', 2)->where('record_owner', $suser->id)->count();
                    $suser->completed_tasks = 0;
                    if ($find_task2 > 0)
                        $suser->completed_tasks = $find_task2;

                    #integrity
                    $integrity_completed_by_user = Integrity::where('project_id', $projectid)->where('status', 2)->where('record_owner', $suser->id)->count();
                    $suser->integrity_completed_by_user = 0;
                    if ($integrity_completed_by_user > 0)
                        $suser->integrity_completed_by_user = $integrity_completed_by_user;
                }
                $final_details->assignedusers = $as_users;
            } else {
                $final_details->assignedusers = [];
            }
            $final_details->templatedetails = $find_data->templatedetails;
            $final_details->projectfile = $find_data->projectfile;
            // $final_details->projectfile->total_rows = $find_data->projectfile->total_rows - 1;
            $file_exists_msg = '';
            if ($final_details->projectfile)
            {
                $final_details->projectfile->total_rows = ($find_data->projectfile->imported_rows > 0)?$find_data->projectfile->imported_rows - 1:0;
            }
            else{
                $file_exists_msg = Lang::get('validation.custom.file_is_deleted');
            }
            
            $all_filecols = $find_data->relatedFileColumns;
            $activeheaders = array();
            if (!empty($all_filecols)) {
                foreach ($all_filecols as $scol) {
                    if ($scol->status == 1) {
                        $activeheaders[] = $scol->id;
                    }
                }
            }

            $defaultHeaderIds = ImportColumns::select('id')->where('project_id',$projectid)->whereIn('column_heading',array('Account_Reference','Current_Balance','Delinquency_Flag'))->get()->pluck('id');
            foreach($defaultHeaderIds as $eachId){
                $activeheaders[] = $eachId;
            }

            $activeReviewHeaders = ImportColumns::select('id')->where('project_id',$projectid)->where('review_status','1')->get()->pluck('id');

            $final_details->file_msg = $file_exists_msg;
            $final_details->activeheaderids = $activeheaders;
            $final_details->activereviewheaderids = $activeReviewHeaders;
            $final_details->investordetails = $find_data->investordetails;
            $final_details->platformdetails = $find_data->platformdetails;
            $final_details->investordetails = Investor::find($find_data->investor_id);
            $final_details->platformdetails = Platform::find($find_data->platform_id);

            unset($final_details->relatedUsers);
            unset($final_details->relatedQuestions);
            unset($final_details->relatedFileColumns);
            return success($final_details);
        } else {
            return error(Lang::get('validation.custom.invalid_projectid'));
        }
    }

    // Delete Project- Change Project status to 0
    public function deleteproject(Request $request)
    {
        $check_user = $request->get('current_user');
        $find_data = Project::find($request->project_id);
        if (!empty($find_data)) {
            if ($request->has('permanentDelete') && $request->permanentDelete == 1) {
                // permenant delete
                $projectId = $request->project_id;
                $filedata = Files::select('id','filename')->where('project_id', $projectId)->get();
                $fileids = $filedata->pluck('id');
                $filenames = $filedata->pluck('filename')->implode(', ');
                if(count($fileids) > 0){
                    // files_shared
                    FilesShared::whereIn('file_id', $fileids)->delete();
                    // files
                    if(Files::whereIn('id', $fileids)->delete()){
                        addlog('Delete', 'Files', Lang::get('validation.logs.projectfilesdeleted_success', ['name' => $filenames, 'username' => $check_user->username]), $check_user->id);
                    }
                    else{
                        addlog('Delete', 'Files', Lang::get('validation.logs.projectfilesdeleted_failed', ['name' => $filenames, 'username' => $check_user->username]), $check_user->id);
                    }
                }
                // import_columns
                // ImportColumns::where('project_id', $projectId)->delete();
                // import_data
                ImportData::where('project_id', $projectId)->delete();
                // integrity
                Integrity::where('project_id', $projectId)->delete();
                // task
                Task::where('project_id',$projectId)->delete();
                // projects_users
                ProjectsUsers::where('project_id', $projectId)->delete();
                // projects
                $projectdata = Project::where('id', $projectId)->get();
                Project::where('id', $projectId)->delete();
                if($projectdata){
                    addlog('Delete', 'Project', Lang::get('validation.logs.projectdelete_success', ['name' => $projectdata[0]->project_name, 'username' => $check_user->username]), $check_user->id);
                    return success([], Lang::get('validation.custom.permanent_delete_success', ['module' => 'Project', 'name' => $projectdata[0]->project_name]));
                }
                else{
                    addlog('Delete', 'Project', Lang::get('validation.logs.projectdelete_failed', ['name' => $projectdata[0]->project_name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.permanent_delete_failed', ['module' => 'Project', 'name' => $projectdata[0]->project_name]));
                }
                
            }
            else{
                // soft delete
                $find_data->status = 0;
                if ($find_data->save()) {
                    addlog('Delete', 'Project', Lang::get('validation.logs.projectdelete_success', ['name' => $find_data->project_name, 'username' => $check_user->username]), $check_user->id);
                    return success([], Lang::get('validation.custom.project_delete_success'));
                } else {
                    addlog('Delete', 'Project', Lang::get('validation.logs.projectdelete_failed', ['name' => $find_data->project_name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.project_delete_failed'));
                }
            }
        } else {
            return error(Lang::get('validation.custom.invalid_projectid'));
        }
    }

    // Get import progress
    public function importprogress(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'projectid' => 'required|numeric|min:1|exists:App\Models\Project,id',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $find_file = Files::where('project_id', $request->projectid)->where('type', 'Import')->first();
        if (!empty($find_file)) {
            if ($find_file->import_status == 1) {
                //Import Done
                $update_rows = ImportData::where('file_id', $find_file->id)->update(['project_id' => $request->projectid]);
                $find_row = ImportData::where('file_id', $find_file->id)->first();
                $find_cols = ImportColumns::where('file_id', $find_file->id)->first();
                if (!empty($find_cols) && empty($find_row)) {

                    Schema::disableForeignKeyConstraints();
                    ImportColumns::where('project_id', $request->projectid)->delete();
                    Project::where('id', $request->projectid)->delete();
                    Files::where('project_id', $request->projectid)->delete();
                    ImportData::where('file_id', $find_file->id)->delete();
                    Schema::enableForeignKeyConstraints();

                    return error(Lang::get('validation.custom.empty_excel_file'), "Failed");
                }
                if (!empty($find_row)) {
                    if (!empty($find_cols)) {
                        $update_columns = ImportColumns::where('file_id', $find_file->id)->update(['project_id' => $request->projectid]);
                    } else {
                        $get_row = $find_row->toArray();
                        $sheet_headers = array_filter(array_keys((array)$get_row['row_details']));
                        $sheet_headers = array_map(function ($value) {
                            return str_replace(' ', '_', $value);
                        }, $sheet_headers);
                        $required_cols = array('Account_Reference', 'Current_Balance', 'Delinquency_Flag');
                        $search_array = array_map('strtolower', $sheet_headers);
                        foreach ($required_cols as $recol) {
                            if (!in_array(strtolower($recol), $search_array)) {

                                Schema::disableForeignKeyConstraints();
                                ImportColumns::where('project_id', $request->projectid)->delete();
                                Project::where('id', $request->projectid)->delete();
                                Files::where('project_id', $request->projectid)->delete();
                                ImportData::where('file_id', $find_file->id)->delete();
                                Schema::enableForeignKeyConstraints();

                                return error(Lang::get('validation.custom.file_import_failed_reqcols'), "Failed");
                            }
                        }
                        $add_all = array();
                        $current_time = currenthumantime();
                        foreach ($sheet_headers as $single_head) {
                            $add_all[] = ['file_id' => $find_file->id, 'project_id' => $request->projectid, 'column_heading' => $single_head, 'created_at' => $current_time, 'updated_at' => $current_time];
                        }
                        if (!empty($add_all)) {
                            $add_many = ImportColumns::insert($add_all);
                        }
                    }

                    $sheet_columns = ImportColumns::where('project_id', $request->projectid)->get();
                    if (!empty($sheet_columns)) {
                        return success($sheet_columns, 'success', "Success");
                    } else {
                        return error(Lang::get('validation.custom.invalid_projectid'));
                    }
                }
            } elseif ($find_file->import_status == 2) {
                //Pending
                $final_data = array('percentage' => 0.00);
                if ($find_file->total_rows > 0) {
                    // $get_imported = ImportData::where('file_id', $find_file->id)->get();
                    // $total_imported = $get_imported->count();
                    // $total_imported = ImportData::where('file_id', $find_file->id)->count();
                    $total_imported = $find_file->imported_rows;
                    if ($total_imported > 0) {
                        $totalrows = $find_file->total_rows;
                        $percent = $total_imported / $totalrows;
                        $imported_percent = number_format($percent * 100, 2);
                        $final_data = array('percentage' => $imported_percent);
                        if ($total_imported >= $totalrows) {
                            // $find_file->import_status = 1;
                            // $find_file->import_end_time = time();
                            // $find_file->save();
                            $final_data = array('percentage' => 98.00);
                        }
                    }
                }
                return success($final_data, 'success', "Pending");
            } else {
                //Falied
                return error(Lang::get('validation.custom.file_import_failed'), "Failed");
            }
        } else {
            return error(Lang::get('validation.custom.invalid_projectid'));
        }
    }



    // export project report
    public function generatereport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'projectid' => 'required|numeric|min:1|exists:App\Models\Project,id',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        // $find_project = Project::find($request->projectid);
        // print_r($find_project);

        #curl request to export project
        $siteurl = env('LARAVEL_SITE_URL');
        // $url = $siteurl . '/readexcelspout/generatereport.php?projectid='.$request->projectid;
        $url = $siteurl . '/phpspreadsheet/generatereport.php?projectid='.$request->projectid;
        // echo $url;die;
        $ch = curl_init();
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
        );
        curl_setopt($ch, CURLOPT_URL, $url );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
        }
        curl_close($ch);
        // $final_result = json_decode($result, true);
        if ($httpCode == 200) {
            return success($result, 'success');
        } else {
            return error(Lang::get('validation.custom.file_export_failed'), "Failed");
        }
        exit;
    }

    public function restoreproject(Request $request, $projectid){
        $find_data = Project::find($projectid);
        if (!empty($find_data)) {
            if ($find_data->status != 0) {
                return error(Lang::get('validation.custom.data_already_active', ['module' => 'Project']));
            } else {
                $check_user = $request->get('current_user');
                $find_data->status = 1;
                if ($find_data->save()) {
                    addlog('Restore', 'Project', Lang::get('validation.logs.projectrestore_success', ['name' => $find_data->project_name, 'username' => $check_user->username]), $check_user->id);
                    return success($find_data, Lang::get('validation.custom.data_restore_success', ['module' => 'Project']));
                } else {
                    addlog('Restore', 'Platform', Lang::get('validation.logs.projectrestore_failed', ['name' => $find_data->project_name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.data_restore_failed', ['module' => 'Project']));
                }
            }
        } else {
            return error(Lang::get('validation.custom.invalid_projectid'));
        }
    }
    
    //Archieve project by make projects.is_archived=1
    public function isArchived(Request $request) {
        
        //validate request  
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|numeric|min:1|exists:App\Models\Project,id',
            'is_archived' => 'required|in:0,1', 
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $project = Project::find($request->project_id);

        if($project->is_archived == 0){
            $check_user = $request->get('current_user');
            // archieve project
            $project->is_archived = 1;
            if ($project->save()) {
                addlog('Archieve', 'Project', Lang::get('validation.logs.project_archieve_success', ['name' => $project->project_name, 'username' => $check_user->username]), $check_user->id);
                return success([], Lang::get('validation.custom.project_archieve_success'));
            } else {
                addlog('Archieve', 'Project', Lang::get('validation.logs.project_archieve_failed', ['name' => $project->project_name, 'username' => $check_user->username]), $check_user->id);
                return error(Lang::get('validation.custom.project_archieve_failed'));
            }
        } else {
            return error(Lang::get('validation.custom.project_already_archieved', ['module' => 'Project']));
        }

    }

    //Unrchieve project by make projects.is_archived=0
    public function Unarchive(Request $request) {
        
        //validate request  
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|numeric|min:1|exists:App\Models\Project,id',
            'is_archived' => 'required|in:0,1', 
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $project = Project::find($request->project_id);
        
        if($project->is_archived == 1){
            $check_user = $request->get('current_user');
            // unarchieve project
            $project->is_archived = 0;
            if ($project->save()) {
                addlog('Unarchive', 'Project', Lang::get('validation.logs.project_unarchive_success', ['name' => $project->project_name, 'username' => $check_user->username]), $check_user->id);
                return success([], Lang::get('validation.custom.project_unarchive_success'));
            } else {
                addlog('Unarchive', 'Project', Lang::get('validation.logs.project_unarchive_failed', ['name' => $project->project_name, 'username' => $check_user->username]), $check_user->id);
                return error(Lang::get('validation.custom.project_unarchive_failed'));
            }
        } else {
            return error(Lang::get('validation.custom.project_already_unarchive', ['module' => 'Project']));
        }

    }

    //Get all archive projects
    public function allArchive(Request $request) {
       
        $check_user = $request->get('current_user');
        $getdata = Project::query()
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

        //ignore archived projects
        $getdata->where('is_archived', 1);

        $all_data = $getdata->get();
        if (!empty($all_data)) {
            foreach ($all_data as $sdata) {
                $sdata->import_status = 0;
                $sdata->imported_rows = 0;
                $find_data = Files::where('project_id', $sdata->id)->where('type', 'Import')->first();
                if (!empty($find_data)) {
                    $sdata->import_status = $find_data->import_status;
                    if ($find_data->imported_rows > 1)
                        $sdata->imported_rows = $find_data->imported_rows - 1;
                }
                $find_data = User::find($sdata->created_by);
                if (!empty($find_data))
                    $sdata->created_user = $find_data->firstname . ' ' . $find_data->lastname;
                else
                    $sdata->created_user = '';
            }
        }

        foreach ($all_data as $k => $pdata) 
        {
            $totalimportdata = ImportData::select(DB::raw('SUM(CASE WHEN integrity_status = 2 THEN 1 ELSE 0 END) as integrity_statustotal'),DB::raw('SUM(CASE WHEN task_status = 2 THEN 1 ELSE 0 END) as task_statustotal'))
                ->where('project_id', $pdata->id)->first();

            if ($totalimportdata->count() > 0) {
                // Add integrity_statustotal to the current project
                $all_data[$k]['integrity_statustotal'] = (int) $totalimportdata->integrity_statustotal;
                $all_data[$k]['task_statustotal'] = (int) $totalimportdata->task_statustotal;
            } 
        }

        return ($all_data) ? success($all_data) : error();
        
    }

}
