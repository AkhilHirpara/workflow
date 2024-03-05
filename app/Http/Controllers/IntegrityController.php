<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Lang;
use App\Models\Project;
use App\Models\Files;
use App\Models\ImportData;
use App\Models\Questions;
use App\Models\User;
use App\Models\Task;
use App\Models\ImportColumns;
use App\Models\Integrity;



class IntegrityController extends Controller
{


    // Get single row/task from project imported sheet
    public function getsingleintegrityrow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'projectid' => 'required|numeric|min:1|exists:App\Models\Project,id',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $check_user = $request->get('current_user');
        $find_project = Project::find($request->projectid);
        if (!empty($find_project) && $find_project->status != 1 && $find_project->status != 2) {
            return error(Lang::get('validation.custom.project_not_active'));
        }
        $current_time = currenthumantime();

        $check_integrity = Integrity::where('project_id', $find_project->id)->where('worked_by', $check_user->id)->where('status', 1)->first();

        // Get single row/task details
        if (!empty($check_integrity)) {
            //previous pending row exist
            $get_row = ImportData::find($check_integrity->row_id);
            if($check_integrity->status != '1' && $find_project->integrity_precentage_completed != '100'){
                $check_integrity->last_modified_by = $check_user->id;
                $check_integrity->save();    
            }
            
        } else {
            //Get new row
            $get_row = ImportData::where('project_id', $find_project->id)->where('integrity_status', 0)->first();
            if (empty($get_row)) {

                //all rows are completed,start from first again
                //get previous pending row if exist
                $check_second_integrity = Integrity::where('project_id', $find_project->id)->where('last_review_doneby', $check_user->id)->where('last_review_status', 1)->first();
                if (empty($check_second_integrity)) {

                    $check_second_integrity = Integrity::where('project_id', $find_project->id)->where('last_review_status', 0)->first();
                    if (empty($check_second_integrity)) {

                        $check_second_integrity = Integrity::where('project_id', $find_project->id)->where('last_review_doneby', $check_user->id)->where('last_review_status','3')->first();

                        if (empty($check_second_integrity)) {
                            //start from again if second review is also done
                            $update_tasks = Integrity::where('project_id', $find_project->id)
                            ->where('last_review_status', 2)
                            ->update(['last_review_status' => 0]);
                            $check_second_integrity = Integrity::where('project_id', $find_project->id)->where('last_review_status', 0)->first(); 
                            if (empty($check_second_integrity)) {
                                $check_second_integrity = Integrity::where('project_id', $find_project->id)->where('last_review_status', 3)->first(); 
                            }
                        }                        
                    }
                }
                // $check_second_integrity->last_review_doneby = $check_user->id;
                // $check_second_integrity->last_review_status = '1';
                $check_second_integrity->save();
                $get_row = ImportData::find($check_second_integrity->row_id);

                if($find_project->integrity_precentage_completed != '100'){
                    $check_second_integrity->last_review_doneby = $check_user->id;
                    $check_second_integrity->worked_by = $check_user->id;
                    // $check_second_integrity->last_modified_by = $check_user->id;
                    $check_second_integrity->save();  
                }

            } else {
                $get_row->integrity_status = 1; //inprogress
                $get_row->save();
                // $add_newintegrity = Integrity::create(['project_id' => $find_project->id, 'row_id' => $get_row->id, 'status' => 1, 'worked_by' => $check_user->id, 'last_review_status'=> 1,'record_owner' => $check_user->id, 'last_review_doneby' =>$check_user->id, 'worked_date' => $current_time]);
                $add_newintegrity = Integrity::create(['project_id' => $find_project->id, 'row_id' => $get_row->id, 'status' => 1, 'worked_by' => $check_user->id, 'last_review_status'=> 1, 'last_review_doneby' =>$check_user->id, 'worked_date' => $current_time]);
                if (!$add_newintegrity) {
                    return error(Lang::get('validation.custom.integrity_task_add_failed'));
                }
            }

        }

        $is_integrity_exist = array();
        if (!empty($check_integrity)) {
            $is_integrity_exist = $check_integrity;
        } else if (!empty($check_second_integrity)) {
            $is_integrity_exist = $check_second_integrity;
        }
        $rowdata = $get_row->row_details;
        $sheet_columns = ImportColumns::where('project_id', $find_project->id)->where('status', 1)->get();
        if (empty($sheet_columns)) {
            return error(Lang::get('validation.custom.project_no_active_cols'));
        }
        $active_cols = $sheet_columns->pluck('column_heading')->toArray();
        array_push($active_cols,"Account_Reference", "Current_Balance", "Delinquency_Flag");
        $investor_ref = '';
        foreach ($rowdata as $skey => $sval) {
            // $investor_ref = ($investor_ref == '') ? $sval : $investor_ref;
            if($skey == 'Account_Reference')
                $investor_ref = $sval;
            if (!in_array($skey, $active_cols))
                unset($rowdata->$skey);
        }
        $get_row->investor_ref = $investor_ref;
        $get_row->row_details = json_encode($rowdata);
        $get_row->only_integrity = $find_project->only_integrity;
        $get_row->review_completed_percentage = $find_project->percentage_completed;
        $get_row->integrity_completed_percentage = $find_project->integrity_precentage_completed;

