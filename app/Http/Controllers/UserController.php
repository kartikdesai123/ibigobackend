<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\interest;
use Carbon\Carbon;
use App\Admin;
use Validator;
use App\User;
use App\BusinessDetails;
use Twilio\Rest\Client;
use App\Verifyuser;
use Hash;
use Illuminate\Support\Str;
use App\Events\UserOnlineOffline;

class UserController extends Controller
{
    public function checkEmail(Request $request)
    {
        $rules = [
         'email' => 'required|email|unique:users,email',
        ];
        $messages=[];

        $validator = Validator::make($request->all(), $rules, $messages);
        if($validator->fails())
        {
            return response()->json([
                'message' => $validator->errors()->all(),
                'status_code' => 401,
                'status' => false
            ]);
        }else{
            return response()->json([
                'status_code' => 200,
                'status' => true
            ]);
        }
    }

    public function mobilelogin(Request $request)
    {
        $mobile_info = json_decode($request->input('mobile'));
        $mobile_otp = $request->input('otp');  
        $sid = 'AC1a84de8769de28a4ff2c4d2509f152dc';
        $token = '972e1a0d1990fa70417489d57dfb6ba8';
        $client = new Client($sid, $token);
        $otp = mt_rand(100000, 999999);
        $dialCode = str_replace("+", "", $mobile_info->dialCode);
        $number = str_replace(" ", "", $mobile_info->number);
        $number = str_replace("-", "", $number);
        $finduser = User::where('country_code',$dialCode)->where('mobile',$number)->get();
        if (count($finduser) > 0) {
            if ($mobile_otp!=null) {
                $saved_otp = $finduser[0]->mobile_otp;
                if($mobile_otp==$saved_otp){
                    $user = User::where('email', $finduser[0]->email)->first();
                    if ($user)
                    {
                        if($user->email_verified_at != NULL)
                        {
                            $token = '';
                            if ($user->user_api_token == NULL) {
                                $token = uniqid(base64_encode(Str::random(60)));
                                User::where('id',$user->id)->update(['user_api_token'=>$token]);    
                            }else{
                                $token = $user->user_api_token;
                            }
                            if ($user->user_type == 'normal') {
                                $user_name = $user->first_name;
                            }else{
                                $business_details = BusinessDetails::where('user_id',$user->id)->first();
                                $user_name = $business_details->business_name;
                            }
                            return response()->json([
                                'message' => 'User login successfully.',
                                'token' => $token,
                                'first_name' => $user_name,
                                'last_name' => $user->last_name,
                                'user_profile' => $user->user_profile,
                                'email' => $user->email,
                                'user_type' => $user->user_type,
                                'status_code' => 200, 
                                'status' => true
                            ]);
                            
                        }else
                        {
                            $response = "Verify your account.";
                            return response()->json([
                                            'message' => $response, 
                                            'status_code' => 422, 
                                            'status' => false
                                        ]);
                        }
                       
                    } else {
                        $response = 'User does not exist';
                        return response()->json([
                                        'message' => $response, 
                                        'status_code' => 422, 
                                        'status' => false
                                    ]);
                    }
                }else{
                    return response()->json([
                        'message' => 'OTP is incorrect.',
                        'status_code' => 404,
                        'status' => false
                    ]);
                }
            }else{ 
                // User::where('id',$finduser[0]->id)->update(['mobile_otp'=>$otp]);
                // $client->messages->create(
                //     $mobile_info->e164Number,
                //     [
                //         'from' => '+13342493769',
                //         'body' => 'The Secret OTP for Mobile Login is '.$otp.' Do not share OTP for security reason.'
                //     ]
                // );
                return response()->json([
                    'message' => 'OTP sent to your mobile.',
                    'status_code' => 200,
                    'status' => true
                ]);
            }
        }else{
            $response = 'User does not exist';
            return response()->json([
                            'message' => $response, 
                            'status_code' => 422, 
                            'status' => false
                        ]);
        }
    }

