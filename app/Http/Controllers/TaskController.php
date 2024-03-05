<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\Integrity;
use App\Models\ImportColumns;
use App\Models\ImportData;
use App\Models\Project;
use App\Models\Templates;
use App\Models\Questions;
use App\Models\User;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    
    public function viewprojecttasks(Request $request){
        $validator = Validator::make($request->all(), [
            'project_id' => 'required',
            'no_of_rows' => 'required',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $importColumns = ImportColumns::where('status', '1')->where('project_id', $request->project_id)->pluck('column_heading')->toArray();
        #for account reference
        $importColumns[] = 'Account_Reference';
        $tasklist = ImportData::where('project_id', $request->project_id)->limit($request->no_of_rows)->get();
        $isProjectOnlyIntegrity = Project::select('status','only_integrity','only_review')->where('id',$request->project_id)->get();
        if($tasklist->count() > 0){
            foreach($tasklist as $eachTask){
                $importCols = array();
                foreach($eachTask->row_details as $keyRow => $eachRow){
                    if(in_array($keyRow, $importColumns)){
                        $importCols[$keyRow] = $eachRow;
                    }
                }
                $reviewStatus = Task::select('status','record_owner','last_review_doneby', 'last_modified_by')->where('row_id',$eachTask['id'])->where('project_id',$eachTask['project_id'])->first();
                if($reviewStatus)
                    $eachTask->task_status = $reviewStatus->status;

                // $eachTask->last_review_done_by = '';
                // if($reviewStatus && $reviewStatus->last_review_doneby){
                //     $user = User::where('id',$reviewStatus->last_review_doneby)->first();
                //     if($user){
                //         $eachTask->last_review_done_by = $user->firstname. ' '. $user->lastname;
                //     }
                // }

                $eachTask->last_review_done_by = '';
                if($reviewStatus && $reviewStatus->last_modified_by){
                    $user = User::where('id',$reviewStatus->last_modified_by)->first();
                    if($user){
                        $eachTask->last_review_done_by = $user->firstname. ' '. $user->lastname;
                    }
                }

                $eachTask->review_record_owner = '';
                if($reviewStatus && $reviewStatus->record_owner){
                    $user = User::where('id',$reviewStatus->record_owner)->first();
                    if($user){
                        $eachTask->review_record_owner = $user->firstname. ' '. $user->lastname;
                    }
                }


                $eachTask->last_integrity_done_by ='';
                $eachTask->integrity_record_owner = '';
                $lastIntegrityDone = Integrity::select('record_owner', 'last_review_doneby', 'last_modified_by')->where('row_id',$eachTask['id'])->where('project_id',$eachTask['project_id'])->first();
                if($lastIntegrityDone){
                    $user = User::where('id',$lastIntegrityDone->last_modified_by)->first();
                    if($user){
                        $eachTask->last_integrity_done_by = $user->firstname. ' '. $user->lastname;
                    }
                    $user = User::where('id',$lastIntegrityDone->record_owner)->first();
                    if($user){
                        $eachTask->integrity_record_owner = $user->firstname. ' '. $user->lastname;
                    }
                }  
                
                $eachTask->project_status = $isProjectOnlyIntegrity[0]->status;
                $eachTask->row_details = json_encode($importCols);
                $eachTask->only_integrity = $isProjectOnlyIntegrity[0]->only_integrity;
                $eachTask->only_review = $isProjectOnlyIntegrity[0]->only_review;
            }
        }
        return success($tasklist, Lang::get('validation.custom.folder_added_success'));
        

        // $reviews = Task::where('project_id', $request->project_id)->with('ownershipDetails')->limit($request->no_of_rows)->get();
        // if($reviews->count()){
        //     foreach($reviews as $eachReview){
        //         if($eachReview->ownershipDetails){
        //             $eachReview->owner_name = $eachReview->ownershipDetails->firstname. ' '. $eachReview->ownershipDetails->lastname;
        //             unset($eachReview->ownershipDetails);
        //         }
        //         if($eachReview->workedBy){
        //             $eachReview->worked_by_name = $eachReview->workedBy->firstname. ' '. $eachReview->workedBy->lastname;
        //             unset($eachReview->workedBy);
        //         }
        //         if($eachReview->lastReviewDoneBy){
        //             $eachReview->last_review_done_by = $eachReview->lastReviewDoneBy->firstname. ' '. $eachReview->lastReviewDoneBy->lastname;
        //             unset($eachReview->lastReviewDoneBy);
        //         }
        //     }
        // }
        // $integrity = Integrity::where('project_id', $request->project_id)->with('workedBy')->with('lastReviewDoneBy')->limit($request->no_of_rows)->get();
        // if($integrity->count()){
        //     foreach($integrity as $eachIntegrity){
        //         if($eachIntegrity->workedBy){
        //             $eachIntegrity->worked_by_name = $eachIntegrity->workedBy->firstname. ' '. $eachIntegrity->workedBy->lastname;
        //             unset($eachIntegrity->workedBy);
        //         }
        //         if($eachIntegrity->lastReviewDoneBy){
        //             $eachIntegrity->last_review_done_by = $eachIntegrity->lastReviewDoneBy->firstname. ' '. $eachIntegrity->lastReviewDoneBy->lastname;
        //             unset($eachIntegrity->lastReviewDoneBy);
        //         }
        //     }
        // }
        // // echo "<pre>";print_r($integrity);die;
        // $data['reviews'] = $reviews;
        // $data['integrity'] = $integrity;
        // return success($data, Lang::get('validation.custom.folder_added_success'));
    }

    public function getreviewdetails(Request $request){

        $validator = Validator::make($request->all(), [
            'project_id' => 'required',
            'row_id' => 'required',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $check_user = $request->get('current_user');
        $taskDetails = Task::where('row_id', $request->row_id)->where('project_id', $request->project_id)->with('ownershipDetails')->get();
        $find_project = Project::find($request->project_id);
        if(!isset($taskDetails[0])){
            $get_row = ImportData::where('project_id', $request->project_id)->where('id', $request->row_id)->first();
            if (!empty($get_row)) {
                $get_row->task_status = 1; //inprogress
                $get_row->save();
                // Task::create(['project_id' => $request->project_id, 'row_id' => $request->row_id, 'status' => 1, 'last_review_doneby'=>$check_user->id, 'worked_by' => $check_user->id, 'record_owner' => $check_user->id, 'ownership' => $check_user->id]);
                Task::create(['project_id' => $request->project_id, 'row_id' => $request->row_id, 'status' => 1, 'last_review_doneby'=>$check_user->id, 'worked_by' => $check_user->id, 'ownership' => $check_user->id]);
                $taskDetails = Task::where('row_id', $request->row_id)->where('project_id', $request->project_id)->with('ownershipDetails')->get();
                if (!$taskDetails) {
                    return error(Lang::get('validation.custom.review_task_add_failed'));
                }
            } 
            // else {
            //     //all rows are completed,start from first again
            //     //get previous pending row if exist
            //     $check_second_task = Task::where('project_id', $find_project->id)->where('last_review_doneby', $find_user->id)->where('last_review_status', 1)->first();
            //     if (empty($check_second_task)) {
            //         $check_second_task = Task::where('project_id', $find_project->id)->where('last_review_status', 0)->first();
            //         if (empty($check_second_task)) {
            //             //start from again if second review is also done
            //             $update_tasks = Task::where('project_id', $find_project->id)->update(['last_review_status' => 0]);
            //             $check_second_task = Task::where('project_id', $find_project->id)->where('last_review_status', 0)->first();
            //         }
            //     }
            //     $check_second_task->last_review_status = 1;
            //     $check_second_task->last_review_doneby = $find_user->id;
            //     $check_second_task->save();
            //     $get_row = ImportData::find($check_second_task->row_id);
            // }
        }
        else{
            if($taskDetails[0]->status != '1' && $find_project->integrity_precentage_completed != '100'){
                if($taskDetails[0]->record_owner =='')
                    $taskDetails[0]->record_owner = $check_user->id;
                // else
                //     $taskDetails[0]->last_modified_by = $check_user->id;
            }
            // if($taskDetails[0]->record_owner =='')
            //     $taskDetails[0]->record_owner = $check_user->id;
            // else
            //     $taskDetails[0]->last_modified_by = $check_user->id;
            
            $taskDetails[0]->save();  
        }

        
        // Check template and get questions
        if ($find_project->template_id == NULL || $find_project->template_id == '') {
            return error(Lang::get('validation.custom.review_no_template'));
        }
        if ($find_project->template_id > 0) {
            $find_template = Templates::find($find_project->template_id);
            if (empty($find_template) || $find_template->status == 0) {
                return error(Lang::get('validation.custom.review_no_template'));
            }
        }
        $prev_answers = array();
        $getquestions = Questions::where('status', '!=', 0)->where('template_id', $find_project->template_id)->get();
        if (!empty($getquestions)) {
            $prev_answers = json_decode($taskDetails[0]->answers);
            foreach ($getquestions as $sdata) {
                $sdata->selected_choice = '';
                $sdata->comment = '';
                if (!empty($prev_answers)) {
                    foreach ($prev_answers as $sans) {
                        if ($sdata->id == $sans->questionid) {
                            $sdata->selected_choice = $sans->selected_choice;
                            $sdata->comment = $sans->comment;
                        }
                    }
                }
                if ($sdata->category == 1) {
                    $all_questions['file_questions'][] = $sdata;
                } elseif ($sdata->category == 2) {
                    $all_questions['loan_questions'][] = $sdata;
                } elseif ($sdata->category == 3) {
                    $all_questions['delinquency_questions'][] = $sdata;
                } else {
                }
            }
        } else {
            return error(Lang::get('validation.custom.review_no_questions'));
        }
        if($taskDetails[0]->ownershipDetails){
            $taskDetails[0]->owner_name = $taskDetails[0]->ownershipDetails->firstname. ' ' . $taskDetails[0]->ownershipDetails->lastname;
            unset($taskDetails[0]->ownershipDetails);
        }
        $row_details = ImportData::select('file_id','row_details','task_status','integrity_status')->where('id',$request->row_id)->where('project_id', $request->project_id)->get();
        if($row_details->count()){

            $rdata = $row_details[0]->row_details;
            $sheet_columns = ImportColumns::where('project_id', $find_project->id)->where('review_status', 1)->get();
            if (empty($sheet_columns)) {
                return error(Lang::get('validation.custom.project_no_active_cols'));
            }
            $active_cols = $sheet_columns->pluck('column_heading')->toArray();
            foreach ($rdata as $skey => $sval) {
                if (!in_array($skey, $active_cols))
                    unset($rdata->$skey);
            }
            $taskDetails[0]->row_details = $rdata;
        }

        

        $final_return = (object) array();
        
        $final_return->all_questions = $all_questions;
        $final_return->singel_row = $taskDetails[0];
        $final_return->singel_row->Account_Reference = $row_details[0]->row_details->Account_Reference;
        $final_return->singel_row->row_comment = $taskDetails[0]->comment;
        $final_return->singel_row->id = $taskDetails[0]->row_id;
        $final_return->singel_row->file_id = $row_details[0]->file_id;
        $final_return->singel_row->task_status = $row_details[0]->task_status;
        $final_return->singel_row->integrity_status = $row_details[0]->integrity_status;

        $final_return->singel_row->review_completed_percentage = $find_project->percentage_completed;
        $final_return->singel_row->integrity_completed_percentage = $find_project->integrity_precentage_completed;
        $final_return->singel_row->project_name = $find_project->project_name;
        $final_return->singel_row->only_review = $find_project->only_review;
        
        $last_row_id ='';
        $lastRowId = ImportData::select('id')->where('project_id',$find_project->id)->latest('id')->first();
        if($lastRowId)
            $last_row_id = $lastRowId->id;

        $final_return->singel_row->last_row_id = $last_row_id;

        unset($final_return->singel_row->comment);
        unset($final_return->singel_row->answers);
        unset($final_return->singel_row->worked_by);
        unset($final_return->singel_row->last_review_check_date);
        unset($final_return->singel_row->ownership);
        unset($final_return->singel_row->row_id);
        return ($final_return) ? success($final_return) : error();

    }

    public function getreviewdetailsview(Request $request){
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

        $sheet_columns = ImportColumns::where('project_id', $find_project->id)->where('status', 1)->get();
        if (empty($sheet_columns)) {
            return error(Lang::get('validation.custom.project_no_active_cols'));
        }

        $rowDetails = '';
        if ($request->has('rowid') && $request->rowid > 0){
            $rowDetails = ImportData::where('project_id', $find_project->id)->where('id',$request->rowid)->first();
            $reviewDetails = Task::where('row_id',$request->rowid)->where('project_id', $find_project->id)->with('ownershipDetails')->first();
        }
        else if ($request->has('nextid') && $request->nextid > 0){
            $rowDetails = ImportData::where('project_id', $find_project->id)
            ->where('task_status', 2)
            // ->where(function($q) {
            //       $q->where('task_status', 2)
            //         ->orWhere('task_status', 3);
            //   })
            ->where('id', '>', $request->nextid)->orderBy('id','asc')->first();

            if(empty($rowDetails)){
                $rowDetails = ImportData::where('project_id', $find_project->id)
                ->where('task_status', 2)
                // ->where(function($q) {
                //     $q->where('task_status', 2)
                //     ->orWhere('task_status', 3);
                // })
                ->first();
            }

            $reviewDetails = Task::where('row_id',$rowDetails->id)->where('project_id', $find_project->id)->with('ownershipDetails')->first();
        }


        $prev_answers = array();
        $getquestions = Questions::where('status', '!=', 0)->where('template_id', $find_project->template_id)->get();
        if (!empty($getquestions)) {
            $prev_answers = json_decode($reviewDetails->answers);
            foreach ($getquestions as $sdata) {
                $sdata->selected_choice = '';
                $sdata->comment = '';
                if (!empty($prev_answers)) {
                    foreach ($prev_answers as $sans) {
                        if ($sdata->id == $sans->questionid) {
                            $sdata->selected_choice = $sans->selected_choice;
                            $sdata->comment = $sans->comment;
                        }
                    }
                }
                if ($sdata->category == 1) {
                    $all_questions['file_questions'][] = $sdata;
                } elseif ($sdata->category == 2) {
                    $all_questions['loan_questions'][] = $sdata;
                } elseif ($sdata->category == 3) {
                    $all_questions['delinquency_questions'][] = $sdata;
                } else {
                }
            }
        } else {
            return error(Lang::get('validation.custom.review_no_questions'));
        }

        if($reviewDetails->ownershipDetails){
            $reviewDetails->owner_name = $reviewDetails->ownershipDetails->firstname. ' ' . $reviewDetails->ownershipDetails->lastname;
            unset($reviewDetails->ownershipDetails);
        }


        if($rowDetails->count()){

            $rdata = $rowDetails->row_details;
            $sheet_columns = ImportColumns::where('project_id', $find_project->id)->where('review_status', 1)->get();
            if (empty($sheet_columns)) {
                return error(Lang::get('validation.custom.project_no_active_cols'));
            }
            $active_cols = $sheet_columns->pluck('column_heading')->toArray();
            foreach ($rdata as $skey => $sval) {
                if (!in_array($skey, $active_cols))
                    unset($rdata->$skey);
            }
            $reviewDetails->row_details = $rdata;
        }

        $last_row_id ='';
        $lastRowId = ImportData::select('id')->where('project_id',$find_project->id)->latest('id')->first();
        if($lastRowId)
            $last_row_id = $lastRowId->id;


        $final_return = (object) array();
        $final_return->singel_row = $reviewDetails;
        $final_return->singel_row->Account_Reference = $rowDetails->row_details->Account_Reference;
        $final_return->all_questions = $all_questions;

        $final_return->singel_row->last_row_id = $last_row_id;
        $final_return->singel_row->row_comment = $reviewDetails->comment;
        $final_return->singel_row->id = $reviewDetails->row_id;
        $final_return->singel_row->file_id = $rowDetails->file_id;
        $final_return->singel_row->task_status = $rowDetails->task_status;
        $final_return->singel_row->integrity_status = $rowDetails->integrity_status;

        $final_return->singel_row->review_completed_percentage = $find_project->percentage_completed;
        $final_return->singel_row->integrity_completed_percentage = $find_project->integrity_precentage_completed;
        $final_return->singel_row->project_name = $find_project->project_name;
        $final_return->singel_row->only_integrity = $find_project->only_integrity;
        $final_return->singel_row->only_review = $find_project->only_review;

        unset($final_return->singel_row->comment);
        unset($final_return->singel_row->answers);
        unset($final_return->singel_row->worked_by);
        unset($final_return->singel_row->last_review_check_date);
        unset($final_return->singel_row->ownership);
        unset($final_return->singel_row->row_id);
        
        return ($final_return) ? success($final_return) : error();

    }

    public function getintegritydetails(Request $request){
        $validator = Validator::make($request->all(), [
            'project_id' => 'required',
            'row_id' => 'required',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }

        // $integrityDetails = Integrity::where('row_id', $request->row_id)->where('project_id', $request->project_id)->with('workedBy')->first();
        // if(empty($integrityDetails)) 
        //     return error(Lang::get('validation.custom.invalid_row_id'));


        // $row_details = ImportData::select('file_id','row_details','task_status','integrity_status')->where('id',$request->row_id)->where('project_id', $request->project_id)->get();


        // $integrityDetails->row_details = json_decode($integrityDetails->answers);
        // if($integrityDetails->workedBy){
        //     $integrityDetails->owner_name = $integrityDetails->workedBy->firstname. ' ' . $integrityDetails->workedBy->lastname;
        //     unset($integrityDetails->workedBy);
        // }
        // $integrityDetails->file_id = $row_details[0]->file_id;
        // $integrityDetails->integrity_status = $row_details[0]->integrity_status;
        // $integrityDetails->task_status = $row_details[0]->task_status;
        // $integrityDetails->id = $integrityDetails->row_id;

        // $sheet_columns = ImportColumns::where('project_id', $request->project_id)->where('status', 1)->get();
        // if (empty($sheet_columns)) {
        //     return error(Lang::get('validation.custom.project_no_active_cols'));
        // }
        // $active_cols = $sheet_columns->pluck('column_heading')->toArray();
        // $investor_ref = '';
        // // echo "<pre>";print_r($row_details[0]->row_details);die;
        // foreach ($row_details[0]->row_details as $skey => $sval) {
        //     $investor_ref = ($investor_ref == '') ? $sval : $investor_ref;
        //     if (!in_array($skey, $active_cols))
        //         unset($row_details[0]->row_details->$skey);
        // }
        // $integrityDetails->investor_ref = $investor_ref;
        // $integrityDetails->row_details = $row_details[0]->row_details;
        // //
        // $check_user = $request->get('current_user');
        // $current_time = currenthumantime();
        // $check_integrity = Integrity::where('project_id', $request->project_id)->where('worked_by', $check_user->id)->where('status', 1)->first();

        

        // // echo "<pre>";print_r($check_integrity);die;
        // $is_integrity_exist = array();
        // if (!empty($check_integrity)) {
        //     $is_integrity_exist = $check_integrity;
        // } else if (!empty($check_second_integrity)) {
        //     $is_integrity_exist = $check_second_integrity;
        // }
        // if (!empty($is_integrity_exist)) {
        //     if (isset($is_integrity_exist->answers) && trim($is_integrity_exist->answers) != '') {
        //         $integrityDetails->prev_answers = json_decode($is_integrity_exist->answers);
        //     }
        // }
        // //
        // unset($integrityDetails->answers);
        // unset($integrityDetails->row_id);
        // return ($integrityDetails) ? success($integrityDetails) : error();


        $check_user = $request->get('current_user');
        $find_project = Project::find($request->project_id);
        if (!empty($find_project) && $find_project->status != 1 && $find_project->status != 2) {
            return error(Lang::get('validation.custom.project_not_active'));
        }
        $current_time = currenthumantime();

        $check_integrity = Integrity::where('row_id', $request->row_id)->where('project_id', $request->project_id)->with('workedBy')->first();

        // Get single row/task details
        if (!empty($check_integrity)) {
            //previous pending row exist
            $get_row = ImportData::find($check_integrity->row_id);
            if($check_integrity->status != '1' && $find_project->integrity_precentage_completed != '100'){
                // $check_integrity->last_modified_by = $check_user->id;
                $check_integrity->save();
            }
        } else {
            //Get new row
            $get_row = ImportData::where('project_id', $find_project->id)->where('id', $request->row_id)->first();
            if (empty($get_row)) {
                //all rows are completed,start from first again
                //get previous pending row if exist
                $check_second_integrity = Integrity::where('project_id', $find_project->id)->where('last_review_doneby', $check_user->id)->where('last_review_status', 1)->first();
                if (empty($check_second_integrity)) {
                    $check_second_integrity = Integrity::where('project_id', $find_project->id)->where('last_review_status', 0)->first();
                    if (empty($check_second_integrity)) {
                        //start from again if second review is also done
                        $update_tasks = Integrity::where('project_id', $find_project->id)->update(['last_review_status' => 0]);
                        $check_second_integrity = Integrity::where('project_id', $find_project->id)->where('last_review_status', 0)->first();
                    }
                }
                if($find_project->integrity_precentage_completed != '100'){
                    $check_second_integrity->last_review_status = 1;
                    $check_second_integrity->last_review_doneby = $check_user->id;
                    // $check_second_integrity->last_modified_by = $check_user->id;
                }
                
                $check_second_integrity->save();
                $get_row = ImportData::find($check_second_integrity->row_id);
            } else {
                $get_row->integrity_status = 1; //inprogress
                $get_row->save();
                // $add_newintegrity = Integrity::create(['project_id' => $find_project->id, 'row_id' => $get_row->id, 'status' => 1, 'record_owner' => $check_user->id,'last_review_doneby'=>$check_user->id, 'worked_by' => $check_user->id, 'worked_date' => $current_time]);
                $add_newintegrity = Integrity::create(['project_id' => $find_project->id, 'row_id' => $get_row->id, 'status' => 1, 'last_review_doneby'=>$check_user->id, 'worked_by' => $check_user->id, 'worked_date' => $current_time]);
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
        $get_row->project_name = $find_project->project_name;

        $last_row_id ='';
        $lastRowId = ImportData::select('id')->where('project_id',$find_project->id)->latest('id')->first();
        if($lastRowId)
            $last_row_id = $lastRowId->id;

        $get_row->last_row_id = $last_row_id;

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
}
