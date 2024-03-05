<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Lang;
use Illuminate\Contracts\Encryption\DecryptException;
use App\Models\User;

class StandardUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */

    public $attributes;

    public function handle(Request $request, Closure $next)
    {
        try {
            $authtoken = $request->header('authtoken');
            $userid = Crypt::decrypt($authtoken);
            $current_user = User::where('id',$userid)->where('is_loggedin', 1)->first();
            $message = '';
            if ((!empty($current_user))) {
                $request->attributes->add(['current_user' => $current_user]);
                if ($current_user->status == 0) {
                    $message = Lang::get('validation.custom.account_deleted');
                } else if ($current_user->status == 2) {
                    $message =  Lang::get('validation.custom.account_inactive');
                } else if (!in_array($current_user->role, array(1, 2))) {
                    $message =  Lang::get('validation.custom.unauthorized_access');
                }
            } else {
                $message = Lang::get('validation.custom.already_loggedout');
            }
            if ($message != '') {
                return error($message);
            } else {
                return $next($request);
            }
        } catch (DecryptException $e) {
            $message =  Lang::get('validation.custom.unauthorized_access');
            return error($message);
        }
    }
}
