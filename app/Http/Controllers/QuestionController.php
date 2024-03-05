<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Lang;
use App\Models\Questions;
use App\Models\Templates;

class QuestionController extends Controller
{
    // Get all Questions
    public function allquestions(Request $request)
    {
        $getdata = Questions::query();
        if ($request->has('status'))
            $getdata = $getdata->where('status', $request->status)->orderBy('order_no', 'ASC');
        else
            $getdata = $getdata->where('status', '!=', 0)->orderBy('order_no', 'ASC');
        if ($request->has('templateid') && $request->templateid != '')
            $getdata = $getdata->where('template_id', $request->templateid)->orderBy('order_no', 'ASC');
        if ($request->has('category') && $request->category != '')
            $getdata = $getdata->where('category', $request->category)->orderBy('order_no', 'ASC');
        $all_data = $getdata->get();
        return ($all_data) ? success($all_data) : error();
    }

    // View question details by ID
    public function viewquestion(Request $request, $questionid)
    {
        $finddata = Questions::find($questionid);
        return (!empty($finddata)) ? success($finddata) : error(Lang::get('validation.custom.invalid_questionid'));
    }

    //Add Question
    public function addquestion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|numeric|min:1|max:3',
            'export_heading' => 'required',
            'comment_required' => 'required|numeric|min:0|max:1',
            'question' => 'required',
            'choices' => 'required',
            'templateid' => 'sometimes|required|numeric|min:1|exists:App\Models\Templates,id',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $check_user = $request->get('current_user');
        $questionsCount = Questions::where('template_id', $request->templateid)->get();
        // echo $questionsCount->count();die;
        $insert_data = array(
            'category' => $request->category,
            'export_heading' => $request->export_heading,
            'comment_required' => $request->comment_required,
            'question' => $request->question,
            'choices' => json_encode($request->choices),
            'status' => 1,
            'template_id' => $request->templateid,
            'order_no' => ($questionsCount->count() + 1),
            'created_by' => $check_user->id,
        );
        $add_data = Questions::create($insert_data);
        if ($add_data) {
            addlog('Add', 'Question', Lang::get('validation.logs.questionadd_success', ['question' => $request->question, 'username' => $check_user->username]), $check_user->id);
            return success($add_data, Lang::get('validation.custom.question_add_success'));
        } else {
            addlog('Add', 'Question', Lang::get('validation.logs.questionadd_failed', ['question' => $request->question, 'username' => $check_user->username]), $check_user->id);
            return error(Lang::get('validation.custom.question_add_failed'));
        }
    }

    // Update Question
    public function updatequestion(Request $request, $questionid)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|numeric|min:1|max:3',
            'export_heading' => 'required',
            'comment_required' => 'required|numeric|min:0|max:1',
            'question' => 'required',
            'choices' => 'required',
            // 'order_no' => 'required',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $cur_data = Questions::find($questionid);
        if ($cur_data) {
            $check_user = $request->get('current_user');
            $update_data = array(
                'category' => $request->category,
                'export_heading' => $request->export_heading,
                'comment_required' => $request->comment_required,
                'question' => $request->question,
                'choices' => json_encode($request->choices),
                // 'order_no' => $request->order_no,
            );
            $update_db = Questions::where('id', $questionid)->update($update_data);
            if ($update_db) {
                addlog('Update', 'Question', Lang::get('validation.logs.questionupdate_success', ['question' => $request->question, 'username' => $check_user->username]), $check_user->id);
                $cur_data = Questions::find($questionid);
                return success($cur_data, Lang::get('validation.custom.question_update_success'));
            } else {
                addlog('Update', 'Question', Lang::get('validation.logs.questionupdate_failed', ['question' => $cur_data->question, 'username' => $check_user->username]), $check_user->id);
                return error(Lang::get('validation.custom.question_update_failed'));
            }
        } else {
            return error(Lang::get('validation.custom.invalid_questionid'));
        }
    }

    // Delete Question - Change question status to 0
    public function deletequestion(Request $request, $questionid)
    {
        $cur_data = Questions::find($questionid);
        if (!empty($cur_data)) {
            $check_user = $request->get('current_user');
            if ($request->has('permanentDelete') && $request->permanentDelete == 1) {
                // permenant delete
                $name = $cur_data->question;
                if ($cur_data->delete()) {
                    addlog('Permanent Delete', 'Question', Lang::get('validation.logs.permanentdelete_success', ['module' => 'Question', 'name' => $name, 'username' => $check_user->username]), $check_user->id);
                    return success([], Lang::get('validation.custom.permanent_delete_success', ['module' => 'Question', 'name' => $name]));
                } else {
                    addlog('Permanent Delete', 'Question', Lang::get('validation.logs.permanentdelete_failed', ['module' => 'question', 'name' => $name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.permanent_delete_failed', ['module' => 'question', 'name' => $name]));
                }
            } else {
                $cur_data->status = 0;
                if ($cur_data->save()) {
                    addlog('Delete', 'Question', Lang::get('validation.logs.questiondelete_success', ['question' => $cur_data->question, 'username' => $check_user->username]), $check_user->id);
                    return success([], Lang::get('validation.custom.question_delete_success'));
                } else {
                    addlog('Delete', 'Question', Lang::get('validation.logs.questiondelete_failed', ['question' => $cur_data->question, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.question_delete_failed'));
                }
            }
        } else {
            return error(Lang::get('validation.custom.invalid_questionid'));
        }
    }

    //Restore Question
    public function restorequestion(Request $request, $questionid)
    {
        $find_data = Questions::find($questionid);
        if (!empty($find_data)) {
            if ($find_data->status != 0) {
                return error(Lang::get('validation.custom.data_already_active', ['module' => 'Question']));
            } else {
                $check_user = $request->get('current_user');
                $find_data->status = 1;
                if ($find_data->save()) {
                    addlog('Restore', 'Question', Lang::get('validation.logs.questionrestore_success', ['question' => $find_data->question, 'username' => $check_user->username]), $check_user->id);
                    return success($find_data, Lang::get('validation.custom.data_restore_success', ['module' => 'Question']));
                } else {
                    addlog('Restore', 'Question', Lang::get('validation.logs.questionrestore_failed', ['question' => $find_data->question, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.data_restore_failed', ['module' => 'question']));
                }
            }
        } else {
            return error(Lang::get('validation.custom.invalid_investorid'));
        }
    }

    public function reorderquestions(Request $request){
        $validator = Validator::make($request->all(), [
            'question' => 'required|array',
            'template_id' => 'required|numeric|min:1|exists:App\Models\Templates,id',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        foreach ($request->question as $eachQuestion) {
            Questions::where('id', $eachQuestion['id'])
                    // ->where('template_id', $request->template_id)
                    ->update(['order_no'=>$eachQuestion['order_no']]);
        }
        return success([], Lang::get('validation.custom.reorder_success'));
    }
}
