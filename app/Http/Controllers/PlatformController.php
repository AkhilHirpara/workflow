<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\Platform;
use App\Models\Project;

class PlatformController extends Controller
{
    // Get all Platforms
    public function allplatforms(Request $request)
    {
        $all_data = ($request->has('status')) ? Platform::where('status', $request->status)->get() : Platform::where('status', '!=', 0)->orderBy('name', 'asc')->get();
        return ($all_data) ? success($all_data) : error();
    }

    // View platform details by ID
    public function viewplatform(Request $request, $platformid)
    {
        $findplt = Platform::find($platformid);
        return (!empty($findplt)) ? success($findplt) : error(Lang::get('validation.custom.invalid_platformid'));
    }

    // Add new platform
    public function addplatform(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $is_exist = Platform::where('name', $request->name)->first();
        if (empty($is_exist)) {
            $check_user = $request->get('current_user');
            $insertdata = Platform::create(['name' => $request->name, 'created_by' => $check_user->id]);
            if ($insertdata) {
                addlog('Add', 'Platform', Lang::get('validation.logs.platformadd_success', ['name' => $request->name, 'username' => $check_user->username]), $check_user->id);
                return success($insertdata, Lang::get('validation.custom.platform_add_success'));
            } else {
                addlog('Add', 'Platform', Lang::get('validation.logs.platformadd_failed', ['name' => $request->name, 'username' => $check_user->username]), $check_user->id);
                return error(Lang::get('validation.custom.platform_add_failed'));
            }
        } else {
            if ($is_exist->status == 0)
                return error(Lang::get('validation.custom.platform_already_deleted'));
            else
                return error(Lang::get('validation.custom.platform_already_exist'));
        }
    }

    // Update platform details
    public function updateplatform(Request $request, $platformid)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
        ]);
        if ($validator->fails()) {
            return error($validator->errors());
        }
        $find_data = Platform::find($platformid);
        if ($find_data) {
            $is_exist = Platform::where('name', $request->name)->where('id', '!=', $platformid)->first();
            if (empty($is_exist)) {
                $find_data->name = $request->name;
                $check_user = $request->get('current_user');
                if ($find_data->save()) {
                    addlog('Update', 'Platform', Lang::get('validation.logs.platformupdate_success', ['name' => $find_data->name, 'username' => $check_user->username]), $check_user->id);
                    return success([], Lang::get('validation.custom.platform_update_success'));
                } else {
                    addlog('Update', 'Platform', Lang::get('validation.logs.platformupdate_failed', ['name' => $find_data->name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.platform_update_failed'));
                }
            } else {
                if ($is_exist->status == 0)
                    return error(Lang::get('validation.custom.platform_already_deleted'));
                else
                    return error(Lang::get('validation.custom.platform_already_exist'));
            }
        } else {
            return error(Lang::get('validation.custom.invalid_platformid'));
        }
    }

    // Delete Platform- Change platform status to 0 , If need to permenant delete,pass permanentDelete = 1
    public function deleteplatform(Request $request, $platformid)
    {
        $check_user = $request->get('current_user');
        $find_data = Platform::find($platformid);
        if (!empty($find_data)) {
            if ($request->has('permanentDelete') && $request->permanentDelete == 1) {
                // permenant delete
                $name = $find_data->name;
                if ($find_data->delete()) {
                    $update_prpject = Project::where('platform_id', $platformid)->update(['platform_id' => NULL]);
                    addlog('Permanent Delete', 'Platform', Lang::get('validation.logs.permanentdelete_success', ['module' => 'Platform', 'name' => $name, 'username' => $check_user->username]), $check_user->id);
                    return success([], Lang::get('validation.custom.permanent_delete_success', ['module' => 'Platform', 'name' => $name]));
                } else {
                    addlog('Permanent Delete', 'Platform', Lang::get('validation.logs.permanentdelete_failed', ['module' => 'platform', 'name' => $name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.permanent_delete_failed', ['module' => 'platform', 'name' => $name]));
                }
            } else {
                $find_data->status = 0;
                if ($find_data->save()) {
                    addlog('Delete', 'Platform', Lang::get('validation.logs.platformdelete_success', ['name' => $find_data->name, 'username' => $check_user->username]), $check_user->id);
                    return success([], Lang::get('validation.custom.platform_delete_success'));
                } else {
                    addlog('Delete', 'Platform', Lang::get('validation.logs.platformdelete_failed', ['name' => $find_data->name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.platform_delete_failed'));
                }
            }
        } else {
            return error(Lang::get('validation.custom.invalid_platformid'));
        }
    }

    //Restore Platform
    public function restoreplatform(Request $request, $platformid)
    {
        $find_data = Platform::find($platformid);
        if (!empty($find_data)) {
            if ($find_data->status != 0) {
                return error(Lang::get('validation.custom.data_already_active', ['module' => 'Platform']));
            } else {
                $check_user = $request->get('current_user');
                $find_data->status = 1;
                if ($find_data->save()) {
                    addlog('Restore', 'Platform', Lang::get('validation.logs.platformrestore_success', ['name' => $find_data->name, 'username' => $check_user->username]), $check_user->id);
                    return success($find_data, Lang::get('validation.custom.data_restore_success', ['module' => 'Platform']));
                } else {
                    addlog('Restore', 'Platform', Lang::get('validation.logs.platformrestore_failed', ['name' => $find_data->name, 'username' => $check_user->username]), $check_user->id);
                    return error(Lang::get('validation.custom.data_restore_failed', ['module' => 'platform']));
                }
            }
        } else {
            return error(Lang::get('validation.custom.invalid_platformid'));
        }
    }
}
