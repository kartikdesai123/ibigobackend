<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Admin;
use App\SpotDetail;
use App\FriendRelation;
use Validator;
use App\User;

class UserUpdateController extends Controller
{
	public function changeInterest(Request $request,$changeid)
	{
		$token = $request->header('Authorization');
		$user = User::where('user_api_token',$token)->first();
		if($user)
    	{
    		$check_details=User::find($user->id);
    		if(!empty($check_details))
    		{
    			$interest = [];
    			$interest = explode(',', $check_details->user_interests);
    			if(empty($interest) || $check_details->user_interests==''){
    				$interest_string = $changeid;
    			}else{
    				if(in_array($changeid, $interest)){
	    				if (($key = array_search($changeid, $interest)) !== false) {
						    unset($interest[$key]);
						}
	    			}else{
	    				array_push($interest, $changeid);
	    			}
	    			$interest_string = implode(',', $interest);
    			}
    			

	        	$update_array=array(
	        		'user_interests'=>$interest_string
	        	);

	        	User::where('id',$user->id)->update($update_array);
	        	$insert_details=User::find($user->id);

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
    		else
    		{
    			return response()->json([
	            	'error' => 'Record is not found',
	            	'status_code' => 401,
	            	'status' => false
	            ]);
    		}
    	}else{
			 return response()->json([
	            	'error' => 'Unauthenticate User',
	            	'status_code' => 401,
	            	'status' => false
	            ], 401);
    	}
	}
	public function getUser(Request $request)
	{
		$token = $request->header('Authorization');
    	$user = User::select('id','unique_id','user_type','user_profile','first_name','last_name','user_about','mobile','country_short_code','user_interests','user_status')->where('user_api_token',$token)->first();
    	
    	if($user)
    	{
    		$user_id = $user->id;
    		$friend_relation = FriendRelation::select('from_user_id','to_user_id')->where(function ($q) use ($user_id,$user) {
                                    $q->where('to_user_id', $user_id)
                                    ->where('relation_status',1)->get();
                                })->orWhere(function ($query) use ($user_id,$user) {
                                    $query->where('from_user_id', $user_id)
                                          ->where('relation_status',1)->get();
                                })->get();
            $review_count = SpotDetail::where('user_id',$user_id)->count();
            $users_id_array = [];
            foreach ($friend_relation as $value) {
                array_push($users_id_array, $value->to_user_id);
                array_push($users_id_array, $value->from_user_id);
            }
            
            //print_r($users_id_array);
            $users_id_array = array_unique($users_id_array);
            if (($key = array_search($user_id, $users_id_array)) !== false) {
                unset($users_id_array[$key]);
            }
    		return response()->json([
    				'friends_count' => count($users_id_array),
    				'review_count' => $review_count,
	            	'user_details' => $user,
	            	'status_code' => 200,
	            	'status' => true
	            ], 200);
    	}else{
			 return response()->json([
	            	'error' => 'Unauthenticate User',
	            	'status_code' => 401,
	            	'status' => false
	            ], 401);
    	}
	}

    public function update(Request $request)
    {
     	
    	$token = $request->header('Authorization');
    	$user = User::where('user_api_token',$token)->first();
    	if($user)
    	{
    		$check_details=User::find($user->id);

    		if(!empty($check_details))
    		{
    			$rules = [
	             'first_name' => 'required|string|max:50',
	             'last_name' => 'required|string|max:50',
	             'mobile' => 'required',
	             'user_about' => 'required'
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
		        	$mobile_info = json_decode($request->input('mobile'));
		            $dialCode = str_replace("+", "", $mobile_info->dialCode);
		            $number = str_replace(" ", "", $mobile_info->number);
		            $country_short_code = $mobile_info->countryCode;
		            $number = str_replace("-", "", $number);
		        	$picture='';
		        	if ($request->hasFile('file')) {
		        		$file = $request->file('file');
					    $filename = $file->getClientOriginalName();
					    $extension = $file->getClientOriginalExtension();
					    $picture = date('dmyHis').'-'.$filename;
					    $file->move(public_path('user_profiles'), $picture);
		        	}
		        	//$bday = Carbon::parse($request['birth_date']);

		        	$update_array=array(
		        		'first_name'=>$request['first_name'],
		        		'last_name'=>$request['last_name'],
		        		'mobile'=>$number,
		        		'country_code'=>$dialCode,
		        		'country_short_code'=>$country_short_code,
		        		'user_about'=>$request['user_about']
		        	);
		        	
		        	if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
		        	{
		        		$update_array['user_profile']=$picture;
		        	}

		        	User::where('id',$user->id)->update($update_array);

		        	$insert_details=User::find($user->id);

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
}