    public function create(Request $request){
		$rules = [
         'first_name' => 'required|string|max:50',
         'last_name' => 'required|string|max:50',
         'email' => 'required|email|unique:users,email',
         'mobile' => 'required',
         'gender' => 'required'
     	];
     	$messages=[];

     	$validator = Validator::make($request->all(), $rules, $messages);
     	if($validator->fails())
        {
            return response()->json([
            	'messages' => $validator->errors()->all(),
            	'status_code' => 401,
            	'status' => false
            ]);
        }else{
        	$now = Carbon::now();
            $unique_code = $now->format('mdYHisu');
        	$bday = Carbon::parse($request['birth_date']);
            $mobile_info = json_decode($request->input('mobile'));
            $dialCode = str_replace("+", "", $mobile_info->dialCode);
            $country_short_code = $mobile_info->countryCode;
            $number = str_replace(" ", "", $mobile_info->number);
            $number = str_replace("-", "", $number);
        	$insert_array=array(
                'unique_id' => $unique_code.mt_rand(100, 999),
        		'first_name'=>$request['first_name'],
        		'last_name'=>$request['last_name'],
                'user_slug'=> Str::slug($request['first_name'].' '.$request['last_name'], '-'),
        		'birth_date'=>$bday,
        		'gender'=>$request['gender'],
                'country_code'=>$dialCode,
                'country_short_code'=>$country_short_code,
        		'mobile'=>$number,
        		'email'=>$request['email'],
        		'user_interests'=>$request['user_interest'],
        		'password'=>bcrypt($request['password']),
        		'is_receive_commercial_email'=>($request['receive_email']==true)?1:0,
                'user_type' => 'normal',
        		'created_at'=>now(),
        		'updated_at'=>now(),
        		
        	);
            if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
            {
                $file = $request->file('file');
                $filename = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $picture = date('dmyHis').'-'.$filename;
                $file->move(public_path('user_profiles'), $picture);
                $insert_array['user_profile']=$picture;
            }
        	$user_details=User::create($insert_array);

        	if(!empty($user_details))
        	{
                $t = sha1(time());
                $verifyUser = VerifyUser::create([
                    'user_id' => $user_details->id,
                    'token' => $t
                ]);

                $url = 'https://ibigo.shadowis.nl/#/user/verify/'.$t;
                $to = $user_details->email;
                $subject = "Verify Email Address";
                $txt = '<html> 
                <head> 
                    <title>Welcome to IBIGO!</title> 
                </head> 
                <body style="margin-left:200px;"> 
                    <div>
                    <h3>Hello!</h3>
                    <br>
                    <p>Please click the button below to verify your email address.</p>
                    <br>
                    <a href="'.$url.'">Veriy Email Address</a>
                    <br>
                    <p>If you did not create an account, no further action is required.</p>
                    <p>Regards,</p>
                    <p>IBIGO</p>
                    </div>
                </body> 
                </html>';
                $headers = "MIME-Version: 1.0" . "\r\n"; 
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                
                mail($to,$subject,$txt,$headers);
                $message ='Registration done successfully. Please confirm your email.';
                $status_code = 201;
                $status = true;

        		return response()->json([
                	'messages' => $message,
                	'status_code' => $status_code,
                	'status' => $status
            	]);
        	}
        	else
        	{
        		return response()->json([
	            	'messages' => 'Something went wrong ! Please try again.',
	            	'status_code' => 401,
	            	'status' => false
            	]);
        	}
        } 
	}

	public function login(Request $request)
     {
        $user = User::where('email', $request->email)->first();
        if ($user)
        {
            if($user->email_verified_at != NULL)
            {
                if (Hash::check($request->password, $user->password)) {
                    
                    $token = '';
                    if ($user->user_api_token == NULL) {
                        $token = uniqid(base64_encode(Str::random(60)));
                        User::where('id',$user->id)->update(['user_api_token'=>$token,'user_status'=>1]);    
                    }else{
                        User::where('id',$user->id)->update(['user_status'=>1]);    
                        $token = $user->user_api_token;
                    }
                    broadcast(new UserOnlineOffline($user,1))->toOthers();
                    if ($user->user_type == 'normal') {
                        $user_name = $user->first_name;
                    }else{
                        $business_details = BusinessDetails::where('user_id',$user->id)->first();
                        $user_name = $business_details->business_name;
                    }
                    return response()->json([
                        'message' => 'User login successfully.',
                        'token' => $token,
                        'first_name' => $user_name,
                        'last_name' => $user->last_name,
                        'user_profile' => $user->user_profile,
                        'email' => $user->email,
                        'user_type' => $user->user_type,
                        'status_code' => 200, 
                        'status' => true
                    ]);
                } else {
                    $response = "Password missmatch";
                    return response()->json([
                                'message' => $response, 
                                'status_code' => 404, 
                                'status' => false
                            ]);
                }
            }else
            {
                $response = "Verify your account.";
                return response()->json([
                                'message' => $response, 
                                'status_code' => 422, 
                                'status' => false
                            ]);
            }
           
        } else {
            $response = 'User does not exist';
            return response()->json([
                            'message' => $response, 
                            'status_code' => 422, 
                            'status' => false
                        ]);
        }

    }

    public function verifyUser($token)
    {
      $verifyUser = VerifyUser::join('users','users.id','=','verify_users.user_id')->where('token', $token)->first();
      if(isset($verifyUser))
      {
            if($verifyUser->email_verified_at==NULL)
            {
                $user = User::find($verifyUser->user_id);
                $user->email_verified_at = Carbon::now();
                $user->save();
                $message = "Your e-mail is verified. You can now login.";
                $status_code = 200;
                $status = true;
            } 
            else
            {
              $message = "Your e-mail is already verified. You can now login.";
              $status_code = 200;
              $status = true;
            }
      } 
      else
      {
            $message = "Something Wrong"; 
            $status_code = 404;
            $status = false;
      }
      return response()->json([
                'message' => $message, 
                'status_code' => $status_code, 
                'status' => $status
            ]);
    }

    public function logout(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if($user) {
            $user_id = $user->id;
            User::where('id',$user_id)->update(['user_status'=>0]);
            broadcast(new UserOnlineOffline($user,0))->toOthers();
            return response()->json([
                'message' => 'User logged out.',
                'status_code' => 200,
                'status' => true,
            ]);
        }else{
            return response()->json([
                'message' => 'User not found.',
                'status_code' => 404,
                'status' => false,
            ]);
        }
    }
}
