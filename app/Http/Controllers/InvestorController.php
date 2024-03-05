<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\Investor;
use App\Models\Project;

class InvestorController extends Controller
{
    // Get all Investors
    public function allinvestors(Request $request)
    {
        $all_data = ($request->has('status')) ? Investor::where('status', $request->status)->get() : Investor::where('status', '!=', 0)->orderBy('name', 'asc')->get();
        return ($all_data) ? success($all_data) : error();
    }

    // View investor details by ID
    public function viewinvestor(Request $request, $investorid)
    {
        $findinvestor = Investor::find($investorid);
        return (!empty($findinvestor)) ? success($findinvestor) : error(Lang::get('validation.custom.invalid_investorid'));
    }

    // Add new investor
    public function addinvestor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $is_exist = Investor::where('name', $request->name)->first();
        if (empty($is_exist)) {
            $check_user = $request->get('current_user');
            $insertdata = Investor::create(['name' => $request->name, 'created_by' => $check_user->id]);
            if ($insertdata) {
                addlog('Add', 'Investor', Lang::get('validation.logs.investoradd_success', ['name' => $request->name, 'username' => $check_user->username]), $check_user->id);
                return success($insertdata, Lang::get('validation.custom.investor_add_success'));
            } else {
                addlog('Add', 'Investor', Lang::get('validation.logs.investoradd_failed', ['name' => $request->name, 'username' => $check_user->username]), $check_user->id);
                return error(Lang::get('validation.custom.investor_add_failed'));
            }
        } else {
            if ($is_exist->status == 0)
                return error(Lang::get('validation.custom.investor_already_deleted'));
            else
                return error(Lang::get('validation.custom.investor_already_exist'));
        }
    }

    // Update investor details
    public function updateinvestor(Request $request, $investorid)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $find_data = Investor::find($investorid);
        if ($find_data) {
            $is_exist = Investor::where('name', $request->name)->where('id', '!=', $investorid)->first();
            if (empty($is_exist)) {
                $find_data->name = $request->name;
                $check_user = $request->get('current_user');
                if ($find_data->save()) {
                    addlog('Update', 'Investor', Lang::get('validation.logs.investorupdate_success', ['name' => $find_data->name, 'username' => $check_user->username]), $check_user->id);
                    return success($find_data, Lang::get('validation.custom.investor_update_success'));
                } else {
                    addlog('Update', 'Investor', Lang::get('validation.logs.investorupdate_failed', ['name' => $find_data->name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.investor_update_failed'));
                }
            } else {
                if ($is_exist->status == 0)
                    return error(Lang::get('validation.custom.investor_already_deleted'));
                else
                    return error(Lang::get('validation.custom.investor_already_exist'));
            }
        } else {
            return error(Lang::get('validation.custom.invalid_investorid'));
        }
    }

    // Delete Investor- Change investor status to 0 , If need to permenant delete,pass permanentDelete = 1
    public function deleteinvestor(Request $request, $investorid)
    {

        $check_user = $request->get('current_user');
        $cur_data = Investor::find($investorid);
        if (!empty($cur_data)) {
            if ($request->has('permanentDelete') && $request->permanentDelete == 1) {
                // permenant delete
                $name = $cur_data->name;
                if ($cur_data->delete()) {
                    $update_prpject = Project::where('investor_id', $investorid)->update(['investor_id' => NULL]);
                    addlog('Permanent Delete', 'Investor', Lang::get('validation.logs.permanentdelete_success', ['module' => 'Investor', 'name' => $name, 'username' => $check_user->username]), $check_user->id);
                    return success([], Lang::get('validation.custom.permanent_delete_success', ['module' => 'Investor', 'name' => $name]));
                } else {
                    addlog('Permanent Delete', 'Investor', Lang::get('validation.logs.permanentdelete_failed', ['module' => 'investor', 'name' => $name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.permanent_delete_failed', ['module' => 'investor', 'name' => $name]));
                }
            } else {
                $cur_data->status = 0;
                if ($cur_data->save()) {
                    addlog('Delete', 'Investor', Lang::get('validation.logs.investordelete_success', ['name' => $cur_data->name, 'username' => $check_user->username]), $check_user->id);
                    return success([], Lang::get('validation.custom.investor_delete_success'));
                } else {
                    addlog('Delete', 'Investor', Lang::get('validation.logs.investordelete_failed', ['name' => $cur_data->name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.investor_delete_failed'));
                }
            }
        } else {
            return error(Lang::get('validation.custom.invalid_investorid'));
        }
    }

    //Restore Investor
    public function restoreinvestor(Request $request, $investorid)
    {
        $find_data = Investor::find($investorid);
        if (!empty($find_data)) {
            if ($find_data->status != 0) {
                return error(Lang::get('validation.custom.data_already_active', ['module' => 'Investor']));
            } else {
                $check_user = $request->get('current_user');
                $find_data->status = 1;
                if ($find_data->save()) {
                    addlog('Restore', 'Investor', Lang::get('validation.logs.investorrestore_success', ['name' => $find_data->name, 'username' => $check_user->username]), $check_user->id);
                    return success($find_data, Lang::get('validation.custom.data_restore_success', ['module' => 'Investor']));
                } else {
                    addlog('Restore', 'Investor', Lang::get('validation.logs.investorrestore_failed', ['name' => $find_data->name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.data_restore_failed', ['module' => 'investor']));
                }
            }
        } else {
            return error(Lang::get('validation.custom.invalid_investorid'));
        }
    }
}
