<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Notification;
use App\FriendRelation;
use App\User;
use App\Events\UserNotification;
use Carbon\Carbon;
use DB;

class FriendRelationController extends Controller
{
    public function getFriends(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if($user) {
            $friend_relation = FriendRelation::select('from_user_id','to_user_id')->where(function ($q) use ($user_id,$user) {
                                    $q->where('to_user_id', $user_id)
                                    ->where('relation_status',1)->get();
                                })->orWhere(function ($query) use ($user_id,$user) {
                                    $query->where('from_user_id', $user_id)
                                          ->where('relation_status',1)->get();
                                })->get();
            
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
            //print_r($users_id_array);
            $friend_list = User::select('id','user_profile','first_name','user_slug','unique_id','last_name',DB::raw("CONCAT(first_name,' ',last_name) AS user_name"))->whereIn('id', $users_id_array)->get();
            return response()->json([
                'friend_list'=> $friend_list,
                'status_code' => 200,
                'status' => true,
            ]);
        }
    }

    public function sendRequest(Request $request,$id)
    {
    	$token = $request->header('Authorization');     
     	$user = User::where('user_api_token',$token)->first();
        if($user) {
        	$friend_relation = FriendRelation::where(function ($q) use ($id,$user) {
                        $q->where('to_user_id', $user->id)
                              ->where('from_user_id', $id);
                    })->orWhere(function ($query) use ($id,$user) {
                        $query->where('from_user_id', $user->id)
                              ->where('to_user_id', $id);
                    })->first();
            $noti = Notification::where('notification_to_user',$id)->where('notification_from_user',$user->id)->where('notification_type',5)->get();
            $notification = array(
                'notification_type' => 5,                    
                'notification_from_user'=> $user->id,
                'notification_to_user'=> $id,
                'notification_time' => now(),
            );
            $temp = 0;
        	if ($friend_relation) {
        		if($friend_relation->relation_status==0){
        			$message ='Request already sent.';
        		}else if($friend_relation->relation_status==1){
        			$message ='Friends.';
        		}else if($friend_relation->relation_status==2){
                    $temp=1;
        			$message ='Request rejected.';
                    $relation_status = 0;
                    $send_request = array(
                        'relation_status' => $relation_status,
                        'action_user_id' => $user->id,
                        'updated_at' => now()
                    );
                    FriendRelation::where('id',$friend_relation->id)->update($send_request);
                    $message =0;
        		}else if($friend_relation->relation_status==3){
        			$message ='Blocked.';
        		}else if($friend_relation->relation_status==4){
                    $temp=1;
                    $message ='No relation';
                    $relation_status = 0;
                    $send_request = array(
                        'relation_status' => $relation_status,
                        'action_user_id' => $user->id,
                        'updated_at' => now()
                    );
                    FriendRelation::where('id',$friend_relation->id)->update($send_request);
                    $message =0;
                }
	            $status_code = 200;
	            $status = true;
        	}else{
                $temp=1;
        		$relation_status = 0;
        		$send_request = array(
	                'from_user_id' => $user->id,
	                'to_user_id' => $id,
	                'relation_status' => $relation_status,
	                'action_user_id' => $user->id,
                    'updated_at' => now()
	            );
	            FriendRelation::create($send_request);
	            $message =0;
	            $status_code = 200;
	            $status = true;
        	}
            if ($temp==1) {
                if (count($noti) > 0) {
                    Notification::where('notification_to_user',$id)->where('notification_from_user',$user->id)->where('notification_type',5)->update($notification);
                    $newnoti_id = $noti[0]->id;
                }else{
                    $newnoti = Notification::create($notification);
                    $newnoti_id = $newnoti->id;    
                }
                $all_notifications = Notification::where('id',$newnoti_id)->first();
                if ($all_notifications) {
                    $new_added_notification = new \stdClass();
                    $new_added_notification->id = $all_notifications->id;
                    $new_added_notification->notification_to_user = $all_notifications->notification_to_user;
                    $new_added_notification->notification_from_user = $all_notifications->notification_from_user;
                    $new_added_notification->notification_time = $all_notifications->notification_time;
                    $new_added_notification->notification_type = $all_notifications->notification_type;
                    $new_added_notification->invited_spot_id = $all_notifications->invited_spot_id;                               
                    $new_added_notification->post_id = $all_notifications->post_id;

                    if ($new_added_notification->notification_to_user!=null || $new_added_notification->notification_to_user!='') {
                        $notification_to_user = User::where('id',$all_notifications->notification_to_user)->first();
                        if ($notification_to_user->user_type=='normal') {
                            $new_added_notification->notification_to_user_name = $notification_to_user->first_name.' '.$notification_to_user->last_name;
                        }else{
                            $notification_to_business_user = BusinessDetails::where('user_id',$new_added_notification->notification_to_user)->first();
                            $new_added_notification->notification_to_user_name = $notification_to_business_user->business_name;
                        }
                        $notification_from_user = User::where('id',$new_added_notification->notification_from_user)->first();
                        $new_added_notification->unique_id = $notification_from_user->unique_id;
                        $new_added_notification->user_profile = $notification_from_user->user_profile;
                        $new_added_notification->user_slug = $notification_from_user->user_slug;
                        $new_added_notification->user_type = $notification_from_user->user_type;
                        if ($notification_from_user->user_type=='normal') {
                            $new_added_notification->notification_from_user_name = $notification_from_user->first_name.' '.$notification_from_user->last_name;
                        }else{
                            $notification_from_business_user = BusinessDetails::where('user_id',$new_added_notification->notification_from_user)->first();
                            $new_added_notification->notification_from_user_name = $notification_from_business_user->business_name;
                        }
                    }
                    $new_added_notification->notification_post_title = null;
                    $new_added_notification->spot_user_name = null;
                    $new_added_notification->spot_user_slug = null;
                    $new_added_notification->spot_user_unique_id = null;
                    if ($new_added_notification->invited_spot_id!=null) {
                        $spot_business_user = BusinessDetails::where('user_id',$new_added_notification->invited_spot_id)->first();
                        $spot_user = User::where('id',$new_added_notification->invited_spot_id)->first();
                        $new_added_notification->spot_user_name = $spot_business_user->business_name;
                        $new_added_notification->spot_user_slug = $spot_user->user_slug;
                        $new_added_notification->spot_user_unique_id = $spot_user->unique_id;
                    }
                    if ($new_added_notification->post_id!=null) {
                        $check_in_details = CheckInDetail::where('id',$new_added_notification->post_id)->first();
                        if ($check_in_details->spot_description!=null) {
                            $new_added_notification->notification_post_title = $check_in_details->spot_description;
                        }
                    }
                    $new_added_notification->notification_time = Carbon::parse($new_added_notification->notification_time)->diffForHumans();
                    broadcast(new UserNotification($new_added_notification))->toOthers();
                }
            }
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'action_user_id' => $user->id,
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function acceptRequest(Request $request,$id)
    {
    	$token = $request->header('Authorization');     
     	$user = User::where('user_api_token',$token)->first();
        if($user) {
        	$friend_relation = FriendRelation::where(function ($q) use ($id,$user) {
                        $q->where('to_user_id', $user->id)
                              ->where('from_user_id', $id);
                    })->orWhere(function ($query) use ($id,$user) {
                        $query->where('from_user_id', $user->id)
                              ->where('to_user_id', $id);
                    })->first();
            $noti = Notification::where('notification_to_user',$id)->where('notification_from_user',$user->id)->where('notification_type',6)->get();
            $notification = array(
                'notification_type' => 6,                    
                'notification_from_user'=> $user->id,
                'notification_to_user'=> $id,
                'notification_time' => now(),
            );
        	if ($friend_relation) {
        		if($friend_relation->relation_status==0){
        			$relation_status = 1;
                    $temp=1;
	        		$send_request = array(
		                'relation_status' => $relation_status,
		                'action_user_id' => $user->id
		            );
		            FriendRelation::where('id',$friend_relation->id)->update($send_request);
		            $message =1;
        		}else if($friend_relation->relation_status==1){
        			$message ='Friends';
        		}else if($friend_relation->relation_status==2){
                    $temp=1;
        			$relation_status = 1;
                    $send_request = array(
                        'relation_status' => $relation_status,
                        'action_user_id' => $user->id
                    );
                    FriendRelation::where('id',$friend_relation->id)->update($send_request);
                    $message =1;
        		}else if($friend_relation->relation_status==3){
        			$message ='Blocked.';
        		}else if($friend_relation->relation_status==4){
                    $message ='No relation';
                }
	            $status_code = 200;
	            $status = true;
                if ($temp==1) {
                    if (count($noti) > 0) {
                        Notification::where('notification_to_user',$id)->where('notification_from_user',$user->id)->where('notification_type',6)->update($notification);
                        $newnoti_id = $noti[0]->id;
                    }else{
                        $newnoti = Notification::create($notification);
                        $newnoti_id = $newnoti->id;    
                    }
                    $all_notifications = Notification::where('id',$newnoti_id)->first();
                    if ($all_notifications) {
                        $new_added_notification = new \stdClass();
                        $new_added_notification->id = $all_notifications->id;
                        $new_added_notification->notification_to_user = $all_notifications->notification_to_user;
                        $new_added_notification->notification_from_user = $all_notifications->notification_from_user;
                        $new_added_notification->notification_time = $all_notifications->notification_time;
                        $new_added_notification->notification_type = $all_notifications->notification_type;
                        $new_added_notification->invited_spot_id = $all_notifications->invited_spot_id;                               
                        $new_added_notification->post_id = $all_notifications->post_id;

                        if ($new_added_notification->notification_to_user!=null || $new_added_notification->notification_to_user!='') {
                            $notification_to_user = User::where('id',$all_notifications->notification_to_user)->first();
                            if ($notification_to_user->user_type=='normal') {
                                $new_added_notification->notification_to_user_name = $notification_to_user->first_name.' '.$notification_to_user->last_name;
                            }else{
                                $notification_to_business_user = BusinessDetails::where('user_id',$new_added_notification->notification_to_user)->first();
                                $new_added_notification->notification_to_user_name = $notification_to_business_user->business_name;
                            }
                            $notification_from_user = User::where('id',$new_added_notification->notification_from_user)->first();
                            $new_added_notification->unique_id = $notification_from_user->unique_id;
                            $new_added_notification->user_profile = $notification_from_user->user_profile;
                            $new_added_notification->user_slug = $notification_from_user->user_slug;
                            $new_added_notification->user_type = $notification_from_user->user_type;
                            if ($notification_from_user->user_type=='normal') {
                                $new_added_notification->notification_from_user_name = $notification_from_user->first_name.' '.$notification_from_user->last_name;
                            }else{
                                $notification_from_business_user = BusinessDetails::where('user_id',$new_added_notification->notification_from_user)->first();
                                $new_added_notification->notification_from_user_name = $notification_from_business_user->business_name;
                            }
                        }
                        $new_added_notification->notification_post_title = null;
                        $new_added_notification->spot_user_name = null;
                        $new_added_notification->spot_user_slug = null;
                        $new_added_notification->spot_user_unique_id = null;
                        if ($new_added_notification->invited_spot_id!=null) {
                            $spot_business_user = BusinessDetails::where('user_id',$new_added_notification->invited_spot_id)->first();
                            $spot_user = User::where('id',$new_added_notification->invited_spot_id)->first();
                            $new_added_notification->spot_user_name = $spot_business_user->business_name;
                            $new_added_notification->spot_user_slug = $spot_user->user_slug;
                            $new_added_notification->spot_user_unique_id = $spot_user->unique_id;
                        }
                        if ($new_added_notification->post_id!=null) {
                            $check_in_details = CheckInDetail::where('id',$new_added_notification->post_id)->first();
                            if ($check_in_details->spot_description!=null) {
                                $new_added_notification->notification_post_title = $check_in_details->spot_description;
                            }
                        }
                        $new_added_notification->notification_time = Carbon::parse($new_added_notification->notification_time)->diffForHumans();
                        broadcast(new UserNotification($new_added_notification))->toOthers();
                    }
                }
        	}else{            
                $message ='Targeted user not found/No relation between you and targeted user.';
                $status_code = 404;
                $status = false;
            }
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'action_user_id' => $user->id,
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function rejectRequest(Request $request,$id)
    {
    	$token = $request->header('Authorization');     
     	$user = User::where('user_api_token',$token)->first();
        if($user) {
        	$friend_relation = FriendRelation::where(function ($q) use ($id,$user) {
                        $q->where('to_user_id', $user->id)
                              ->where('from_user_id', $id);
                    })->orWhere(function ($query) use ($id,$user) {
                        $query->where('from_user_id', $user->id)
                              ->where('to_user_id', $id);
                    })->first();
        	if ($friend_relation) {
        		if($friend_relation->relation_status==0){
        			$relation_status = 2;
	        		$send_request = array(
		                'relation_status' => $relation_status,
		                'action_user_id' => $user->id
		            );
		            FriendRelation::where('id',$friend_relation->id)->update($send_request);
		            $message = 2;
        		}else if($friend_relation->relation_status==1){
        			$message ='Friends.';
        		}else if($friend_relation->relation_status==2){
        			$message ='Request rejected.';
        		}else if($friend_relation->relation_status==3){
        			$message ='Blocked.';
        		}else if($friend_relation->relation_status==4){
                    $message ='No relation';
                }
	            $status_code = 200;
	            $status = true;
        	}else{
                $message ='Targeted user not found/No relation between you and targeted user.';
                $status_code = 404;
                $status = false;
            }
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'action_user_id' => $user->id,
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status
        ]);
    }

    public function unfriend(Request $request, $id)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if($user) {
            $friend_relation = FriendRelation::where(function ($q) use ($id,$user) {
                        $q->where('to_user_id', $user->id)
                              ->where('from_user_id', $id);
                    })->orWhere(function ($query) use ($id,$user) {
                        $query->where('from_user_id', $user->id)
                              ->where('to_user_id', $id);
                    })->first();
            if ($friend_relation) {
                if($friend_relation->relation_status==0){
                    $relation_status = 4;//cancelrequest
                    $send_request = array(
                        'relation_status' => $relation_status,
                        'action_user_id' => $user->id
                    );
                    FriendRelation::where('id',$friend_relation->id)->update($send_request);
                    $message =4;
                }else if($friend_relation->relation_status==1){
                    $relation_status = 4;//unfriend
                    $send_request = array(
                        'relation_status' => $relation_status,
                        'action_user_id' => $user->id
                    );
                    FriendRelation::where('id',$friend_relation->id)->update($send_request);
                    $message =4;
                }else if($friend_relation->relation_status==2){
                    $message ='Request rejected.';
                }else if($friend_relation->relation_status==3){
                    $message ='Blocked.';
                }else if($friend_relation->relation_status==4){
                    $message ='No relation';
                }

                $status_code = 200;
                $status = true;
            }else{
                $message ='Targeted user not found/No relation between you and targeted user.';
                $status_code = 404;
                $status = false;
            }
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'action_user_id' => $user->id,
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status
        ]);      
    }   

    public function friendSuggestions(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $users_id_array = [];
        $friends_of_friends_users_id = [];
        $check_this_ids = [];
        $all_suggestions = [];
        $all_friend_suggestions = [];
        if($user) {
            $user_id = $user->id;
            array_push($check_this_ids, $user_id.'');
            $friend_relation = FriendRelation::select('to_user_id','from_user_id','relation_status')->where(function ($q) use ($user_id,$user) {
                                    $q->where('to_user_id', $user->id)->orWhere('from_user_id', $user->id)->get();
                                })->where('relation_status',1)->get();
            
           if (count($friend_relation) >0) {
                foreach ($friend_relation as $value) {
                    array_push($users_id_array, $value->to_user_id);
                    array_push($users_id_array, $value->from_user_id);
                }
                $users_id_array = array_unique($users_id_array);
                if (($key = array_search($user_id, $users_id_array)) !== false) {
                    unset($users_id_array[$key]);
                }
                $check_this_ids = array_merge($check_this_ids,$users_id_array);
           }
            $friends_of_friends = FriendRelation::select('to_user_id','from_user_id','relation_status')->inRandomOrder()->where(function ($q) use ($user_id,$user,$users_id_array) {
                                    $q->whereIn('to_user_id', $users_id_array)->orWhereIn('from_user_id', $users_id_array)->get();
                                })->where('to_user_id','!=',$user_id)->where('from_user_id','!=',$user_id)
                                    ->where('relation_status',1)->limit(50)->get();
            
            if (count($friends_of_friends) > 0) {
                foreach ($friends_of_friends as $value) {
                    array_push($friends_of_friends_users_id, $value->to_user_id);
                    array_push($friends_of_friends_users_id, $value->from_user_id);
                }
            }

            $same_interest_users_ids = [];
            if($user->user_interests!='' || $user->user_interests!=null){
                $user_i = explode(',', $user->user_interests);
                foreach ($user_i as $value) {
                    $same_interest_user = User::select('id')->whereRaw('FIND_IN_SET(?,user_interests)', [$value])->get();
                    if (count($same_interest_user) > 0) {
                        foreach ($same_interest_user as $i) {
                            array_push($same_interest_users_ids, $i->id.'');
                        }
                    }
                }
            }
            $all_suggestions = array_merge($same_interest_users_ids,$friends_of_friends_users_id);
            $all_suggestions = array_unique($all_suggestions);
            $all_suggestions = array_values($all_suggestions);
            $demo = [];
            if (count($all_suggestions) > 0 ){
                foreach ($all_suggestions as $friend) {
                    //print($friend);echo "<br>";
                    $check_request_is_sended = FriendRelation::select('to_user_id','from_user_id','relation_status')->where(function ($q) use ($user_id,$user,$friend) {
                                    $q->where('to_user_id', $user_id)
                                    ->where('from_user_id',$friend)
                                    ->get();
                                })->orWhere(function ($query) use ($user_id,$user,$friend) {
                                    $query->where('from_user_id', $user_id)
                                    ->where('to_user_id', $friend)
                                    ->where('relation_status',0)
                                    ->get();
                                })
                                ->where(function ($subq) use ($user_id,$user,$friend) {
                                        $subq->where('relation_status',0)->orWhere('relation_status',1)->get();
                                    })->get();
                    
                    if (count($check_request_is_sended)>0) {
                        foreach ($check_request_is_sended as $value) {
                            array_push($check_this_ids, $value->from_user_id);
                            array_push($check_this_ids, $value->to_user_id);
                        }
                    }
                }
                $check_this_ids = array_unique($check_this_ids);
                if (count($check_this_ids)>0) {
                    foreach ($check_this_ids as $value) {
                        if (($key = array_search($value, $all_suggestions)) !== false) {
                            unset($all_suggestions[$key]);
                        }
                    }
                }

                $all_friend_suggestions = User::select('unique_id','first_name','last_name','id','user_profile','user_slug',DB::raw("CONCAT(first_name,' ',last_name) AS user_name"))->where('user_type','normal')->whereIn('id',$all_suggestions)->get();
                
            }
            
            return response()->json([
                'all_friend_suggestions' => $all_friend_suggestions,
                'check_this_ids' => $check_this_ids,
                'all_suggestions' => $all_suggestions,
                'status_code' => 200,
                'status' => true
            ]);
        }else{
            return response()->json([
                'message'=> 'User not found.',
                'status_code' => 404,
                'status' => false
            ]);
            
        }
    }

    public function friend_requests(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();        
        $all_friend_requests = [];
        if($user) {
            $all_friend_requests = FriendRelation::select('to_user_id','from_user_id','relation_status','action_user_id')
                        ->where(function ($q) use ($user) {
                            $q->where('to_user_id', $user->id)->orWhere('from_user_id', $user->id)->get();
                        })->where('action_user_id','!=',$user->id)->where('relation_status',0)->get();
                        
            foreach ($all_friend_requests as $value) {
                $value->request_time = Carbon::parse($value->updated_at)->diffForHumans();
                if ($value->from_user_id!=$user->id) {
                    $sent_friend_request_user = User::where('id',$value->from_user_id)->first();
                    $value->request_send_by_user_name = $sent_friend_request_user->first_name.' '.$sent_friend_request_user->last_name;
                    $value->request_send_by_user_id = $sent_friend_request_user->id;
                    $value->request_send_by_user_profile = $sent_friend_request_user->user_profile;
                    $value->request_send_by_user_slug = $sent_friend_request_user->user_slug;
                    $value->request_send_by_user_unique_id = $sent_friend_request_user->unique_id;
                }else if ($value->to_user_id!=$user->id) {
                    $sent_friend_request_user = User::where('id',$value->to_user_id)->first();
                    $value->request_send_by_user_name = $sent_friend_request_user->first_name.' '.$sent_friend_request_user->last_name;
                    $value->request_send_by_user_id = $sent_friend_request_user->id;
                    $value->request_send_by_user_profile = $sent_friend_request_user->user_profile;
                    $value->request_send_by_user_slug = $sent_friend_request_user->user_slug;
                    $value->request_send_by_user_unique_id = $sent_friend_request_user->unique_id;
                }
            }
            return response()->json([
                'all_friend_requests'=> $all_friend_requests,
            ]);
        }
    }
}
