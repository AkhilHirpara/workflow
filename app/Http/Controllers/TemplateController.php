<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Lang;
use App\Models\Templates;
use App\Models\Questions;
use App\Models\Project;
use App\Models\Task;

class TemplateController extends Controller
{
    // Get all Templates
    public function alltemplates(Request $request)
    {
        $all_data = ($request->has('status')) ? Templates::where('status', $request->status)->get() : Templates::where('status', '!=', 0)->orderBy('name', 'asc')->get();
        foreach ($all_data as $sdata) {
            $sdata->projectdetails = Project::where('template_id', $sdata->id)->first();
            $sdata->projectname = (!empty($sdata->projectdetails)) ? $sdata->projectdetails->project_name : '';
            $sdata->totalquestions = sizeof($sdata->relatedQuestions);
            unset($sdata->relatedQuestions);
        }
        return ($all_data) ? success($all_data) : error();
    }

    // View template details by ID
    public function viewtemplate(Request $request, $templateid)
    {
        $finddata = Templates::find($templateid);
        if (!empty($finddata)) {
            $finddata->projectdetails = Project::where('template_id', $finddata->id)->first();
            $finddata->projectname = (!empty($finddata->projectdetails)) ? $finddata->projectdetails->project_name : '';
            $finddata->relatedQuestions;
            $finddata->relatedActiveQuestions;
            return success($finddata);
        } else {
            return error(Lang::get('validation.custom.invalid_templateid'));
        }
    }

