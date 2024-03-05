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
use App\Models\Templates;
use App\Models\ImportColumns;



class ReviewController extends Controller
{
    // Get category-wise questions for review
    public function categoryquestions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'projectid' => 'required|numeric|min:1|exists:App\Models\Project,id',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $find_data = Project::find($request->projectid);
        $all_data = array();
        if (!empty($find_data)) {
            if ($find_data->template_id == NULL || $find_data->template_id == '') {
                return error(Lang::get('validation.custom.review_no_template'));
            }
            if ($find_data->template_id > 0) {
                $find_template = Templates::find($find_data->template_id);
                if (empty($find_template) || $find_template->status == 0) {
                    return error(Lang::get('validation.custom.review_no_template'));
                }
            }
            $getdata = Questions::where('status', '!=', 0)->where('template_id', $find_data->template_id)->get();
            if (!empty($getdata)) {
                foreach ($getdata as $sdata) {
                    if ($sdata->category == 1) {
                        $all_data['file_questions'][] = $sdata;
                    } elseif ($sdata->category == 2) {
                        $all_data['loan_questions'][] = $sdata;
                    } elseif ($sdata->category == 3) {
                        $all_data['delinquency_questions'][] = $sdata;
                    } else {
                    }
                }
                // $all_data['investor_reference'] = "14131";
                // $find_user = User::find($find_data->created_by);
                // $all_data['project_owner'] = "";
                // if (!empty($find_user))
                //     $all_data['project_owner'] = $find_user->firstname . ' ' . $find_user->lastname;
                return ($all_data) ? success($all_data) : error();
            } else {
                return error(Lang::get('validation.custom.review_no_questions'));
            }
        } else {
            return error(Lang::get('validation.custom.invalid_projectid'));
        }
    }


    // Get single row/task from project imported sheet
    public function getsinglerow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'projectid' => 'required|numeric|min:1|exists:App\Models\Project,id',
            'userid' => 'required|numeric|min:1|exists:App\Models\User,id',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $find_project = Project::find($request->projectid);
        $find_user = User::find($request->userid);
        if (!empty($find_project) && $find_project->status != 1 && $find_project->status != 2) {
            return error(Lang::get('validation.custom.project_not_active'));
        }
        if (!empty($find_user) && $find_user->status != 1) {
            return error(Lang::get('validation.custom.user_not_active'));
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

        $check_task = Task::where('project_id', $find_project->id)->where('worked_by', $find_user->id)->where('ownership', $find_user->id)->where('status', 1)->first();

        // Get single row/task details
        if (!empty($check_task)) {
            //previous pending row exist
            $get_row = ImportData::find($check_task->row_id);
            if($check_task->status != '1'){
                $check_task->last_modified_by = $find_user->id;
                $check_task->save();    
            }
        } else {
            //Get new row
            $get_row = ImportData::where('project_id', $find_project->id)->where('task_status', 0)->first();
            if (!empty($get_row)) {
                $get_row->task_status = 1; //inprogress
                $get_row->save();
                // $add_newtask = Task::create(['project_id' => $find_project->id, 'row_id' => $get_row->id, 'status' => 1, 'last_review_doneby'=> $find_user->id, 'worked_by' => $find_user->id, 'last_review_status'=> 1,'record_owner' => $find_user->id, 'ownership' => $find_user->id]);
                $add_newtask = Task::create(['project_id' => $find_project->id, 'row_id' => $get_row->id, 'status' => 1, 'last_review_doneby'=> $find_user->id, 'worked_by' => $find_user->id, 'last_review_status'=> 1, 'ownership' => $find_user->id]);
                if (!$add_newtask) {
                    return error(Lang::get('validation.custom.review_task_add_failed'));
                }
            } else {
                //all rows are completed,start from first again
                //get previous pending row if exist
                // $check_second_task = Task::where('project_id', $find_project->id)->where('last_review_doneby', $find_user->id)->where('last_review_status', 1)->first();
                $check_second_task = Task::where('project_id', $find_project->id)->where('last_review_status', 0)->first();
                if (empty($check_second_task)) {
                    // $check_second_task = Task::where('project_id', $find_project->id)->where('last_review_status', 0)->first();
                    // if (empty($check_second_task)) {
                        $check_second_task = Task::where('project_id', $find_project->id)->where('last_review_doneby', $find_user->id)->where('last_review_status', 3)->first();
                        if(empty($check_second_task)){
                            //start from again if second review is also done
                            $update_tasks = Task::where('project_id', $find_project->id)
                            ->where('last_review_status', 2)
                            // ->where(function($q){
                            //       $q->where('last_review_status', 2)
                            //         ->orWhere('last_review_status', 3);
                            //   })
                            // ->where('last_review_doneby',$find_user->id)
                            ->update(['last_review_status' => 0]);
                            $check_second_task = Task::where('project_id', $find_project->id)->where('last_review_status', 0)->first();
                            if (empty($check_second_task)) {
                                $check_second_task = Task::where('project_id', $find_project->id)->where('last_review_status', 3)->first(); 
                            }
                        }
                    // }
                }
                // $check_second_task->last_review_status = 1;
                // $check_second_task->last_review_doneby = $find_user->id;
                $check_second_task->save();
                $get_row = ImportData::find($check_second_task->row_id);

                if($find_project->percentage_completed != '100'){
                    $check_second_task->last_review_doneby = $find_user->id;
                    $check_second_task->worked_by = $find_user->id;
                    // $check_second_task->last_modified_by = $find_user->id;
                    $check_second_task->save();  
                }

            }
        }

        $is_task_exist = array();
        if (!empty($check_task)) {
            $is_task_exist = $check_task;
        } else if (!empty($check_second_task)) {
            $is_task_exist = $check_second_task;
        }

        $prev_answers = array();
        $all_questions = array();
        $getquestions = Questions::where('status', '!=', 0)->where('template_id', $find_project->template_id)->get();
        if (!empty($getquestions)) {
            if (!empty($is_task_exist) && isset($is_task_exist->answers) && trim($is_task_exist->answers) != '') {
                $prev_answers = json_decode($is_task_exist->answers);
            }
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



        //After confirmation,removed this logic as per mail in may 2-during review standard user can see all data
        /*
        $rowdata = $get_row->row_details;
        $sheet_columns = ImportColumns::where('project_id', $find_project->id)->where('status', 1)->get();
        if (empty($sheet_columns)) {
            return error(Lang::get('validation.custom.project_no_active_cols'));
        }
        $active_cols = $sheet_columns->pluck('column_heading')->toArray();
        foreach ($rowdata as $skey => $sval) {
            if (!in_array($skey, $active_cols))
                unset($rowdata->$skey);
        }
        // $get_row->rowdetails = $rowdata;
        // unset($get_row->row_details);
        $get_row->row_details = json_encode($rowdata);
        */

        $rowdata = $get_row->row_details;
        $sheet_columns = ImportColumns::where('project_id', $find_project->id)->where('review_status', 1)->get();
        if (empty($sheet_columns)) {
            return error(Lang::get('validation.custom.project_no_active_cols'));
        }
        $active_cols = $sheet_columns->pluck('column_heading')->toArray();
        foreach ($rowdata as $skey => $sval) {
            if (!in_array($skey, $active_cols))
                unset($rowdata->$skey);
        }
        // $get_row->rowdetails = $rowdata;
        // unset($get_row->row_details);
        $get_row->row_details = json_encode($rowdata);
        

        $get_row->last_row_id ='';
        $lastRowId = ImportData::select('id')->where('project_id',$find_project->id)->latest('id')->first();
        if($lastRowId)
            $get_row->last_row_id = $lastRowId->id;

        $get_row->Account_Reference = '';
        $Account_Reference = ImportData::select('row_details')->where('project_id',$find_project->id)->where('id',$get_row['id'])->first();
        // echo "<pre>";print_r($Account_Reference['row_details']);return;
        if($Account_Reference)
            $get_row->Account_Reference = $Account_Reference['row_details']->Account_Reference;
            // $get_row->Account_Reference = $get_row->row_details->Account_Reference;

        $get_row->owner_name = $find_user->firstname . ' ' . $find_user->lastname;
        if (!empty($is_task_exist)) {
            $find_owner = User::find($is_task_exist->ownership);
            $get_row->owner_name = $find_owner->firstname . ' ' . $find_owner->lastname;
        }
        if (!empty($is_task_exist)) {
            $get_row->grade = $is_task_exist->grade;
            $get_row->row_comment = $is_task_exist->comment;
        } else {
            $get_row->grade = '';
            $get_row->row_comment = '';
        }

        #review upper columns - show only ticked one
        $activeReviewheaders = ImportColumns::select('column_heading')->where('project_id',$find_project->id)->where('review_status','1')->get()->pluck('column_heading')->toArray();
        $presentRowHeaders = (array)$get_row->row_details;
        // echo "<pre>";print_r($activeReviewheadersToSend);return;
        // $activeReviewheadersToSend = array();
        // foreach($presentRowHeaders as $eachpresentRowHeaders){
        //     if(in_array($eachpresentRowHeaders, $activeReviewheaders))
        //         echo $eachpresentRowHeaders;
        //         array_push($activeReviewheadersToSend,$eachpresentRowHeaders);
        // }
        // echo "<pre>";print_r($activeReviewheadersToSend);return;

        $get_row->review_completed_percentage = $find_project->percentage_completed;
        $get_row->integrity_completed_percentage = $find_project->integrity_precentage_completed;
        $get_row->project_name = $find_project->project_name;
        $get_row->only_review = $find_project->only_review;

        $final_return = (object) array();
        $final_return->all_questions = $all_questions;
        $final_return->singel_row = $get_row;
        return ($final_return) ? success($final_return) : error();
    }

    // Save row/task details and answers
    public function saverowdetails(Request $request)
    {
        $validate_array = array(
            'projectid' => 'required|numeric|min:1|exists:App\Models\Project,id',
            'userid' => 'required|numeric|min:1|exists:App\Models\User,id',
            'rowid' => 'required|numeric|min:1|exists:App\Models\Task,row_id',
            // 'answers' => 'required|array',
            // 'grade' => 'required',
        );
        $validator = Validator::make($request->all(), $validate_array);
        if ($validator->fails()) {
            return error($validator->errors());
        }


        $find_project = Project::find($request->projectid);
        $find_user = User::find($request->userid);
        $find_task = Task::where('row_id', $request->rowid)->first();
        $current_time = currenthumantime();


        // Doing standard review of row
        $find_task->grade = $request->grade;
        $find_task->comment = ($request->has('row_comment') && trim($request->row_comment) != '') ? trim($request->row_comment) : NULL;
        $already_completed = 0;        

        if ($find_task->status == 2) {
            //already completed
            $already_completed = 1;
            $find_task->last_review_status = 2;
            $find_task->last_review_doneby = $find_user->id;
            $find_task->last_review_check_date = $current_time;
            if($request->grade =='' || $request->row_comment ==''){
                $find_task->status = 3;
            }
            $find_task->worked_by = $find_user->id;
        }

        // else {
        //    if($request->grade =='' || $request->row_comment ==''){
        //         $find_task->last_review_doneby = $find_user->id;
        //         $find_task->status = 3;
        //     }
        //     else{
        //         $find_task->status = 2;         //Completed
        //         $find_task->worked_date = $current_time;    
        //     }
        // }
        if($request->incomplete_status){
            if($request->incomplete_status && $find_task->last_review_status == '3'){
                $find_task->last_review_status = 2;
            }
            else{
                $find_task->last_review_status = 3;
            }
            $find_task->status = 3;
            $find_task->last_review_doneby = $find_user->id;
            $find_task->worked_by = $find_user->id;
        }
        else{
            $find_task->status = 2;
            $find_task->last_review_status = 2;
            $find_task->last_review_doneby = $find_user->id;
            $find_task->worked_by = $find_user->id;
        }

        $get_questions = Questions::where('status', '!=', 0)->where('template_id', $find_project->template_id)->get();
        // if (sizeof($request->answers) != $get_questions->count()) {
        //     return error(Lang::get('validation.custom.review_provide_all_answers'));
        // }
        
        if($request->has('answers') && $request->answers != ''){
            $find_task->answers = json_encode($request->answers);
        }
        else{
            $find_task->answers = '';
        }

        if ($request->has('review_status') && $request->review_status != 0 && $request->review_status != 1){
            if($find_task->record_owner =='')
                $find_task->record_owner = $find_user->id;
            else
                $find_task->last_modified_by = $find_user->id;
        }

        // echo $find_task->status;return;
        if($find_task->record_owner =='')
            $find_task->record_owner = $find_user->id;
        else
            $find_task->last_modified_by = $find_user->id;
        
        if ($find_task->save()) {

            


            if ($already_completed == 0 && $request->incomplete_status != 1) {
                $update_row = ImportData::where('id', $request->rowid)->update(['task_status' => 2]);
            }
            if($request->incomplete_status)
                $update_row = ImportData::where('id', $request->rowid)->update(['task_status' => 3]);

            // if ($find_project->percentage_completed != 100) {
                $count_task = Task::where('project_id', $find_project->id)->where('status', 2)->count();
                if ($count_task > 0) {
                    $find_file = Files::where('project_id', $find_project->id)->where('type', 'Import')->first();
                    $total_imported = $find_file->imported_rows;
                    if ($total_imported > 0) {
                        $project_percent = number_format(($count_task / ($total_imported - 1)) * 100, 2);
                        $find_project->percentage_completed = $project_percent;
                        $find_project->save();
                    }
                }
                else{
                    $find_project->percentage_completed = '0';
                    $find_project->save();
                }
            // }
            return success($find_task, Lang::get('validation.custom.review_task_update_success'));
        } else {
            return error(Lang::get('validation.custom.review_task_update_failed'));
        }
    }
}
