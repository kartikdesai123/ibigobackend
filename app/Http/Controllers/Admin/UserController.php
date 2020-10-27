<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\interest;
use Carbon\Carbon;
use App\Admin;
use App\Verifyuser;
use Hash;
use Validator;
use App\User;
use Illuminate\Support\Str;
class UserController extends Controller
{
	public function getInterests()
	{
		//$token = $request->header('Authorization');
    	
		$interests = interest::all();
		return response()->json([
		            	'interest_details' => $interests,
		            	'status_code' => 201,
		            	'status' => true
		            	]);
	}

	public function create(Request $request){
		$token = $request->header('Authorization');
    	$user = Admin::where('api_token',$token)->first();
    	if($user)
    	{
    		$rules = [
             	'email' => 'unique:users,email',
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
	        	if($request->hasFile('file')){
		        	$file = $request->file('file');
				    $filename = $file->getClientOriginalName();
				    $extension = $file->getClientOriginalExtension();
				    $picture = date('dmyHis').'-'.$filename;
				    $file->move(public_path('user_profiles'), $picture);
		        }	
	        	
	        	if($request->hasFile('cover')){
				    $cover = $request->file('cover');
		            $covername = $cover->getClientOriginalName();
		            $coverextension = $cover->getClientOriginalExtension();
		            $coverpicture = date('dmyHis').'-'.$covername;
		            $cover->move(public_path('user_cover'), $coverpicture);
		        }
		        $country_short_code = null;
		        $dialCode = null;
		        $number = null;
		        $coverpicture =null;
		        $picture = null;
		        $bday = null;
		        if ($request->input('birth_date')!='undefined') {
		        	$bday = Carbon::parse($request['birth_date']);
		        }
		        
		        if ($request->input('mobile')!='null') {
		        	$mobile_info = json_decode($request->input('mobile'));
		            $dialCode = str_replace("+", "", $mobile_info->dialCode);
		            $country_short_code = $mobile_info->countryCode;
		            $number = str_replace(" ", "", $mobile_info->number);
		            $number = str_replace("-", "", $number);
		        }
	            $now = Carbon::now();
	            //$unique_code = Carbon::createFromFormat('mdYHisu', $now);
	            $unique_code = $now->format('mdHiYsu');
	        	$insert_array=array(
	        		'unique_id' => $unique_code.mt_rand(100, 999),
	        		'first_name'=>$request['first_name'],
	        		'last_name'=>$request['last_name'],
	        		'user_slug'=> Str::slug($request['first_name'].' '.$request['last_name'], '-'),
	        		'birth_date'=>$bday,
	        		'user_profile'=>$picture,
	        		'user_cover'=>$coverpicture,
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
	        	$user_details=User::create($insert_array);

	        	if(!empty($user_details))
	        	{
	        		if ($request->input('email')!='null') {
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
	        		}
	        		return response()->json([
		            	'messages' => 'User is Added Successfully.',
		            	'status_code' => 201,
		            	'status' => true
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
    	else
    	{
    		 return response()->json([
	            	'messages' => 'Unauthenticate User',
	            	'status_code' => 401,
	            	'status' => false
	            ]);

    	}
	}

	public function list(Request $request)
    {
    	$token = $request->header('Authorization');
    	$user = Admin::where('api_token',$token)->first();
    	if($user)
    	{
    		$list=User::where('user_type','normal')->get();

    		return response()->json([
		            	'users_details' => $list,
		            	'status_code' => 201,
		            	'status' => true
		            	], 201);
    	}
    	else
    	{
    		 return response()->json([
	            	'error' => 'Unauthenticate User',
	            	'status_code' => 401,
	            	'status' => false
	            ], 401);
    	}
    }

    public function edit(Request $request,$id){
    	$token = $request->header('Authorization');
    	$user = Admin::where('api_token',$token)->first();
    	if($user)
    	{
    		 $check_details=User::find($id);

    		if(!empty($check_details))
    		{
    			
    			return response()->json([
		            	'user' => $check_details,
		            	'status_code' => 201,
		            	'status' => true
		            	], 201);
			}
    		else
    		{
    			return response()->json([
	            	'error' => 'Record is not found',
	            	'status_code' => 401,
	            	'status' => false
	            ], 401);
    		}
    	}
    	else
    	{
    		 return response()->json([
	            	'error' => 'Unauthenticate User',
	            	'status_code' => 401,
	            	'status' => false
	            ], 401);
    	}
    }

    public function update(Request $request,$id){
    	$token = $request->header('Authorization');
    	$user = Admin::where('api_token',$token)->first();
    	if($user)
    	{
    		$check_details=User::find($id);

    		if(!empty($check_details))
    		{
    			$rules = [
	             'first_name' => 'required|string|max:50',
	             'last_name' => 'required|string|max:50',
	             'email' => 'required|email',
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
		            ], 401);
		        }
		        else
		        {	

	        	
		        	$picture='';
		        	if ($request->hasFile('file')) {
		        		$file = $request->file('file');
					    $filename = $file->getClientOriginalName();
					    $extension = $file->getClientOriginalExtension();
					    $picture = date('dmyHis').'-'.$filename;
					    $file->move(public_path('user_profiles'), $picture);
		        	}
		        	$coverpicture='';
                    if ($request->hasFile('cover')) {
                        $cover = $request->file('cover');
                        $covername = $cover->getClientOriginalName();
                        $coverextension = $cover->getClientOriginalExtension();
                        $coverpicture = date('dmyHis').'-'.$covername;
                        $cover->move(public_path('user_cover'), $coverpicture);
                    }

		        	$bday = Carbon::parse($request['birth_date']);
		        	$mobile_info = json_decode($request->input('mobile'));
		            $dialCode = str_replace("+", "", $mobile_info->dialCode);
		            $country_short_code = $mobile_info->countryCode;
		            $number = str_replace(" ", "", $mobile_info->number);
		            $number = str_replace("-", "", $number);
		        	$update_array=array(
		        		'first_name'=>$request['first_name'],
		        		'last_name'=>$request['last_name'],
		        		'birth_date'=>$bday,
		        		'country_code'=>$dialCode,
		                'country_short_code'=>$country_short_code,
		        		'mobile'=>$number,
		        		'gender'=>$request['gender'],
		        		'email'=>$request['email'],
		        		'user_interests'=>$request['user_interest'],
		        		'is_receive_commercial_email'=>0,
		        	);
		        	if(isset($request['password']) && $request['password']!=''){
		        		$update_array['password'] = bcrypt($request['password']);
		        	}
		        	if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
		        	{
		        		$update_array['user_profile']=$picture;
		        	}
		        	if(isset($request['cover']) && $request['cover']!='' && $request->hasFile('cover'))
                    {
                        $update_array['user_cover']=$coverpicture;
                    }
                    

		        	User::where('id',$id)->update($update_array);

		        	$insert_details=User::find($id);

		        	if(!empty($insert_details))
		        	{
		        		return response()->json([
		            	'messages' => 'User updated successfully.',
		            	'status_code' => 201,
		            	'status' => true
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
    		else
    		{
    			return response()->json([
	            	'error' => 'Record is not found',
	            	'status_code' => 401,
	            	'status' => false
	            ]);
    		}
    	}
    	else
    	{
    		 return response()->json([
	            	'error' => 'Unauthenticate User',
	            	'status_code' => 401,
	            	'status' => false
	            ], 401);

    	}
    }

    public function delete(Request $request,$id){
    	$token = $request->header('Authorization');
    	$user = Admin::where('api_token',$token)->first();
    	if($user)
    	{
    		 $check_details=User::find($id);

    		if(!empty($check_details))
    		{
    			$check_details->delete();
    			
    			return response()->json([
		            	'message' => 'Successfully Record deleted. ',
		            	'status_code' => 201,
		            	'status' => true
		            	], 201);
			}
    		else
    		{
    			return response()->json([
	            	'message' => 'Record is not found',
	            	'status_code' => 401,
	            	'status' => false
	            ], 401);
    		}
    	}
    	else
    	{
    		 return response()->json([
	            	'message' => 'Unauthenticate User',
	            	'status_code' => $token,
	            	'status' => false
	            ], 401);
    	}
    }
}
