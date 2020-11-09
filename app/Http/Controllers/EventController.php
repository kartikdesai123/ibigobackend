<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Events\MessageSent;
use Carbon\Carbon;
use App\User;
use App\Message;
use App\Event;
use App\FriendRelation;
use App\Group;
use App\SpotDetail;
use App\Events\UserNotification;
use App\Notification;
use DB; 
use Illuminate\Support\Facades\Config;
use \Pusher\Pusher;
use Illuminate\Support\Arr;
use App\EventInvite;

class EventController extends Controller
{
    public function create_event(Request $request)
    {
    	$token = $request->header('Authorization');
        $user = User::select('id','user_profile','user_type','user_about','mobile','user_cover','country_short_code','unique_id','user_slug')->where('user_api_token',$token)->first();
        // $user = User::where('id',25)->first();
        if($user){
            $user_id = $user->id;
            $start_date_time = Carbon::parse($request->input('start_date_time'));
            $end_date_time = Carbon::parse($request->input('end_date_time'));
            $host_group = $request->input('host_group');
            if($request->input('host_group')=='null'){
                $host_group = NULL;
            }
            $now = Carbon::now();
            $unique_code = $now->format('mdYHisu');
            $event_create = array(
            	'event_title' => $request->input('event_title'),
                'event_unique_id' => $unique_code.mt_rand(100, 999),
                'event_slug' => Str::slug($request['event_title'], '-'),
            	'start_date_time' => $start_date_time,
            	'end_date_time' => $end_date_time,
            	'host_id' => $user_id,
            	'location' => $request->input('location'),
            	'host_group' => $host_group,
            	'event_description' => $request->input('event_description'),
            );
            Event::create($event_create);
            return response()->json([
                // 'recent_chats' => array_values($from),                
                'message' => 'Found',
                'status_code' => 200,
                'status' => true
            ]);
        }else{
            return response()->json([
                'message' => 'User not found.',
                'status_code' => 404,
                'status' => false
            ]);
        }
    }

    public function listEvent(Request $request)
    {
        $token = $request->header('Authorization');
        $user = User::select('id','user_profile','user_type','user_about','mobile','user_cover','country_short_code','unique_id','user_slug')->where('user_api_token',$token)->first();
        // $user = User::where('id',25)->first();
        if($user){
            $today = Carbon::now()->format('Y-m-d');
            $user_id = $user->id;            
            $events = Event::select('user_profile','user_type','user_type','first_name','last_name','unique_id','user_slug','groups.*','events.*','business_details.business_name')
                    ->leftjoin('groups','groups.id','=','events.host_group')
                    ->join('users','users.id','=','events.host_id')
                    ->leftjoin('business_details','business_details.user_id','=','users.id')
                    ->where('end_date_time','>',$today)
                    ->where('events.host_id',$user_id)
                    ->get();
            //$events = $events->start_date_time->format('d-m-Y');
            //$events = $events->end_date_time->format('d-m-Y');
            return response()->json([
                'events' => $events,                
                'status_code' => 200,
                'status' => true
            ]);
        }else{
            return response()->json([
                'message' => 'User not found.',
                'status_code' => 404,
                'status' => false
            ]);
        }
    }   

    public function getEvent(Request $request,$unique_id)
    {
        $token = $request->header('Authorization');
        $user = User::select('id','user_profile','user_type','user_about','mobile','user_cover','country_short_code','unique_id','user_slug')->where('user_api_token',$token)->first();
        // $user = User::where('id',25)->first();
        if($user){
            $user_id = $user->id;
            $event_details = Event::select('user_profile','user_type','first_name','last_name','unique_id','user_slug','groups.*','events.*','business_details.business_name')->where('event_unique_id',$unique_id)
                ->join('users','users.id','=','events.host_id')
                ->leftjoin('business_details','business_details.user_id','=','users.id')
                ->leftjoin('groups','groups.id','=','events.host_group')->first();
            
                $event_invitation = EventInvite::select('user_profile','user_type','first_name','last_name','unique_id','user_slug')->where('event_id',$event_details->id)
                ->leftjoin('users','users.id','=','event_invites.user_id')
              //  ->leftjoin('event_invites','event_invites.user_id','=','users.id')
                ->get();

            return response()->json([
                'event_details' => $event_details,
                'event_invitation' =>  $event_invitation,               
                'status_code' => 200,
                'status' => true
            ]);
        }else{
            return response()->json([
                'message' => 'User not found.',
                'status_code' => 404,
                'status' => false
            ]);
        }
    }