    //Add Template
    public function addtemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $is_exist = Templates::where('name', $request->name)->first();
        if (empty($is_exist)) {
            $check_user = $request->get('current_user');
            $add_data = Templates::create(['name' => $request->name, 'status' => 1, 'created_by' => $check_user->id]);
            if ($add_data) {
                addlog('Add', 'Template', Lang::get('validation.logs.templateadd_success', ['template' => $request->name, 'username' => $check_user->username]), $check_user->id);
                return success($add_data, Lang::get('validation.custom.template_add_success'));
            } else {
                addlog('Add', 'Template', Lang::get('validation.logs.templateadd_failed', ['template' => $request->name, 'username' => $check_user->username]), $check_user->id);
                return error(Lang::get('validation.custom.template_add_failed'));
            }
        } else {
            if ($is_exist->status == 0)
                return error(Lang::get('validation.custom.template_already_deleted'));
            else
                return error(Lang::get('validation.custom.template_already_exist'));
        }
    }

    // Update Template
    public function updatetemplate(Request $request, $templateid)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $cur_data = Templates::find($templateid);
        if ($cur_data) {
            $is_exist = Templates::where('name', $request->name)->where('id', '!=', $templateid)->first();
            if (empty($is_exist)) {
                $check_user = $request->get('current_user');
                $cur_data->name = $request->name;
                if ($cur_data->save()) {
                    addlog('Update', 'Template', Lang::get('validation.logs.templateupdate_success', ['template' => $request->name, 'username' => $check_user->username]), $check_user->id);
                    return success($cur_data, Lang::get('validation.custom.template_update_success'));
                } else {
                    addlog('Update', 'Template', Lang::get('validation.logs.templateupdate_failed', ['template' => $cur_data->name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.template_update_failed'));
                }
            } else {
                if ($is_exist->status == 0)
                    return error(Lang::get('validation.custom.template_already_deleted'));
                else
                    return error(Lang::get('validation.custom.template_already_exist'));
            }
        } else {
            return error(Lang::get('validation.custom.invalid_templateid'));
        }
    }

    // Delete Template - Change template status to 0
    public function deletetemplate(Request $request, $templateid)
    {
        $cur_data = Templates::find($templateid);
        if (!empty($cur_data)) {
            $check_user = $request->get('current_user');
            if ($request->has('permanentDelete') && $request->permanentDelete == 1) {
                // permenant delete
                $name = $cur_data->name;
                if ($cur_data->delete()) {
                    $projectIds = Project::where('template_id', $templateid)->get()->pluck('id')->toArray();
                    // echo '<pre>';print_r($projectIds);return;
                    Task::whereIn('project_id',$projectIds)->update(['status' => '0']);
                    Project::where('template_id', $templateid)->update(['template_id' => null,'percentage_completed' => '0', 'integrity_precentage_completed' => '0']);

                    addlog('Permanent Delete', 'Template', Lang::get('validation.logs.permanentdelete_success', ['module' => 'Template', 'name' => $name, 'username' => $check_user->username]), $check_user->id);
                    return success([], Lang::get('validation.custom.permanent_delete_success', ['module' => 'Template', 'name' => $name]));
                } else {
                    addlog('Permanent Delete', 'Template', Lang::get('validation.logs.permanentdelete_failed', ['module' => 'Template', 'name' => $name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.permanent_delete_failed', ['module' => 'Template', 'name' => $name]));
                }
            } else {
                $cur_data->status = 0;
                if ($cur_data->save()) {
                    addlog('Delete', 'Template', Lang::get('validation.logs.templatedelete_success', ['template' => $cur_data->name, 'username' => $check_user->username]), $check_user->id);
                    return success($cur_data, Lang::get('validation.custom.template_delete_success'));
                } else {
                    addlog('Delete', 'Template', Lang::get('validation.logs.templatedelete_failed', ['template' => $cur_data->name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.template_delete_failed'));
                }
            }
        } else {
            return error(Lang::get('validation.custom.invalid_templateid'));
        }
    }

    // Temporary and Permanent Delete multiple templates
    public function multidelete(Request $request){
        $validator = Validator::make($request->all(), [
            'templateid' => 'required',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $successMsgs = $errorMsgs =  $curdatas = [];
        foreach($request->templateid as $eachTemplate){
            $cur_data = Templates::find($eachTemplate);
            if (!empty($cur_data)) {
                // $curdatas[] = $cur_data;
                $check_user = $request->get('current_user');
                if ($request->has('permanentDelete') && $request->permanentDelete == 1) {
                    // permenant delete
                    $name = $cur_data->name;
                    if ($cur_data->delete()) {
                        $projectIds = Project::where('template_id', $eachTemplate)->get()->pluck('id')->toArray();
                        Task::whereIn('project_id',$projectIds)->update(['status' => '0']);
                        Project::where('template_id', $eachTemplate)->update(['template_id' => null,'percentage_completed' => '0', 'integrity_precentage_completed' => '0']);

                        addlog('Permanent Delete', 'Template', Lang::get('validation.logs.permanentdelete_success', ['module' => 'Template', 'name' => $name, 'username' => $check_user->username]), $check_user->id);
                        $successMsgs[] = Lang::get('validation.custom.permanent_delete_success', ['module' => 'Template', 'name' => $name]);
                        // return success([], Lang::get('validation.custom.permanent_delete_success', ['module' => 'Template', 'name' => $name]));
                    } else {
                        addlog('Permanent Delete', 'Template', Lang::get('validation.logs.permanentdelete_failed', ['module' => 'Template', 'name' => $name, 'username' => $check_user->username]), $check_user->id);
                        $errorMsgs[] = Lang::get('validation.custom.permanent_delete_failed', ['module' => 'Template', 'name' => $name]);
                        // return error(Lang::get('validation.custom.permanent_delete_failed', ['module' => 'Template', 'name' => $name]));
                    }
                } else {
                    $cur_data->status = 0;
                    if ($cur_data->save()) {
                        addlog('Delete', 'Template', Lang::get('validation.logs.templatedelete_success', ['template' => $cur_data->name, 'username' => $check_user->username]), $check_user->id);
                        $successMsgs[] = Lang::get('validation.custom.templatedelete_success', ['module' => 'Template', 'name' => $cur_data->name]);
                        // return success($cur_data, Lang::get('validation.custom.template_delete_success'));
                    } else {
                        addlog('Delete', 'Template', Lang::get('validation.logs.templatedelete_failed', ['template' => $cur_data->name, 'username' => $check_user->username]), $check_user->id);
                        $errorMsgs[] = Lang::get('validation.custom.templatedelete_failed', ['module' => 'Template', 'name' => $cur_data->name]);
                        // return error(Lang::get('validation.custom.template_delete_failed'));
                    }
                }
            } else {
                $errorMsgs[] = Lang::get('validation.custom.invalid_templateid : '. $eachTemplate);
                // return error(Lang::get('validation.custom.invalid_templateid'));
            }
        }
        if(count($errorMsgs) == 0){
            return success($successMsgs, Lang::get('validation.custom.template_delete_success'));
        }
        else{
            
            return error(array_merge($successMsgs,$errorMsgs), Lang::get('validation.custom.template_delete_failed'));
        }
    }

    //Restore Template
    public function restoretemplate(Request $request, $templateid)
    {
        $find_data = Templates::find($templateid);
        if (!empty($find_data)) {
            if ($find_data->status != 0) {
                return error(Lang::get('validation.custom.data_already_active', ['module' => 'Template']));
            } else {
                $check_user = $request->get('current_user');
                $find_data->status = 1;
                if ($find_data->save()) {
                    addlog('Restore', 'Template', Lang::get('validation.logs.templaterestore_success', ['name' => $find_data->name, 'username' => $check_user->username]), $check_user->id);
                    return success($find_data, Lang::get('validation.custom.data_restore_success', ['module' => 'Template']));
                } else {
                    addlog('Restore', 'Template', Lang::get('validation.logs.templaterestore_failed', ['name' => $find_data->name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.data_restore_failed', ['module' => 'template']));
                }
            }
        } else {
            return error(Lang::get('validation.custom.invalid_templateid'));
        }
    }

    // Assign questions to template
    public function assignquestions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'templateid' => 'required|numeric|min:1|exists:App\Models\Templates,id',
            'questionids' => 'required|array',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $check_user = $request->get('current_user');
        $add_all = array();
        $current_time = currenthumantime();
        foreach ($request->questionids as $qid) {
            if (is_numeric($qid)) {
                $cur_question = Questions::find($qid);
                if (!empty($cur_question) && ($cur_question->template_id != $request->templateid)) {
                    $is_exist = Questions::where('question', '=', $cur_question->question)->where('template_id', $request->templateid)->first();
                    if (empty($is_exist)) {
                        $add_all[] = array(
                            'category' => $cur_question->category,
                            'export_heading' => $cur_question->export_heading,
                            'comment_required' => $cur_question->comment_required,
                            'question' => $cur_question->question,
                            'choices' => json_encode($cur_question->choices),
                            'status' => 1,
                            'template_id' => $request->templateid,
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
        $finddata = Templates::find($request->templateid);
        if (!empty($finddata)) {
            $projectdetails = Project::where('template_id', $finddata->id)->first();
            $finddata->projectdetails = ($projectdetails) ? $projectdetails->toArray() : [];
            $finddata->relatedQuestions;
            return success($finddata, Lang::get('validation.custom.template_question_assigned'));
        } else {
            return error(Lang::get('validation.custom.invalid_templateid'));
        }
    }
}