        $get_row->last_row_id ='';
        $lastRowId = ImportData::select('id')->where('project_id',$find_project->id)->latest('id')->first();
        if($lastRowId)
            $get_row->last_row_id = $lastRowId->id;
        
        $get_row->project_name = $find_project->project_name;

        $get_row->owner_name = $check_user->firstname . ' ' . $check_user->lastname;
        if (!empty($is_integrity_exist)) {
            $find_owner = User::find($is_integrity_exist->worked_by);
            if($find_owner)
                $get_row->owner_name = $find_owner->firstname . ' ' . $find_owner->lastname;
            if (isset($is_integrity_exist->answers) && trim($is_integrity_exist->answers) != '') {
                $get_row->prev_answers = json_decode($is_integrity_exist->answers);
            }
        }
        return ($get_row) ? success($get_row) : error();
    }

    // Save integrity details and answers
    public function saverowintegritydetails(Request $request)
    {
        $validate_array = array(
            'projectid' => 'required|numeric|min:1|exists:App\Models\Project,id',
            'rowid' => 'required|numeric|min:1|exists:App\Models\Integrity,row_id',
            // 'answers' => 'required|array',
        );
        $validator = Validator::make($request->all(), $validate_array);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $find_project = Project::find($request->projectid);
        $check_user = $request->get('current_user');
        $find_integrity = Integrity::where('project_id', $find_project->id)->where('row_id', $request->rowid)->first();
        if (!empty($find_integrity)) {
            $current_time = currenthumantime();
            $sheet_columns = ImportColumns::where('project_id', $find_project->id)->where('status', 1)->count();
            // if (sizeof($request->answers) != ($sheet_columns)) {
            //     return error(Lang::get('validation.custom.integrity_provide_all_answers'));
            // }
            $find_integrity->answers = json_encode($request->answers);

            $already_completed = 0;
            if ($find_integrity->status == 2) {
                //already completed
                $already_completed = 1;
                $find_integrity->last_review_status = 2;
                $find_integrity->last_review_doneby = $check_user->id;
                $find_integrity->last_review_check_date = $current_time;
                $find_integrity->worked_by = $check_user->id;
            } 
            // else {
            //     $find_integrity->status = 2;
            //     $find_integrity->worked_by = $check_user->id;
            //     #changed
            //     // $find_integrity->last_review_status = 2;
            //     $find_integrity->last_review_doneby = $check_user->id;
            //     $find_integrity->worked_date = $current_time;
            // }

            if($request->incomplete_status){
                // echo "<pre>";print_r($find_integrity);return;
                if($request->incomplete_status && $find_integrity->last_review_status == '3'){
                    $find_integrity->last_review_status = 2;
                }
                else{
                    $find_integrity->last_review_status = 3;
                }

                $find_integrity->status = 3;
                $find_integrity->worked_by = $check_user->id;
                // $find_integrity->last_review_status = 3;
                $find_integrity->last_review_doneby = $check_user->id;
                $find_integrity->worked_date = $current_time;
            }
            else{
                $find_integrity->status = 2;
                $find_integrity->last_review_status = 2;
                $find_integrity->last_review_doneby = $check_user->id;
                $find_integrity->worked_by = $check_user->id;
            }

            if ($request->has('integrity_status') && $request->integrity_status != 0 && $request->integrity_status != 1){
                if($find_integrity->record_owner =='')
                    $find_integrity->record_owner = $check_user->id;
                else
                    $find_integrity->last_modified_by = $check_user->id;  
            }
            if($find_integrity->record_owner =='')
                $find_integrity->record_owner = $check_user->id;
            else
                $find_integrity->last_modified_by = $check_user->id;  


            if ($find_integrity->save()) {
                if ($already_completed == 0) {
                    $update_row = ImportData::where('id', $request->rowid)->update(['integrity_status' => 2]);
                }
                if($request->incomplete_status){
                    $update_row = ImportData::where('id', $request->rowid)->update(['integrity_status' => 3]);   
                }

                // if($find_project->only_integrity){
                    $completedPercent = Integrity::where('status','2')->where('project_id',$request->projectid)->get();
                    $totalRows = Files::select('imported_rows')->where('project_id',$request->projectid)->get();
                    if (count($completedPercent) > 0) {
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
                // }
                return success($find_integrity, Lang::get('validation.custom.integrity_update_success'));
            } else {
                return error(Lang::get('validation.custom.integrity_update_failed'));
            }
        } else {
            return error(Lang::get('validation.custom.integrity_invalid_rowid'));
        }
    }

    public function getsingleintegrityrowview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'projectid' => 'required|numeric|min:1|exists:App\Models\Project,id',
            // 'rowid' => 'required|numeric|min:1|exists:App\Models\Integrity,row_id',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $check_user = $request->get('current_user');
        $find_project = Project::find($request->projectid);
        if (!empty($find_project) && $find_project->status != 1 && $find_project->status != 2) {
            return error(Lang::get('validation.custom.project_not_active'));
        }

        $sheet_columns = ImportColumns::where('project_id', $find_project->id)->where('status', 1)->get();
        if (empty($sheet_columns)) {
            return error(Lang::get('validation.custom.project_no_active_cols'));
        }

        $rowDetails = '';
        if ($request->has('rowid') && $request->rowid > 0){
            $rowDetails = ImportData::where('project_id', $find_project->id)->where('id',$request->rowid)->first();
            $integrityDetails = Integrity::where('row_id',$request->rowid)->where('project_id', $find_project->id)->first();
        }
        else if ($request->has('nextid') && $request->nextid > 0){
            $rowDetails = ImportData::where('project_id', $find_project->id)
            ->where('integrity_status', 2)
            // ->where(function($q) {
            //       $q->where('integrity_status', 2)
            //         ->orWhere('integrity_status', 3);
            //   })
            ->where('id', '>', $request->nextid)->orderBy('id','asc')->first();

            if(empty($rowDetails)){
                $rowDetails = ImportData::where('project_id', $find_project->id)
                ->where('integrity_status', 2)
                // ->where(function($q) {
                //     $q->where('integrity_status', 2)
                //     ->orWhere('integrity_status', 3);
                // })
                ->first();
            }
            $integrityDetails = Integrity::where('row_id',$rowDetails->id)->where('project_id', $find_project->id)->first();

        }

        $rowdata = $rowDetails->row_details;
        $sheet_columns = ImportColumns::where('project_id', $find_project->id)->where('status', 1)->get();
        if (empty($sheet_columns)) {
            return error(Lang::get('validation.custom.project_no_active_cols'));
        }
        $active_cols = $sheet_columns->pluck('column_heading')->toArray();
        array_push($active_cols,"Account_Reference", "Current_Balance", "Delinquency_Flag");
        $investor_ref = '';
        foreach ($rowdata as $skey => $sval) {
            // $investor_ref = ($investor_ref == '') ? $sval : $investor_ref;
            // echo "<pre>";print_r($skey);print_r($sval);return;
            if($skey == 'Account_Reference')
                $investor_ref = $sval;

            if (!in_array($skey, $active_cols))
                unset($rowdata->$skey);
        }
        $rowDetails->investor_ref = $investor_ref;
        $rowDetails->row_details = json_encode($rowdata);

        $rowDetails->review_completed_percentage = $find_project->percentage_completed;
        $rowDetails->integrity_completed_percentage = $find_project->integrity_precentage_completed;
        $rowDetails->only_integrity = $find_project->only_integrity;

        $rowDetails->last_row_id ='';
        $lastRowId = ImportData::select('id')->where('project_id',$find_project->id)->latest('id')->first();
        if($lastRowId)
            $rowDetails->last_row_id = $lastRowId->id;

        $rowDetails->project_name = $find_project->project_name;

        if(!empty($integrityDetails) && isset($integrityDetails->worked_by)){
            $find_owner = User::find($integrityDetails->worked_by);
            if($find_owner)
                $rowDetails->owner_name = $find_owner->firstname . ' ' . $find_owner->lastname;
        }
        
        if (isset($integrityDetails->answers) && trim($integrityDetails->answers) != ''){
            
            $decodedAnswer = json_decode($integrityDetails->answers);
            foreach($decodedAnswer as $eachAnswer){
                $cname = $eachAnswer->column_name;
                if(isset($rowDetails->row_details->$cname))
                    $eachAnswer->data = $rowDetails->row_details->$cname;
            }
            // echo "<pre>";print_r($decodedAnswer);die;
            // $rowDetails->prev_answers = json_decode($integrityDetails->answers);
            $rowDetails->prev_answers = $decodedAnswer;
        }

        return ($rowDetails) ? success($rowDetails) : error();
    }
}