    public function updateBackground(Request $request,$event_id)
    {
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        if($user)
        {
            $check_details = Event::find($event_id);
            if(!empty($check_details))
            {
                $picture='';
                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    $filename = $file->getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
                    $picture = date('dmyHis').'-'.$filename;
                    $file->move(public_path('event_cover'), $picture);
                }
                $update_array=array();
                if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
                {
                    $update_array['event_cover']=$picture;
                }
                Event::where('id',$event_id)->update($update_array);
                return response()->json([
                    'messages' => 'Cover updated successfully.',
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

    public function edit(Request $request,$event_id)
    {
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        if($user)
        {
            $check_details = Event::find($event_id);
            if(!empty($check_details))
            {
                return response()->json([
                    'event' => $check_details,
                    'messages' => 'Cover updated successfully.',
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

    public function update(Request $request)
    {
        $start_date_time = new \DateTime(Carbon::parse($request->input('start_date_time')));
        $end_date_time = new \DateTime(Carbon::parse($request->input('end_date_time')));
        $token = $request->header('Authorization');
        $user = User::select('id','user_profile','user_type','user_about','mobile','user_cover','country_short_code','unique_id','user_slug')->where('user_api_token',$token)->first();
        // $user = User::where('id',25)->first();
        if($user){
            $user_id = $user->id;
            $host_group = $request->input('host_group');
            if($request->input('host_group')=='null'){
                $host_group = NULL;
            }
            $now = Carbon::now();
            $unique_code = $now->format('mdYHisu');
            $event_create = array(
                'event_title' => $request->input('event_title'),                
                'event_slug' => Str::slug($request['event_title'], '-'),
                'start_date_time' => $start_date_time,
                'end_date_time' => $end_date_time,
                'host_id' => $user_id,
                'location' => $request->input('location'),
                'host_group' => $host_group,
                'event_description' => $request->input('event_description'),
            );
            Event::where('id',$request->input('event_id'))->update($event_create);
            return response()->json([
                // 'recent_chats' => array_values($from),                
                'message' => 'Found',
                'status_code' => 200,
                'status' => true
            ]);
        }else{
            return response()->json([
                'message' => 'User not found.',
                'status_code' => 404,
                'status' => false
            ]);
        }
    }

    public function inviteToEvent(Request $request)
    {
        $token = $request->header('Authorization');
        $user = User::select('id','user_profile','user_type','user_about','mobile','user_cover','country_short_code','unique_id','user_slug')->where('user_api_token',$token)->first();
        // $user = User::where('id',25)->first();
        if($user){
            $user_id = $user->id;
            $event_id = $request->input('event_id');
            $share_frd_id = [];

            if ($request->input('share_frd_id')!=null || $request->input('share_frd_id')!='') {
                $share_frd_id = explode(',', $request->input('share_frd_id'));
            }
            $share_group_id = [];
            if ($request->input('share_group_id')!=null || $request->input('share_group_id')!='') {                
                $share_group_id = explode(',',$request->input('share_group_id'));
            }

            if (count($share_group_id) > 0) {
                foreach ($share_group_id as $value) {
                    $invite_create = array(
                        'event_id' => $request->input('event_id'),
                        'group_id' => $value,
                    );
                    $get_notification_to_user = Group::where('id',$value)->first();
                    $noti = Notification::where('notification_to_user',$get_notification_to_user->group_created_by)->where('invited_group_id',$value)->where('notification_from_user',$user->id)->where('notification_type',10)->get();
                    $notification = array(
                        'notification_type' => 10,                    
                        'notification_from_user'=> $user->id,
                        'invited_group_id' => $value,
                        'invited_event_id' => $request->input('event_id'),
                        'notification_read' => 0,
                        'notification_to_user'=> $get_notification_to_user->group_created_by,
                        'notification_time' => now(),
                    );
                    if (count($noti) > 0) {
                        Notification::where('notification_to_user',$get_notification_to_user->group_created_by)->where('invited_group_id',$value)->where('notification_from_user',$user->id)->where('notification_type',10)->update($notification);
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
                        $new_added_notification->invited_event_id = $all_notifications->invited_event_id;                   
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
                        if ($new_added_notification->invited_event_id!=null) {
                            $event = Event::where('id',$new_added_notification->invited_event_id)->first();
                            $new_added_notification->event_title = $event->event_title;
                            $new_added_notification->event_slug = $event->event_slug;
                            $new_added_notification->event_unique_id = $event->event_unique_id;   
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
                    EventInvite::create($invite_create);
                }
            }
            if (count($share_frd_id) > 0) {
                foreach ($share_frd_id as $value) {
                    $invite_create = array(
                        'event_id' => $request->input('event_id'),
                        'user_id' => $value,
                    );
                    $noti = Notification::where('notification_to_user',$value)->where('notification_from_user',$user->id)->where('notification_type',10)->where('invited_group_id',null)->get();
                    $notification = array(
                        'notification_type' => 10,                    
                        'notification_from_user'=> $user->id,
                        'notification_read' => 0,
                        'invited_event_id' => $request->input('event_id'),
                        'notification_to_user'=> $value,
                        'notification_time' => now(),
                    );
                    if (count($noti) > 0) {
                        Notification::where('notification_to_user',$value)->where('notification_from_user',$user->id)->where('notification_type',10)->update($notification);
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
                        $new_added_notification->invited_event_id = $all_notifications->invited_event_id;
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
                        if ($new_added_notification->invited_event_id!=null) {
                            $event = Event::where('id',$new_added_notification->invited_event_id)->first();
                            $new_added_notification->event_title = $event->event_title;
                            $new_added_notification->event_slug = $event->event_slug;
                            $new_added_notification->event_unique_id = $event->event_unique_id;   
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
                    EventInvite::create($invite_create);
                }
            }
            return response()->json([
                'message' => 'Found',
                'status_code' => 200,
                'status' => true
            ]);
        }else{
            return response()->json([
                'message' => 'User not found.',
                'status_code' => 404,
                'status' => false
            ]);
        }
    }

    public function delete(Request $request,$id)
    {
        $token = $request->header('Authorization');
        $user = User::select('id','user_profile','user_type','user_about','mobile','user_cover','country_short_code','unique_id','user_slug')->where('user_api_token',$token)->first();
        // $user = User::where('id',25)->first();
        if($user){
            $user_id = $user->id;
            Event::where('id',$id)->delete();
            return response()->json([
                'message' => 'Found',
                'status_code' => 200,
                'status' => true
            ]);
        }else{
            return response()->json([
                'message' => 'User not found.',
                'status_code' => 404,
                'status' => false
            ]);
        }
    }

    public function getConnectedUser(Request $request)
    {
        $token = $request->header('Authorization');
        $user = User::select('id','user_profile','user_type','user_about','mobile','user_cover','country_short_code','unique_id','user_slug')->where('user_api_token',$token)->first();
        // $user = User::where('id',25)->first();
        if($user){
            $user_id = $user->id;
            $connected_users = SpotDetail::select('users.id','spot_details.is_connected','spot_details.spot_id','users.first_name','users.last_name')->join('users','users.id','=','spot_details.user_id')->where('spot_id',$user_id)->where('is_connected',1)->get();
            return response()->json([
                'connected_users' => $connected_users,
                'message' => 'Found',
                'status_code' => 200,
                'status' => true
            ]);
        }else{
            return response()->json([
                'message' => 'User not found.',
                'status_code' => 404,
                'status' => false
            ]);
        }
    }
    /* Notificaiton
    1 : invite
    2 : taggeed post
    3 : like
    4 : comemnt
    5 : send request 
    6 : accept request
    7 : post
    8 : invite_to_group
    9 : request_to_group
    10 : invite to event */
}
