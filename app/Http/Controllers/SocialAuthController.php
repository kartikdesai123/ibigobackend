<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SocialAuthService;
use Socialite;
use App\SocialLogin;
use App\User;
use Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SocialAuthController extends Controller
{
    public function login(Request $request)
    {
        $user = SocialLogin::where('provider_id',$request->input('id'))->get();
        $now = Carbon::now();
        $unique_code = $now->format('mdYHisu');
        $token = uniqid(base64_encode(Str::random(60)));
        if (count($user) > 0) {
            $user_details = User::where('id',$user[0]->user_id)->first();
            User::where('id',$user[0]->user_id)->update(['user_api_token'=>$token]);
            $user_name = $user_details->first_name;
            return response()->json([
                'message' => 'User login successfully.',
                'token' => $token,
                'first_name' => $user_name,
                'last_name' => $user_details->last_name,
                'user_profile' => $user_details->user_profile,
                'email' => $user_details->email,
                'user_type' => $user_details->user_type,
                'status_code' => 200, 
                'status' => true
            ]);
        }else{
            $name_array = explode(" ", $request->input('name'));
            $first_name = $name_array[0];
            unset($name_array[0]);
            $last_name = implode(" ", $name_array);
            $insert_array=array(
                'unique_id' => $unique_code.mt_rand(100, 999),
                'user_slug'=> Str::slug($first_name.' '.$last_name, '-'),
                'first_name' => $first_name,
                'last_name' => $last_name,
                'user_profile' => $request->input('image'),
                'user_type'=> 'normal'
            );
            if ($request->input('email')!='undefined') {
                $insert_array['email'] = $request->input('email');
            }
            $user_details=User::create($insert_array);
            User::where('id',$user_details->id)->update(['user_api_token'=>$token]);
            $social_array=array(
                'user_id' => $user_details->id,
                'provider_id' => $request->input('id'),
                'provider_name' =>$request->input('name')
            );
            $social_details=SocialLogin::create($social_array);
            $user_name = $user_details->first_name;
            return response()->json([
                'message' => 'User login successfully.',
                'token' => $token,
                'first_name' => $user_name,
                'last_name' => $user_details->last_name,
                'user_profile' => $user_details->user_profile,
                'email' => $user_details->email,
                'user_type' => $user_details->user_type,
                'status_code' => 200, 
                'status' => true
            ]);
        }
    }

    public function callback(SocialAuthService $service)
    {
    	$user = $service->createOrGetUser(Socialite::driver('facebook')->user());
    	//auth()->login($user);
    	return response()->json('user',$user);
    	//return response()
        //return redirect()->to('/home');
    }
}
