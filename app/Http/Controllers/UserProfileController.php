<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Admin;
use App\FriendRelation;
use Validator;
use App\User;
use App\interest;
use App\SpotPhotoVideo;
use App\SpotDetail;

class UserProfileController extends Controller
{
	public function getUser(Request $request)
	{
		$token = $request->header('Authorization');
    	$user = User::select('id','user_profile','first_name','last_name','birth_date','user_type','user_interests','user_cover','user_about','user_slug','unique_id')->where('user_api_token',$token)->first();
    	$user_interests = explode(',', $user->user_interests);

		$years = Carbon::parse($user->birth_date)->age;
		$user->age = $years;
	
		$interests = interest::select('title')->whereIn('id',$user_interests)->get();
		
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
            
            $users_id_array = [];
            if (count($friend_relation) > 0) {
            	foreach ($friend_relation as $value) {
                	array_push($users_id_array, $value->to_user_id);
	                array_push($users_id_array, $value->from_user_id);
	            }
            }
            $review_count = SpotDetail::where('user_id',$user_id)->whereNotNull('rating')->count();
            $review_places = SpotDetail::select('spot_id','rating')->where('user_id',$user_id)->whereNotNull('rating')->limit(4)->get();
            $spot_photos_videos_by_user = SpotPhotoVideo::where('user_id',$user_id)->get();
            $spot_photos_by_user = [];
            $spot_videos_by_user = [];
            if (count($spot_photos_videos_by_user) > 0) {
            	foreach ($spot_photos_videos_by_user as $spv) {
	                if ($spv->user_to_spot_photos!=null) {
	                    $photos_by_user =  unserialize($spv->user_to_spot_photos);
	                    $spot_photos_by_user = array_merge($spot_photos_by_user,$photos_by_user);
	                }
	                if ($spv->user_to_spot_videos!=null) {
	                    $videos_by_user = unserialize($spv->user_to_spot_videos);
	                    $spot_videos_by_user = array_merge($spot_videos_by_user,$videos_by_user);
	                }
	            } 
            }
            $liked_places = SpotDetail::select('spot_id',)->where('user_id',$user_id)->where('is_like',1)->limit(4)->get();
            if (count($review_places)>0) {
            	foreach ($review_places as $value) {
            		$spot_detail = User::select('users.id','business_details.business_name','users.user_profile','users.unique_id','users.user_slug')
            					->join('business_details','business_details.user_id','=','users.id')
            					->where('users.id',$value->spot_id)
            					->first();
            		$value->business_name = $spot_detail->business_name;
            		$value->unique_id = $spot_detail->unique_id;
            		$value->user_slug = $spot_detail->user_slug;
            		$value->user_profile = $spot_detail->user_profile;
            		//$value->spot_name = $spot_detail->business_name;
            	}
            }
            if (count($liked_places)>0) {
            	foreach ($liked_places as $value) {
            		$spot_detail = User::select('users.id','business_details.business_name','users.user_profile','users.unique_id','users.user_slug')
            					->join('business_details','business_details.user_id','=','users.id')
            					->where('users.id',$value->spot_id)
            					->first();
            		$value->business_name = $spot_detail->business_name;
            		$value->unique_id = $spot_detail->unique_id;
            		$value->user_slug = $spot_detail->user_slug;
            		$value->user_profile = $spot_detail->user_profile;
            		//$value->spot_name = $spot_detail->business_name;
            	}
            }
            $users_id_array = array_unique($users_id_array);
            if (($key = array_search($user_id, $users_id_array)) !== false) {
                unset($users_id_array[$key]);
            }
    		return response()->json([
    				'friends_count' => count($users_id_array),
    				'review_count' => $review_count,
    				'liked_places' => $liked_places,
    				'spot_videos_by_user' => $spot_videos_by_user,
    				'spot_photos_by_user' => $spot_photos_by_user,
    				'review_places' => $review_places,
	            	'user_details' => $user,
	            	'user_interests' => $interests,
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

    public function changeBackground(Request $request)
    {
    	$token = $request->header('Authorization');
    	$user = User::where('user_api_token',$token)->first();
    	if($user)
    	{
    		$check_details=User::find($user->id);

    		if(!empty($check_details))
    		{
    			$picture='';
	        	if ($request->hasFile('file')) {
	        		$file = $request->file('file');
				    $filename = $file->getClientOriginalName();
				    $extension = $file->getClientOriginalExtension();
				    $picture = date('dmyHis').'-'.$filename;
				    $file->move(public_path('user_cover'), $picture);
	        	}
	        	$update_array=array();
	        	if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
	        	{
	        		$update_array['user_cover']=$picture;
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

    public function changeAbout(Request $request)
    {
    	$token = $request->header('Authorization');
    	$user = User::where('user_api_token',$token)->first();
    	if($user)
    	{
    		$check_details=User::find($user->id);

    		if(!empty($check_details))
    		{
    			$rules = [
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
		        }else{
		        	$update_array=array('user_about'=>$request['user_about']);
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
