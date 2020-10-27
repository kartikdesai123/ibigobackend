<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Group;
use App\Message;
use App\User;
use DB;
use App\FriendRelation;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\GroupUser;
use App\Notification;
use App\CheckInDetail;
use App\PostComment;
use App\PostLike;
use App\Events\UserNotification;
use App\BusinessDetails;

class GroupController extends Controller
{
    public function createGroup(Request $request)
    {
    	$token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
        	$now = Carbon::now();
            $unique_code = $now->format('mdYHisu');
        	$insert_group = array(
	    		'group_name' => $request->input('group_name'),
	    		'group_unique_id' => $unique_code.mt_rand(100, 999),
	    		'group_slug' => Str::slug($request['group_name'], '-'),
	    		'group_created_by' => $user_id,
	    		'group_created_time' => now()
	    	);
	    	$group_details = Group::create($insert_group);
	    	$invitation = explode(',', $request->input('invite_frd_id'));
	    	if (count($invitation) > 0) {
	    		foreach ($invitation as $value) {
		    		$group_user_detail = array(
		    			'group_id' => $group_details->id,
		    			'user_id' => $value,
		    			'invited' => 1,
		    			'requested' => 0,
		    			'invited_by' => $user_id
		    		);
		    		GroupUser::create($group_user_detail);
		    	}
	    	}
	    	return response()->json([
                'message' => 'created',
                'group_details' => $group_details,
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

    public function list(Request $request)
    {
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $group_list = Group::where('group_created_by',$user_id)->get();
            $connected_group_list = GroupUser::join('groups','groups.id','=','group_users.group_id')->where('group_users.user_id',$user_id)->get();            
            return response()->json([
                'group_details' => $group_list,
                'connected_group_list' => $connected_group_list,
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

    public function getGroup(Request $request,$unique_id)
    {
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {

            $group_details = Group::where('group_unique_id',$unique_id)->first();
            $group_members = GroupUser::select('users.first_name','group_users.*','users.last_name','users.user_profile','users.unique_id','users.user_slug')->join('users','users.id','=','group_users.user_id')->where('group_id',$group_details->id)->where('invited',0)->get();
            $users_requests = GroupUser::select('users.first_name','group_users.*','users.last_name','users.user_profile','users.unique_id','users.user_slug')->join('users','users.id','=','group_users.user_id')->where('group_id',$group_details->id)->where('requested',1)->get();
            $group_status = GroupUser::where('group_id',$group_details->id)->where('user_id',$user_id)->first();
            $group_details->group_members = $group_members->count();
            return response()->json([
                'group_status' => $group_status,
                'group_members' => $group_members,
                'group_details' => $group_details,
                'users_requests' => $users_requests,
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

    public function updateBackground(Request $request,$group_id)
    {
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        if($user)
        {
            $check_details = Group::find($group_id);

            if(!empty($check_details))
            {
                $picture='';
                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    $filename = $file->getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
                    $picture = date('dmyHis').'-'.$filename;
                    $file->move(public_path('user_profiles'), $picture);
                }
                $update_array=array();
                if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
                {
                    $update_array['group_profile']=$picture;
                }

                Group::where('id',$group_id)->update($update_array);
                return response()->json([
                    'messages' => 'Profile updated successfully.',
                    'status_code' => 201,
                    'status' => true
                ]);
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

    public function inviteFriend(Request $request)
    {
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();        
        if ($user) {
            $user_id = $user->id;
            $group_id = $request->input('group_id');
            $invitation = explode(',', $request->input('invite_frd_id'));
            if (count($invitation) > 0) {
                foreach ($invitation as $value) {
                    $group_details = GroupUser::where('group_id',$group_id)->where('user_id',$value)->get();
                    if (count($group_details)==0) {
                        $group_user_detail = array(
                            'group_id' => $group_id,
                            'user_id' => $value,
                            'invited' => 1,
                            'requested' => 0,
                            'invited_by' => $user_id
                        );
                        GroupUser::create($group_user_detail);
                        $noti = Notification::where('notification_to_user',$value)->where('notification_from_user',$user_id)->where('notification_type',8)->get();
                        $notification = array(
                            'notification_type' => 8,                    
                            'notification_from_user'=> $user_id,
                            'notification_to_user'=> $value,
                            'notification_time' => now(),
                            'invited_group_id' => $group_id,
                        );            
                        if (count($noti) > 0) {
                            Notification::where('notification_to_user',$value)->where('notification_from_user',$user_id)->where('notification_type',8)->update($notification);
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
                            $new_added_notification->invited_group_id = $all_notifications->invited_group_id;                               
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
                            if ($new_added_notification->invited_group_id!=null) {
                                $group = Group::where('id',$new_added_notification->invited_group_id)->first();
                                if ($group) {
                                    $new_added_notification->group_name = $group->group_name;
                                    $new_added_notification->group_slug = $group->group_slug;
                                    $new_added_notification->group_unique_id = $group->group_unique_id;
                                }
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
                }
            }
            return response()->json([
                'message' => 'invited',            
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

    public function getFriends(Request $request,$group_id)
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
            $array = [];
            $group = Group::where('group_unique_id',$group_id)->first();
            if ($group) {
                $group_details = GroupUser::where('group_id',$group->id)->get();
                if (count($group_details) > 0) {
                    $array = $group_details->pluck('user_id')->toArray();
                }
                array_push($array, $group->group_created_by);
            }

            $friend_list = User::select('id','user_profile','first_name','user_slug','unique_id','last_name',DB::raw("CONCAT(first_name,' ',last_name) AS user_name"))->whereIn('id', $users_id_array)->whereNotIn('id',$array)->get();
            return response()->json([
                'friend_list' => $friend_list,            
                'status_code' => 200,
                'status' => true,
            ]);
        }
    }

    public function leaveGroup(Request $request,$group_id)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if($user) {
            $update_group_user = array(
                'invited' => null,
                'requested' => 0,
            );
            GroupUser::where('group_id',$group_id)->where('user_id',$user_id)->update($update_group_user);
            return response()->json([
                'message' => 'Leave the group.',
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

    public function joinGroup(Request $request,$group_id)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if($user) {
            
            $group_exists = GroupUser::where('group_id',$group_id)->where('user_id',$user_id)->first();
            if ($group_exists) {
                $update_group_user = array(
                    'invited' => null,
                    'requested' => 1,
                );
                GroupUser::where('group_id',$group_id)->where('user_id',$user_id)->update($update_group_user);
            }else{
                $update_group_user = array(
                    'group_id' => $group_id,
                    'invited' => null,
                    'requested' => 1,
                    'user_id' => $user_id,
                );
                GroupUser::create($update_group_user);
            }
            
            return response()->json([
                'message' => 'Leave the group.',
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

    public function cancelGroupRequest(Request $request,$group_id)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if($user) {
            $update_group_user = array(
                'invited' => null,
                'requested' => 0,
            );
            GroupUser::where('group_id',$group_id)->where('user_id',$user_id)->update($update_group_user);
            return response()->json([
                'message' => 'Leave the group.',
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

    public function confirmGroupInvitation(Request $request,$group_id)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if($user) {
            $update_group_user = array(
                'invited' => 0,
                'requested' => null,
            );
            GroupUser::where('group_id',$group_id)->where('user_id',$user_id)->update($update_group_user);
            return response()->json([
                'message' => 'Leave the group.',
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

    public function rejectGroupInvitation(Request $request,$group_id)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if($user) {
            $update_group_user = array(
                'invited' => null,
                'requested' => 0,
            );
            GroupUser::where('group_id',$group_id)->where('user_id',$user_id)->update($update_group_user);
            return response()->json([
                'message' => 'Leave the group.',
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

    public function getGroupPost(Request $request,$group_id)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {

            $posts = CheckInDetail::where('group_id',$group_id)->orwhereRaw('FIND_IN_SET(?,share_with_groups)', [$group_id])->orderBy('checked_in_datetime','desc')->get();
            if (count($posts) > 0) {
                foreach ($posts as $key => $value) {
                    $likes = PostLike::where('post_id',$value->id)->first();
                    $value->liked_users_names = [];
                    if ($value->spot_description==null) {
                        $value->spot_description = '';
                    }
                    $value->liked_by_logged_in_user = 0;
                    if ($likes) {
                        if($likes->liked_users!=null || $likes->liked_users!='' ){
                            $liked_users_id = explode(",", $likes->liked_users);
                            if ($likes->liked_users!=null) {
                                if (($key = array_search($user_id, $liked_users_id)) !== false) {
                                    $value->liked_by_logged_in_user = 1;
                                    unset($liked_users_id[$key]);
                                }else{
                                    $value->liked_by_logged_in_user = 0;
                                }    
                                $liked_users_names = [];
                                foreach($liked_users_id as $key => $lid){
                                    $to_user = User::where('id',$lid)->first();
                                    if ($to_user->user_type=='normal') {
                                        array_push($liked_users_names,$to_user->first_name.' '.$to_user->last_name);
                                    }else{
                                        $buser = BusinessDetails::where('user_id',$to_user->id)->first();
                                        array_push($liked_users_names,$buser->business_name);
                                    }
                                    
                                }
                                $value->liked_users_names = $liked_users_names;
                            }
                            
                        }
                    }    
                    $comments = PostComment::where('post_id',$value->id)->orderBy('comment_date_time')->get();
                    foreach ($comments as $c) {
                        $comment_user = User::where('id',$c->comment_user_id)->first();
                        $c->comment_user_slug = $comment_user->user_slug;
                        $c->comment_user_unique_id = $comment_user->unique_id;
                        $c->comment_user_profile = $comment_user->user_profile;
                        $c->comment_user_type = $comment_user->user_type;
                        if ($comment_user->user_type=='normal') {
                            $c->comment_user_name = $comment_user->first_name.' '.$comment_user->last_name;
                        }else{
                            $buser = BusinessDetails::where('user_id',$comment_user->id)->first();
                            $c->comment_user_name = $buser->business_name;
                        }
                        $comment_desc = Carbon::parse($c->comment_date_time)->diffForHumans();
                        if (strpos($comment_desc, 'minutes') !== false) {
                            $c->comment_date_time = str_replace('minutes', 'm', $comment_desc);
                        }else if (strpos($comment_desc, 'minute') !== false) {
                            $c->comment_date_time = str_replace('minute', 'm', $comment_desc);
                        }else if (strpos($comment_desc, 'hours') !== false) {
                            $c->comment_date_time = str_replace('hours', 'h', $comment_desc);
                        }else if (strpos($comment_desc, 'hour') !== false) {
                            $c->comment_date_time = str_replace('hour', 'h', $comment_desc);
                        }else if (strpos($comment_desc, 'seconds') !== false) {
                            $c->comment_date_time = str_replace('seconds', 's', $comment_desc);
                        }else if (strpos($comment_desc, 'second') !== false) {
                            $c->comment_date_time = str_replace('second', 's', $comment_desc);
                        }else if (strpos($comment_desc, 'days') !== false) {
                            $c->comment_date_time = str_replace('days', 'd', $comment_desc);
                        }else if (strpos($comment_desc, 'day') !== false) {
                            $c->comment_date_time = str_replace('day', 'd', $comment_desc);
                        }else if (strpos($comment_desc, 'weeks') !== false) {
                            $c->comment_date_time = str_replace('weeks', 'w', $comment_desc);
                        }else if (strpos($comment_desc, 'week') !== false) {
                            $c->comment_date_time = str_replace('week', 'w', $comment_desc);
                        }else{
                            $c->comment_date_time = Carbon::parse($c->comment_date_time)->format('F, d Y h:i A');
                        }
                        //$c->comment_date_time = Carbon::parse($c->comment_date_time)->format('F, d Y h:i A');
                    }
                    $from_user = User::where('id',$value->user_id)->first();
                    $value->user_id = $from_user->id;
                    $value->user_slug = $from_user->user_slug;
                    $value->unique_id = $from_user->unique_id;  
                    $value->comments = $comments;

                    if ($from_user->user_type=='normal') {
                        $value->user_name = $from_user->first_name.' '.$from_user->last_name;
                    }else{
                        $buser = BusinessDetails::where('user_id',$from_user->id)->first();
                        $value->user_name = $buser->business_name;
                    }
                    if ($value->spot_id!=null) {
                        $spot_user = BusinessDetails::where('user_id',$value->spot_id)->first();
                        $value->spot_name = $spot_user->business_name;
                        $value->spot_id = $spot_user->user_id;

                        $spot_my_user = User::where('id',$value->spot_id)->first();
                        $value->spot_slug = $spot_my_user->user_slug;
                        $value->spot_unique_id = $spot_my_user->unique_id;
                    }else{
                        $value->spot_name = null;
                        $value->spot_id = null;
                        $value->spot_slug = null;
                        $value->spot_unique_id = null;
                    }
                    
                    $value->user_profile = $from_user->user_profile;
                    
                    $tagged_user_id = explode(",", $value->tagged_users);
                    $photos = unserialize($value->photos);
                    $videos = unserialize($value->videos);
                    // array_pop($videos);
                    if ($videos==null) {
                        $value->photos_videos = $photos;
                    }else if ($photos==null){
                        $value->photos_videos = $videos;
                    }else{
                        $value->photos_videos = array_merge($photos,$videos);
                    }
                    

                    if (count($tagged_user_id) > 0) {
                        $tagged_users_names = [];
                        if($value->tagged_users!=null){
                            foreach($tagged_user_id as $tid){
                                $to_user = User::where('id',$tid)->first();
                                $demo_user = [];
                                $demo_user['user_name'] = $to_user->first_name.' '.$to_user->last_name;
                                $demo_user['unique_id'] = $to_user->unique_id;
                                $demo_user['user_slug'] = $to_user->user_slug;
                                array_push($tagged_users_names,$demo_user);
                            }
                        }
                        $value->tagged_users_names = $tagged_users_names;
                    }
                    
                    $value->checked_in_datetime = Carbon::parse($value->checked_in_datetime)->diffForHumans();
                }
            }
            return response()->json([
                    'posts' => $posts,
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

    public function getGroupOrUser(Request $request,$unique_id)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        //$user = User::where('id',25)->first();
        $user_id = $user->id;
        if($user) {
            $message_user = User::where('unique_id',$unique_id)->get();
            if (count($message_user) > 0) {
                $another_user = $message_user[0];
                $messages = Message::select('from_user_id','to_user_id','message','message_date_time')->where(function ($q) use ($user_id,$another_user) {
                                    $q->where('to_user_id', $user_id)
                                    ->where('from_user_id', $another_user->id)->get();                                
                                })->orWhere(function ($query) use ($user_id,$another_user) {
                                    $query->where('from_user_id', $user_id)
                                          ->where('to_user_id',$another_user->id)->get();
                                })->get();
                $messages = $messages->groupBy(function($item){ 
                    return $item->message_date_time->format('Y-m-d'); 
                });
                
                foreach ($messages as $message) {
                    foreach ($message as $value) {
                        $to_user = User::where('id',$value->to_user_id)->first();
                        $value->to_user_name = $to_user->first_name.' '.$to_user->last_name;
                        $value->to_user_slug = $to_user->user_slug;
                        $value->to_user_unique_id = $to_user->unique_id;

                        $from_user = User::where('id',$value->from_user_id)->first();
                        $value->from_user_name = $from_user->first_name.' '.$from_user->last_name;
                        $value->from_user_slug = $from_user->user_slug;
                        $value->from_user_unique_id = $from_user->unique_id;
                    }
                }
            }else{
                $group_user = Group::where('group_unique_id',$unique_id)->get();
            }
            // echo "<pre>";
            // print_r($messages);

            return response()->json([
                'message' => 'User found',
                'another_user' => $another_user,
                'messages' => $messages,
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


    public function  getGroups(Request $request){
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $group_list = Group::where('group_created_by',$user_id)->get();
            $connected_group_list = GroupUser::join('groups','groups.id','=','group_users.group_id')->where('group_users.user_id',$user_id)->get();            
            return response()->json([
                'group_details' => $group_list,
                'connected_group_list' => $connected_group_list,
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
