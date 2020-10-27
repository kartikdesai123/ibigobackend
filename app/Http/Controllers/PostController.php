<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\BusinessDetails;
use App\User;
use DB;
use App\Notification;
use App\CheckInDetail;
use App\SpotDetail;
use App\Group;
use App\PostLike;
use App\Event;
use App\PostComment;
use Carbon\Carbon;
use Mail;
use App\Events\CommentSent;
use App\Events\UserNotification;
use App\Events\LikeEvent;
use App\Message;
use App\FriendRelation;
use Illuminate\Support\Facades\Config;
use \Pusher\Pusher;

class PostController extends Controller
{
    private $pusher;
    public function __construct()
    {
        $config = Config::get('broadcasting.connections.pusher');

        $options = [
            'cluster' => $config['options']['cluster']
        ];

        $this->pusher = new Pusher(
            $config['key'],
            $config['secret'],
            $config['app_id'],
            $options
        );
    }

    public function likePost(Request $request,$id)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $post =  CheckInDetail::where('id',$id)->first();
            $notification = array(
                    'notification_type' => 3,                    
                    'notification_from_user'=> $user_id,
                    'notification_to_user'=> $post->user_id,
                    'post_id' => $id
                );
            $likes = PostLike::where('post_id',$id)->first();
            $like_user = User::where('id',$user_id)->first();
            if ($like_user->user_type=='normal') {
                $like_user_name = $like_user->first_name.' '.$like_user->last_name;
            }else{
                $buser = BusinessDetails::where('user_id',$like_user->id)->first();
                $like_user_name = $buser->business_name;
            }
            $temp = 0;
            $newnoti_id = null;
            if ($likes) {
                $liked_users_id = explode(",", $likes->liked_users);
                if (($key = array_search($user_id, $liked_users_id)) !== false) {
                    unset($liked_users_id[$key]);
                }else{
                    array_push($liked_users_id, $user_id);
                    $temp = 1;
                    $notification['notification_time'] = now();                    
                }
                if ($likes->liked_users!=null || $likes->liked_users!='') {
                    $liked_users = implode(',', $liked_users_id);    
                }else{
                    $liked_users = $user_id;
                }
                PostLike::where('post_id',$id)->update(['liked_users'=>$liked_users]);
                $noti = Notification::where('post_id',$id)->where('notification_type',3)->get();
                if ($post->user_id!=$user_id) {
                    if (count($noti) > 0) {
                        Notification::where('post_id',$id)->where('notification_type',3)->update($notification);
                        $newnoti_id = $noti[0]->id;
                    }else{
                        $newnoti = Notification::create($notification);
                        $newnoti_id = $newnoti->id;
                    }
                }
            }else{
                $notification['notification_time'] = now();
                PostLike::insert(['liked_users'=>$user_id,'post_id'=>$id]);
                if ($post->user_id!=$user_id) {
                    $temp = 1;
                    $newnoti =Notification::create($notification);
                }
            }
            if($temp==1) {
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
            $likes_new = PostLike::where('post_id',$id)->first();
            $liked_users_names = [];
            $liked_by_logged_in_user = 0;
            if ($likes_new) {
                if($likes_new->liked_users!=null || $likes_new->liked_users!='' ){
                    $liked_users_id = explode(",", $likes_new->liked_users);
                    if ($likes_new->liked_users!=null) {
                        
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
                        $liked_users_names = $liked_users_names;
                    }
                }
            }
            $liked_array = new \stdClass();
            $liked_array->liked_users_names = $liked_users_names;
            $liked_array->liked_by_logged_in_user = $liked_by_logged_in_user;
            broadcast(new LikeEvent($id,$liked_array))->toOthers();
            return response()->json([
                    'message' => 'Post Liked.',
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



    public function getAllPostsOfSpots(Request $request,$id){
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $spot_user_id = User::where('unique_id',$id)->first();
            $posts = CheckInDetail::where('user_id',$spot_user_id->id)->orderBy('checked_in_datetime','desc')->get();
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

    public function getAllPosts(Request $request)
    {
    	$token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $post_ids = [];
            $tagged_posts = CheckInDetail::select('id','tagged_users','share_with_friends')->whereRaw('FIND_IN_SET(?,tagged_users)', [$user_id])->orwhereRaw('FIND_IN_SET(?,share_with_friends)', [$user_id])->get();
            
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
            
            $users_id_array = array_unique($users_id_array);
            if (($key = array_search($user_id, $users_id_array)) !== false) {
                unset($users_id_array[$key]);
            }

            if(count($tagged_posts) > 0){
                foreach ($tagged_posts as $value) {
                    $t_users = explode(',', $value->tagged_users);
                    
                    if (count($t_users) > 0) {
                        if($value->tagged_users!=null){
                            if (in_array($user_id, $t_users)) {
                                array_push($post_ids, $value->id);
                            }
                        }
                    }
                }    
            }
            $loaded_posts_ids = [];
        	$posts = CheckInDetail::where('user_id',$user_id)->orWhereIn('user_id',$users_id_array)->orWhereIn('id',$post_ids)->orderBy('checked_in_datetime','desc')->get();            
            if (count($posts) > 0) {
                foreach ($posts as $key => $value) {
                    array_push($loaded_posts_ids, $value->id);
                    $likes = PostLike::where('post_id',$value->id)->first();
                    if ($value->group_id) {
                        $group = Group::where('id',$value->group_id)->first();
                        $value->group_unique_id = $group->group_unique_id;
                        $value->group_slug = $group->group_slug;
                        $value->group_name = $group->group_name;
                        $value->group_profile = $group->group_profile;
                    }else{
                        $value->group_unique_id = null;
                        $value->group_slug = null;
                        $value->group_name = null;
                        $value->group_profile = null;
                    }
                    if ($value->event_id) {
                        $event = Event::where('id',$value->event_id)->first();
                        $value->event_unique_id = $event->event_unique_id;
                        $value->event_slug = $event->event_slug;
                        $value->event_title = $event->event_title;
                        $value->event_cover = $event->event_cover;
                        $value->start_date_time = $event->start_date_time;
                        $value->event_description = $event->event_description;
                        $value->end_date_time = $event->end_date_time;
                        $value->location = $event->location;
                        
                    }else{
                        $value->event_unique_id = null;
                        $value->event_slug = null;
                        $value->event_title = null;
                        $value->event_description = null;
                        $value->event_cover = null;
                        $value->start_date_time = null;
                        $value->end_date_time = null;
                        $value->location = null;
                    }
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
                    'loaded_posts_ids' => $loaded_posts_ids,
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

    public function loadNewPost(Request $request)
    {
        $already_loaded_posts = explode(',', $request->input('post_ids'));
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $post_ids = [];

            $tagged_posts = CheckInDetail::select('id','tagged_users','share_with_friends')->whereRaw('FIND_IN_SET(?,tagged_users)', [$user_id])->orwhereRaw('FIND_IN_SET(?,share_with_friends)', [$user_id])->get();
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
            
            $users_id_array = array_unique($users_id_array);
            if (($key = array_search($user_id, $users_id_array)) !== false) {
                unset($users_id_array[$key]);
            }

            if(count($tagged_posts) > 0){
                foreach ($tagged_posts as $value) {
                    $t_users = explode(',', $value->tagged_users);
                    
                    if (count($t_users) > 0) {
                        if($value->tagged_users!=null){
                            if (in_array($user_id, $t_users)) {
                                array_push($post_ids, $value->id);
                            }
                        }
                    }
                }    
            }
            $loaded_posts_ids = [];
            
            $posts = CheckInDetail::where(function ($q) use ($user_id,$users_id_array,$already_loaded_posts,$post_ids) {
                            $q->where('user_id',$user_id)
                            ->orWhereIn('id',$post_ids)
                            ->orWhereIn('user_id',$users_id_array)
                            ->get();
                        })->whereNotIn('id',$already_loaded_posts)->orderBy('checked_in_datetime','desc')->get();
            if (count($posts) > 0) {
                foreach ($posts as $key => $value) {
                    array_push($loaded_posts_ids, $value->id);
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
                    'loaded_posts_ids' => $loaded_posts_ids,
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

    public function addComment(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $add_comment = array(
                'comment' => $request->input('comment'),
                'post_id' => $request->input('post_hidden'),
                'comment_user_id' => $user_id,
                'comment_date_time' => now()
            );
            $post =  CheckInDetail::where('id',$request->input('post_hidden'))->first();
            $noti = Notification::where('post_id',$request->input('post_hidden'))->where('notification_from_user',$user_id)->where('notification_type',4)->get();
            $notification = array(
                'notification_type' => 4,                    
                'notification_from_user'=> $user_id,
                'notification_to_user'=> $post->user_id,
                'notification_time' => now(),
                'post_id' => $request->input('post_hidden')
            );            
            if (count($noti) > 0) {
                Notification::where('post_id',$request->input('post_hidden'))->where('notification_from_user',$user_id)->where('notification_type',4)->update($notification);
                $newnoti_id = $noti[0]->id;
            }else{
                if ($user_id!=$post->user_id) {
                    $newnoti = Notification::create($notification);
                    $newnoti_id = $newnoti->id;    
                }else{
                    $newnoti_id = null;    
                }
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

            $added_comment = PostComment::create($add_comment);
            $comment_desc = Carbon::parse($added_comment->comment_date_time)->diffForHumans();
            

            $new_added_comment = new \stdClass();
            $new_added_comment->id = $added_comment->id;
            $new_added_comment->comment = $added_comment->comment;
            $new_added_comment->comment_user_profile = $user->user_profile;
            $new_added_comment->comment_user_id = $added_comment->comment_user_id.'';
            $new_added_comment->comment_user_type = $user->user_type;
            $new_added_comment->comment_user_unique_id = $user->unique_id;
            $new_added_comment->comment_user_id = $added_comment->comment_user_id;
            $new_added_comment->demo = $newnoti_id;
            if (strpos($comment_desc, 'seconds') !== false) {
                $new_added_comment->comment_date_time = str_replace('seconds', 'm', $comment_desc);
            }else if (strpos($comment_desc, 'second') !== false) {
                $new_added_comment->comment_date_time = str_replace('second', 'm', $comment_desc);
            }
            if ($user->user_type=='normal') {
                $new_added_comment->comment_user_name = $user->first_name.' '.$user->last_name;
            }else{
                $buser = BusinessDetails::where('user_id',$user->id)->first();
                $new_added_comment->comment_user_name = $buser->business_name;
            }
            broadcast(new CommentSent('add',$request->input('post_hidden'),$new_added_comment))->toOthers();

            return response()->json([
                'comment' => $added_comment,
                'message' => 'Added',
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

    public function updateComment(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $add_comment = array(
                'comment' => $request->input('comment'),
                'comment_user_id' => $user_id,
            );
            PostComment::where('id',$request->input('post_hidden'))->update($add_comment);
            $added_comment = PostComment::where('id',$request->input('post_hidden'))->first();
            $comment_desc = Carbon::parse($added_comment->comment_date_time)->diffForHumans();
            
            $new_added_comment = new \stdClass();
            $new_added_comment->id = $added_comment->id;
            $new_added_comment->comment = $added_comment->comment;
            $new_added_comment->comment_user_profile = $user->user_profile;
            $new_added_comment->comment_user_id = $added_comment->comment_user_id.'';
            $new_added_comment->comment_user_type = $user->user_type;
            $new_added_comment->comment_user_unique_id = $user->unique_id;
            $new_added_comment->comment_user_id = $added_comment->comment_user_id;
            if (strpos($comment_desc, 'seconds') !== false) {
                $new_added_comment->comment_date_time = str_replace('seconds', 'm', $comment_desc);
            }else if (strpos($comment_desc, 'second') !== false) {
                $new_added_comment->comment_date_time = str_replace('second', 'm', $comment_desc);
            }
            if ($user->user_type=='normal') {
                $new_added_comment->comment_user_name = $user->first_name.' '.$user->last_name;
            }else{
                $buser = BusinessDetails::where('user_id',$user->id)->first();
                $new_added_comment->comment_user_name = $buser->business_name;
            }
            broadcast(new CommentSent('update',$added_comment->post_id.'',$new_added_comment))->toOthers();

            return response()->json([
                'message' => 'Updated',
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

    public function deleteComment(Request $request,$id)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $added_comment = PostComment::where('id',$id)->first();
            $comment_desc = Carbon::parse($added_comment->comment_date_time)->diffForHumans();
            
            $new_added_comment = new \stdClass();
            $new_added_comment->id = $added_comment->id;
            $new_added_comment->comment = $added_comment->comment;
            $new_added_comment->comment_user_profile = $user->user_profile;
            $new_added_comment->comment_user_id = $added_comment->comment_user_id.'';
            $new_added_comment->comment_user_type = $user->user_type;
            $new_added_comment->comment_user_unique_id = $user->unique_id;
            $new_added_comment->comment_user_id = $added_comment->comment_user_id;
            if (strpos($comment_desc, 'seconds') !== false) {
                $new_added_comment->comment_date_time = str_replace('seconds', 'm', $comment_desc);
            }else if (strpos($comment_desc, 'second') !== false) {
                $new_added_comment->comment_date_time = str_replace('second', 'm', $comment_desc);
            }
            if ($user->user_type=='normal') {
                $new_added_comment->comment_user_name = $user->first_name.' '.$user->last_name;
            }else{
                $buser = BusinessDetails::where('user_id',$user->id)->first();
                $new_added_comment->comment_user_name = $buser->business_name;
            }
            broadcast(new CommentSent('delete',$added_comment->post_id.'',$new_added_comment))->toOthers();

            PostComment::where('id',$id)->delete();
            return response()->json([
                'message' => 'Deleted',
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

    public function justCheckedIn(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        
        if ($user) {
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
            
            $users_id_array = array_unique($users_id_array);
            if (($key = array_search($user_id, $users_id_array)) !== false) {
                unset($users_id_array[$key]);
            }

            $today = Carbon::now()->format('Y-m-d');
            $posts = CheckInDetail::whereIn('user_id',$users_id_array)->where('spot_id','!=',null)->orderBy('checked_in_datetime','desc')->whereDate('checked_in_datetime',$today)->get();
                        
            if (count($posts) > 0) {
                foreach ($posts as $key => $value) {
                    $from_user = User::where('id',$value->user_id)->first();
                    if ($from_user) {
                        $value->user_id = $from_user->id;
                        $value->user_slug = $from_user->user_slug;
                        $value->unique_id = $from_user->unique_id;
                        $value->user_profile = $from_user->user_profile;
                        if ($from_user->user_type=='normal') {
                            $value->user_name = $from_user->first_name.' '.$from_user->last_name;
                        }else{
                            $buser = BusinessDetails::where('user_id',$from_user->id)->first();
                            $value->user_name = $buser->business_name;
                        }
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
                    
                    $tagged_user_id = explode(",", $value->tagged_users);
                    
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
                    'justcheckedin' => $posts,                  
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

    public function recentlyUsers(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        // $user = User::where('id',25)->first();
        if ($user) {
            $user_id = $user->id;

            $friend_relation = FriendRelation::select('from_user_id','to_user_id')->where(function ($q) use ($user_id,$user) {
                                    $q->where('to_user_id', $user_id)
                                    ->where('relation_status',1)->get();
                                })->orWhere(function ($query) use ($user_id,$user) {
                                    $query->where('from_user_id', $user_id)
                                          ->where('relation_status',1)->get();
                                })->orderBy('updated_at','DESC')->get();

            $users_id_array = [];
            if (count($friend_relation) > 0) {
                foreach ($friend_relation as $value) {
                    array_push($users_id_array, $value->to_user_id);
                    array_push($users_id_array, $value->from_user_id);
                }
            }
            
            $users_id_array = array_unique($users_id_array);
            if (($key = array_search($user_id, $users_id_array)) !== false) {
                unset($users_id_array[$key]);
            }

            $recent_users = User::select('users.first_name','users.last_name','users.unique_id','users.user_profile','users.user_slug')->whereIn('id',$users_id_array)->get();
            $recent_groups = Group::join('group_users','group_users.group_id','=','groups.id')
                            ->select('groups.group_name','groups.group_slug','groups.group_unique_id','groups.group_profile','group_users.*')
                            ->where('group_users.user_id',$user_id)
                            ->where('invited',0)
                            ->orderBy('group_users.updated_at','DESC')
                            ->get();            
            return response()->json([
                'recent_users' => $recent_users,
                'recent_groups' => $recent_groups,                  
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

    public function getShareData(Request $request,$post_id)
    {
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $posts = CheckInDetail::where('id',$post_id)->get();
            if (count($posts) > 0) {
                foreach ($posts as $key => $value) {
                    $share_user_id = explode(",", $value->share_with_friends);
                    if (count($share_user_id) > 0) {
                        $share_users_names = [];
                        if($value->share_with_friends!=null){
                            foreach($share_user_id as $tid){
                                $to_user = User::where('id',$tid)->first();
                                $demo_user = [];
                                $demo_user['user_name'] = $to_user->first_name.' '.$to_user->last_name;
                                $demo_user['unique_id'] = $to_user->unique_id;
                                $demo_user['user_slug'] = $to_user->user_slug;
                                array_push($share_users_names,$demo_user);
                            }
                        }
                        $value->share_users_names = $share_users_names;
                    }
                    $share_group_id = explode(",", $value->share_with_groups);
                    if (count($share_group_id) > 0) {                        
                        $share_group_names = [];
                        if($value->share_with_groups!=null){
                            foreach($share_group_id as $tid){
                                $to_user = Group::where('id',$tid)->first();
                                $demo_user = [];
                                $demo_user['group_name'] = $to_user->group_name;
                                $demo_user['unique_id'] = $to_user->group_unique_id;
                                $demo_user['user_slug'] = $to_user->group_slug;
                                array_push($share_group_names,$demo_user);
                            }
                        }
                        $value->share_group_names = $share_group_names;
                    }                    
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

    public function updateShareData(Request $request)
    {
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        if ($user) {
            $user_id = $user->id;
            $send_request = array(
                'share_with_friends'=>$request->input('share_friends'),
                'share_with_groups'=>$request->input('share_groups')                
            );
            CheckInDetail::where('id',$request->input('post_id'))->update($send_request);
            $posts = CheckInDetail::where('id',$request->input('post_id'))->get();            
            if (count($posts) > 0) {
                foreach ($posts as $key => $value) {
                    //array_push($loaded_posts_ids, $value->id);
                    $likes = PostLike::where('post_id',$value->id)->first();
                    if ($value->group_id) {
                        $group = Group::where('id',$value->group_id)->first();
                        $value->group_unique_id = $group->group_unique_id;
                        $value->group_slug = $group->group_slug;
                        $value->group_name = $group->group_name;
                        $value->group_profile = $group->group_profile;
                    }else{
                        $value->group_unique_id = null;
                        $value->group_slug = null;
                        $value->group_name = null;
                        $value->group_profile = null;
                    }
                    if ($value->event_id) {
                        $event = Event::where('id',$value->event_id)->first();
                        $value->event_unique_id = $event->event_unique_id;
                        $value->event_slug = $event->event_slug;
                        $value->event_title = $event->event_title;
                        $value->event_cover = $event->event_cover;
                        $value->start_date_time = $event->start_date_time;
                        $value->event_description = $event->event_description;
                        $value->end_date_time = $event->end_date_time;
                        $value->location = $event->location;
                        
                    }else{
                        $value->event_unique_id = null;
                        $value->event_slug = null;
                        $value->event_title = null;
                        $value->event_description = null;
                        $value->event_cover = null;
                        $value->start_date_time = null;
                        $value->end_date_time = null;
                        $value->location = null;
                    }
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
                'post' => $posts,
                'message' => 'Updated',
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
