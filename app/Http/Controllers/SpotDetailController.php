<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\BusinessDetails;
use App\User;
use DB;
use App\Notification;
use App\CheckInDetail;
use App\SpotDetail;
use App\PostLike;
use App\PostComment;
use App\interest;
use App\Group;
use App\Event;
use App\SpotPhotoVideo;
use Carbon\Carbon;
use Mail;
class SpotDetailController extends Controller
{
    public function getUserSpots(Request $request){
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if ($user) {
            $id = $user->id;
            $liked_spots = DB::table('spot_details')->select('spot_id','users.user_profile','business_details.business_name','business_details.short_description','users.user_slug','users.unique_id')
                ->join('business_details','business_details.user_id','=','spot_details.spot_id')
                ->join('users','users.id','=','spot_details.spot_id')
                ->where(function ($query) {
                    $query->where('is_like', 1)
                          ->orWhere('is_connected',1);
                })
                ->where('spot_details.user_id',$id)
                ->groupBy('spot_id')
                ->get();
                foreach ($liked_spots as $key => $value) {
                    $liked_users = DB::table('spot_details')->select('user_id')
                         ->where('spot_id',$value->spot_id)
                         ->where('user_id','!=',$id)
                         ->where('is_like',1)
                         ->limit(4)->get();
                    $users_id_array = [];
                    foreach ($liked_users as $v) {
                        array_push($users_id_array, $v->user_id);
                    }
                    $like_by = User::select('id','user_profile')->whereIn('id', $users_id_array)->get();
                    $users_details = [];
                    foreach ($like_by as $v) {
                        array_push($users_details, $v->user_profile);
                    }
                    $liked_spots[$key]->liked_users = $users_details;
                    $rating = DB::table('spot_details')->select(DB::raw('sum(rating) as rating_sum'),DB::raw('count(id) as count_rating'))
                     ->where('spot_id',$value->spot_id)
                     ->get();
                    $avg_rating = null;
                    if ($rating[0]->rating_sum==0 || $rating[0]->count_rating==0) {
                        $avg_rating = 0;
                    }else{
                        $avg_rating =  round($rating[0]->rating_sum/$rating[0]->count_rating, 1);           
                    }
                    $liked_spots[$key]->avg_rating = $avg_rating;
                }
               
                return response()->json([
                    'liked_connected_spots' => $liked_spots,
                    'status_code' => 200,
                    'status' => true,
                ]);
        }else{
            return response()->json([
                'message' => 'User not found',
                'status_code' => 404,
                'status' => false,
            ]);
        }
    }

    public function getSpots(Request $request)
    {
    	$name = $request->input('spot_name');
    	$spot_details = User::select('business_details.business_name','users.id','users.user_profile','business_details.full_address')->where('user_type','business')->join('business_details','business_details.user_id','=','users.id')->where('business_name', 'like', '%'.$name.'%')->get();
    	return response()->json([
    		'spot_detail' => $spot_details,
            'status_code' => 200,
            'status' => true,
        ]);
    }

    public function getSpot(Request $request,$id)
    {
        
        $token = $request->header('Authorization');
        if($token)
            $user = User::where('user_api_token',$token)->first();
        else
            $user = null;
        $spot_user = User::select('id','user_profile','user_about','mobile','user_cover','unique_id','user_slug','user_interests')->where('unique_id',$id)->first();
        if($spot_user){
            $user_interests = explode(',', $spot_user->user_interests);
            $interests = interest::select('title')->whereIn('id',$user_interests)->get();
            $id = $spot_user->id;
            $connect_status = 0;
            $like_status = 0;
            $photos_by_user=[];
            $videos_by_user=[];
            $user_id = null;
            $edit_photos_by_user = [];
            $edit_videos_by_user = [];
            if ($user) {
                $user_id = $user->id;
                $spot_connected = SpotDetail::where('spot_id',$spot_user->id)->where('user_id',$user->id)->first();
                if ($spot_connected) {
                    $connect_status = $spot_connected->is_connected;
                    $like_status = $spot_connected->is_like;
                }
                    $edit_photos_videos_by_user = SpotPhotoVideo::where('spot_id',$id)->where('user_id',$user->id)->first();
                if ($edit_photos_videos_by_user) {
                	if ($edit_photos_videos_by_user->user_to_spot_photos!=null) {
	                    $edit_photos_by_user =  unserialize($edit_photos_videos_by_user->user_to_spot_photos);
	                }
	                if ($edit_photos_videos_by_user->user_to_spot_videos!=null) {
	                    $edit_videos_by_user = unserialize($edit_photos_videos_by_user->user_to_spot_videos);
	                }
                }

            }
            
            $liked_users = SpotDetail::where('spot_id',$id)->where('is_like',1)->get();
            $users_array = [];
            foreach ($liked_users as $key => $lu) {
                $user_detail = User::where('id',$lu->user_id)->first();
                $users_array[$key]['user_profile'] = $user_detail->user_profile;
                $users_array[$key]['first_name'] = $user_detail->first_name;
                $users_array[$key]['last_name'] = $user_detail->last_name;
                $users_array[$key]['unique_id'] = $user_detail->unique_id;
                $users_array[$key]['user_slug'] = $user_detail->user_slug;
            }
            $rating_users = SpotDetail::select('spot_id','id','user_id','rating')->whereNotNull('rating')->where('spot_id',$id)->limit(7)->get();
            foreach ($rating_users as $key => $value) {
                if ($value->rating!=null) {
                    $rated_user = User::where('id',$value->user_id)->first();
                    $value->user_profile = $rated_user->user_profile;
                    $value->first_name = $rated_user->first_name;
                    $value->last_name = $rated_user->last_name;
                    $value->user_slug = $rated_user->user_slug;
                    $value->unique_id = $rated_user->unique_id;
                }
            }
            $reviews = SpotDetail::select('spot_id','id','user_id','rating','review','review_date_time','review_photos','review_videos')
                ->where(function ($query) {
                    $query->whereNotNull('review')
                          ->orWhereNotNull('rating');
                })
                ->where('spot_id',$id)->whereNull('parent_review_id')->orderBy('review_date_time','desc')->get();
            foreach ($reviews as $key => $value) {
                $replies = SpotDetail::select('spot_id','id','user_id','rating','review','review_date_time','review_photos','review_videos')->where('parent_review_id',$value->id)->orderBy('review_date_time','desc')->get();
                if ($value->review==null) {
                    $value->review = '';
                }
                if ($value->rating==null) {
                    $value->rating = '';
                }
                $value->is_edit = 0 ;
                $value->replies = $replies ;
                if ($value->rating!=null && $value->review!=null) {
                    $value->is_edit = 1;
                }
                $p_array = [];
                $v_array = [];
                if ($value->review_photos!=null) {
                    $p_array = unserialize($value->review_photos);
                }
                if ($value->review_videos!=null) {
                    $v_array = unserialize($value->review_photos);
                }
                $value->review_photos_videos = [];
                $reviews_photos = [];
                $reviews_videos = [];
                if (count($p_array) > 0) {
                    $reviews_photos = unserialize($value->review_photos);
                    $value->review_photos_videos = $reviews_photos;
                }
                if (count($v_array) > 0) {
                    $reviews_videos = unserialize($value->review_videos);
                    $value->review_photos_videos = $reviews_videos;
                }
                if (count($p_array) > 0 || count($v_array) > 0) {
                    $value->review_photos_videos = array_merge($reviews_photos,$reviews_videos);
                }
                
                $rated_user = User::where('id',$value->user_id)->first();
                $value->user_profile = $rated_user->user_profile;
                $value->first_name = $rated_user->first_name;
                $value->user_slug = $rated_user->user_slug;
                $value->unique_id = $rated_user->unique_id;
                $value->review_date_time = date('F, d Y h:i A', strtotime($value->review_date_time));
                $value->last_name = $rated_user->last_name;
            }
            $business_user = BusinessDetails::select('business_name','full_address','short_description','phone_number','business_type','spot_videos','spot_photos','parking_details','place_id')->where('user_id',$spot_user->id)->first();
            //Notification::where('id',$notification_id)->update(['is_read'=>1]);
            $photos = [];
            $videos = [];
            $spot_photos_videos_by_user = SpotPhotoVideo::where('spot_id',$id)->get();
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
            
            if ($business_user->spot_photos!=null) {
                $photos = unserialize($business_user->spot_photos);
                //$photos = array_merge($business_user->spot_photos);
            }
            if ($business_user->spot_videos!=null) {
                $videos = unserialize($business_user->spot_videos);
                //$videos = array_merge($videos,$v);
            }
            $reviews_count = DB::table('spot_details')->select('review')
                ->where('spot_id',$id)
                ->where('user_id','!=',$id)
                ->where('review','!=',null)
                ->count();
            $is_connected_count = DB::table('spot_details')->select('is_connected')
                ->where('spot_id',$id)
                ->where('is_connected',1)
                ->count();
            $rating = DB::table('spot_details')->select(DB::raw('sum(rating) as rating_sum'),DB::raw('count(id) as count_rating'))
                ->where('spot_id',$id)
                ->get();
                $avg_rating = null;
                if ($rating[0]->rating_sum==0 || $rating[0]->count_rating==0) {
                    $avg_rating = 0;
                }else{
                    $avg_rating =  round($rating[0]->rating_sum/$rating[0]->count_rating, 1);           
                }
            return response()->json([
                    'liked_users' => $users_array,
                    'user_details' => $spot_user,
                    'user_interests' => $interests,
                    'photos' => $photos,
                    'spot_photos_by_user'=>$spot_photos_by_user,
                    'spot_videos_by_user'=>$spot_videos_by_user,
                    'edit_videos_by_user'=> $edit_videos_by_user,
                    'edit_photos_by_user'=> $edit_photos_by_user,
                    'videos' => $videos,
                    'reviews' => $reviews,
                    'photos_by_user' => $photos_by_user,
                    'videos_by_user' => $videos_by_user,
                    'rating_users' => $rating_users,
                    'reviews_count' => $reviews_count,
                    'is_connected_count' => $is_connected_count,
                    'business_details' => $business_user,
                    'is_connected' => $connect_status,
                    'is_like' => $like_status,
                    'avg_rating'=>$avg_rating,
                    'status_code' => 200,
                    'status' => true
                ], 200);
        }else{
            return response()->json([
                'status_code' => 404,
                'status' => false
            ]);
        }
    }

    public function getSpotDetail(Request $request,$id)
    {
    	$rating = DB::table('spot_details')->select(DB::raw('sum(rating) as rating_sum'),DB::raw('count(id) as count_rating'))
                 ->where('spot_id',$id)
                 ->get();
		$like_spots = DB::table('spot_details')->select('user_id')
                 ->where('spot_id',$id)
                 ->where('is_like',1)
                 ->get();
        
        $users_id_array = [];
        foreach ($like_spots as $value) {
            array_push($users_id_array, $value->user_id);
        }
        $like_by = User::select('id','user_profile')->whereIn('id', $users_id_array)->get();
        if ($rating[0]->rating_sum==0 || $rating[0]->count_rating==0) {
        	$avg_rating = 0;
        }else{
        	$avg_rating =  round($rating[0]->rating_sum/$rating[0]->count_rating, 1);        	
        }
    	$spot_details = User::select('business_details.business_name','business_details.short_description','users.id','users.user_profile','business_details.full_address')->where('user_type','business')->join('business_details','business_details.user_id','=','users.id')->where('users.id',$id)->get();
    	return response()->json([
    		'spot_detail' => $spot_details,
    		'spot_rating' => $avg_rating,
    		'like_by' => $like_by,
            'status_code' => 200,
            'status' => true,
        ]);
    }

    public function addSpot(Request $request)
    {        
        $token = $request->header('Authorization');     
     	$user = User::where('user_api_token',$token)->first();
        if($user) {
    		$tag_users = $request->input('tag_friends');
            $files = $request->file('file');
            $spot_id = $request->input('spot_id');
            $event_id = $request->input('event_id');
            if ($event_id=='' || $event_id==null || $event_id=='null') {
                $event_id = null;
            }
            if ($spot_id=='' || $spot_id==null || $spot_id=='null') {
                $spot_id = null;
            }
            $spot_desc = $request->input('review');
            if ($spot_desc=='' || $spot_desc==null || $spot_desc=='null') {
                $spot_desc = null;
            }
    		$send_request = array(
                'spot_id' => $spot_id,
                'event_id' => $event_id,
                'group_id' => $request->input('group_id'),
                'tagged_users' => $tag_users,
                'share_with_friends'=>$request->input('share_friends'),
                'share_with_groups'=>$request->input('share_groups'),
                'spot_description' => $spot_desc,
                'checked_in_datetime' => now(),
                'user_id' => $user->id
            );

           
    		if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
            {
                $imageExtensions = ['png','jpeg','jpg','tiff','pjp','pjpeg','jfif','tif','gif','svg','bmp','svgz','webp','ico','xbm','dib'];
                $videoExtensions = ['mp4','ogm','wmv','mpg','webm','ogv','mov','asx','mpeg','m4v','avi'];
                $audioExtensions = ['mp3'];
            	$photo_array = [];
                $video_array = [];
                $audio_array = [];
                $file_extension=[];
            	foreach ($files as $file) {
	                $filename = $file->getClientOriginalName();
                    //$filemimetype = $file->getMimeType();
	                $extension = $file->getClientOriginalExtension();
	                if (in_array($extension, $imageExtensions)) {
                        $picture = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_images/'), $picture);
                        array_push($photo_array, $picture);
                    }else if(in_array($extension, $videoExtensions)){
                        $video = date('dmyHis').'-'.$filename;
                       $file->move(public_path('spot_videos/'), $video);
                        array_push($video_array, $video);
                    }else if(in_array($extension,$audioExtensions)){
                        $audio = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_audio/'),$audio);
                        array_push($audio_array, $audio); 
                    }   
				}

				$send_request['photos'] = serialize($photo_array);
                $send_request['videos'] = serialize($video_array);
                $send_request['audios'] = serialize($audio_array);

            }
            $posts = [];
            $check_in_detail = CheckInDetail::create($send_request);
            if ($check_in_detail) {
                $posts = CheckInDetail::where('id',$check_in_detail->id)->get();            
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
            }
        	$message ='Spot saved.';
            $status_code = 200;
            $status = true;
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }

        return response()->json([
            'post' => $posts,
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function editCheckin(Request $request,$id)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $spot_details = [];
            $avg_rating = 0;
            $like_by = [];
            $posts = CheckInDetail::where('id',$id)->get();
            $spotid = $posts[0]->spot_id;
            if ($spotid!=null) {
                $rating = DB::table('spot_details')->select(DB::raw('sum(rating) as rating_sum'),DB::raw('count(id) as count_rating'))
                     ->where('spot_id',$spotid)
                     ->get();
                $like_spots = DB::table('spot_details')->select('user_id')
                         ->where('spot_id',$spotid)
                         ->where('is_like',1)
                         ->get();

                $users_id_array = [];
                foreach ($like_spots as $value) {
                    array_push($users_id_array, $value->user_id);
                }
                $like_by = User::select('id','user_profile')->whereIn('id', $users_id_array)->get();
                if ($rating[0]->rating_sum==0 || $rating[0]->count_rating==0) {
                    $avg_rating = 0;
                }else{
                    $avg_rating =  round($rating[0]->rating_sum/$rating[0]->count_rating, 1);           
                }
                $spot_details = User::select('business_details.business_name','business_details.short_description','users.id','users.user_profile','business_details.full_address')->where('user_type','business')->join('business_details','business_details.user_id','=','users.id')->where('users.id',$spotid)->get();
            }
            
            
            
            if (count($posts) > 0) {
                foreach ($posts as $key => $value) {
                    
                    $from_user = User::where('id',$value->user_id)->first();
                    $value->user_name = $from_user->first_name.' '.$from_user->last_name;
                    $value->user_id = $from_user->id;
                    
                    if ($value->spot_id!=null) {
                        $spot_user = BusinessDetails::where('user_id',$value->spot_id)->first();
                        $value->spot_name = $spot_user->business_name;
                        $value->spot_id = $spot_user->user_id;
                    }

                    $value->user_profile = $from_user->user_profile;
                    $tagged_user_id = explode(",", $value->tagged_users);
                    $photos = [];
                    $videos = [];
                    if ($value->photos!=null) {
                        $photos = unserialize($value->photos);
                    }
                    if ($value->videos!=null) {
                        $videos = unserialize($value->videos);    
                    }
                    
                    $audio = unserialize($value->audios);

                    if ($videos==null) {
                        $value->photos_videos = $photos;
                    }else if ($photos==null){
                        $value->photos_videos = $videos;
                    }else{
                        $value->photos_videos = array_merge($photos,$videos);
                    }
                    if($audio!=null)
                    {
                        $value->audios=$audio;
                    }
                    else
                    {
                        $value->audios=[];
                    }
                    if (count($tagged_user_id) > 0) {
                        $tagged_users_names = [];
                        if($value->tagged_users!=null){
                            foreach($tagged_user_id as $tid){
                                $to_user = User::where('id',$tid)->first();
                                $tagged_obj = new \stdClass;
                                $tagged_obj->first_name = $to_user->first_name;
                                $tagged_obj->user_profile = $to_user->user_profile;
                                $tagged_obj->last_name = $to_user->last_name;
                                $tagged_obj->user_name = $to_user->first_name.' '.$to_user->last_name;
                                $tagged_obj->id = $to_user->id;
                                array_push($tagged_users_names, $tagged_obj);
                            }
                        }
                        $value->tagged_users_names = $tagged_users_names;
                    }
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
                    
                    $value->checked_in_datetime = Carbon::parse($value->checked_in_datetime)->diffForHumans();
                }
            }
            return response()->json([
                    'posts' => $posts,
                    'spot_detail' => $spot_details,
                    'spot_rating' => $avg_rating,
                    'like_by' => $like_by,
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

    public function updateCheckin(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $post_id = $request->input('edit_post_id');
        if($user) {
            $user_id = $user->id;
            $check_in_details = CheckInDetail::find($post_id);
            $tag_users = $request->input('tag_friends');
            $files = $request->file('file');
            $spot_id = $request->input('spot_id');
            if ($spot_id=='' || $spot_id==null || $spot_id=='null') {
                $spot_id = null;
            }
            $spot_desc = $request->input('review');
            if ($spot_desc=='' || $spot_desc==null || $spot_desc=='null') {
                $spot_desc = null;
            }
            $send_request = array(
                'spot_id' => $spot_id,
                'tagged_users' => $tag_users,
                'share_with_friends'=>$request->input('share_friends'),
                'share_with_groups'=>$request->input('share_groups'),
                'spot_description' => $spot_desc,
                'user_id' => $user->id
            );
            $photo_array = [];
            $video_array = [];
            $audio_array = [];

            if (isset($request['db_files_array']) && $request['db_files_array']!='') {
                $db_files = explode(',', $request['db_files_array']);
                $imageExtensions = ['png','jpeg','jpg','tiff','pjp','pjpeg','jfif','tif','gif','svg','bmp','svgz','webp','ico','xbm','dib'];
                $videoExtensions = ['mp4','ogm','wmv','mpg','webm','ogv','mov','asx','mpeg','m4v','avi'];
                $audioExtensions = ['mp3'];

                foreach ($db_files as $value) {
                    $variable = trim(substr($value, strpos($value, '.') + 1));
                    if(in_array($variable, $imageExtensions)){
                        array_push($photo_array, $value);    
                    }
                    if (in_array($variable, $videoExtensions)) {
                        array_push($video_array, $value);
                    }
                    if(in_array($variable, $audioExtensions)){
                        array_push($audio_array,$value);
                    }

                }
            }
            
            if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
            {
                $imageExtensions = ['png','jpeg','jpg','tiff','pjp','pjpeg','jfif','tif','gif','svg','bmp','svgz','webp','ico','xbm','dib'];
                $videoExtensions = ['mp4','ogm','wmv','mpg','webm','ogv','mov','asx','mpeg','m4v','avi'];
                $audioExtensions = ['mp3'];
                foreach ($files as $file) {
                    $filename = $file->getClientOriginalName();
                    //$filemimetype = $file->getMimeType();
                    $extension = $file->getClientOriginalExtension();
                    if (in_array($extension, $imageExtensions)) {
                        $picture = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_images/'), $picture);
                        array_push($photo_array, $picture);
                    }else if(in_array($extension, $videoExtensions)){
                        $video = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_videos/'), $video);
                        array_push($video_array, $video);
                    }else if(in_array($extension,$audioExtensions)){
                        $audio = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_audio/'),$audio);
                        array_push($audio_array, $audio); 
                    }   
                }
            }
            $send_request['photos'] = serialize($photo_array);
            $send_request['videos'] = serialize($video_array);
            $send_request['audios'] = serialize($audio_array);
            CheckInDetail::where('id',$post_id)->update($send_request);

            $posts = CheckInDetail::where('id',$post_id)->get();            
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
            
            $message ='Spot saved.';
            $status_code = 200;
            $status = true;
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        
        return response()->json([
            'post' => $posts,
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function deleteCheckin(Request $request,$id)
    {
    	$token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if($user) {
            CheckInDetail::where('id',$id)->delete();
            $message ='Spot deleted.';
            $status_code = 200;
            $status = true;
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        
        return response()->json([
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function like(Request $request,$id)
    {
    	$token = $request->header('Authorization');     
     	$user = User::where('user_api_token',$token)->first();
        if($user) {
        	$spot_details = SpotDetail::where('user_id',$user->id)->where('spot_id',$id)->first();
        	if ($spot_details) {
                $is_liked = 0;
        		if($spot_details->is_like == null || $spot_details->is_like == 0)
        			$is_liked = 1;
        		else if($spot_details->is_like == 1)
        			$is_liked = 0;
	        	$send_request = array(
	                'is_like' => $is_liked
	            );
	            SpotDetail::where('user_id',$user->id)->where('spot_id',$id)->update($send_request);
	        	$message ='Successfully updated.';
	            $status_code = 200;
	            $status = true;
        	}else{
	        	$send_request = array(
                    'user_id' => $user->id,
                    'spot_id' =>$id,
	                'is_like' => 1,
	            );
	            SpotDetail::create($send_request);
	        	$message ='Successfully created.';
	            $status_code = 200;
	            $status = true;
        	}
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function connect(Request $request,$id)
    {
    	$token = $request->header('Authorization');     
     	$user = User::where('user_api_token',$token)->first();
        if($user) {
        	$spot_details = SpotDetail::where('user_id',$user->id)->where('spot_id',$id)->first();
        	if ($spot_details) {
                $is_connected = 0;
        		if($spot_details->is_connected == null || $spot_details->is_connected == 0)
        			$is_connected = 1;
        		else if($spot_details->is_connected == 1)
        			$is_connected = 0;
	        	$send_request = array(
	                'is_connected' => $is_connected
	            );
	            SpotDetail::where('user_id',$user->id)->where('spot_id',$id)->update($send_request);
	        	$message ='Successfully updated.';
	            $status_code = 200;
	            $status = true;
        	}else{
	        	$send_request = array(
                    'user_id' => $user->id,
                    'spot_id' =>$id,
	                'is_connected' => 1,
	            );
	            SpotDetail::create($send_request);
	        	$message ='Successfully created.';
	            $status_code = 200;
	            $status = true;
        	}
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function inviteFriend(Request $request)
    {
    	$token = $request->header('Authorization');     
     	$user = User::where('user_api_token',$token)->first();
        $spot_id = $request->input('spot_id');
        $invited_user = $request->input('invite_frd_id');
        $iu = [];
        if($user) {
            $send_request = array(
                'invited_users' => $invited_user,
            );
        	$spot_details = SpotDetail::where('spot_details.user_id',$user->id)->where('spot_id',$spot_id)->join('business_details','business_details.user_id','spot_details.spot_id')->first();
            $invited_array = explode(',', $invited_user);
            foreach ($invited_array as $v) {
                $notification = array(
                    'notification_type' => 1,
                    'notification_to_user' => $v,
                    'notification_from_user'=> $user->id,
                    'invited_spot_id' => $spot_id,
                    'notification_time' => now(),
                    'is_read' => 0
                );
                $nid = Notification::create($notification);
                $user_details = User::select('email')->where('id',$v)->first();
                /*Mail::send('email_temo', ['key' => 'value'], function($message) use($user_details)
				{
				    $message->to($user_details->email, 'John Smith')->subject('Invitation for IBIGO');
				});*/
                $url = 'https://ibigo.shadowis.nl/#/spot/'.$spot_id.'/'.$nid->id;
                $to = $user_details->email;
                $subject = "Invitation for ".$spot_details->business_name;
                $txt = '<html> 
                <head> 
                    <title>Welcome to IBIGO!</title> 
                </head> 
                <body style="margin-left:200px;"> 
                    <div>
                    <h3>Hello!</h3>
                    <br>
                    <p>'.$user->first_name.' '.$user->last_name.' invited to you like '.$spot_details->business_name.'</p>
                    <br>
                    <a href="'.$url.'">'.$spot_details->business_name.'</a>
                    <br>
                    <p>If you did not connect to spot, no further action is required.</p>
                    <p>Regards,</p>
                    <p>IBIGO</p>
                    </div>
                </body> 
                </html>';
                $headers = "MIME-Version: 1.0" . "\r\n"; 
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                
                mail($to,$subject,$txt,$headers);

            }
        	if ($spot_details) {

	        	if ($spot_details->invited_users == null) {
                    SpotDetail::where('user_id',$user->id)->where('spot_id',$spot_id)->update($send_request);
                }else{
                    $iu = explode(',', $spot_details->invited_users);
                    $invited_user_array = explode(',', $invited_user);
                    
                    foreach ($invited_user_array as $value) {
                        if (!in_array($value, $iu)) {
                            array_push($iu, $value);
                        }
                    }
                    $new_iu = implode(',', $iu);
                    SpotDetail::where('user_id',$user->id)->where('spot_id',$spot_id)->update(['invited_users'=>$new_iu]); 
                }
                $message ='User is invited to spot.';
                $status_code = 200;
                $status = true;   
        	}else{
	            SpotDetail::create($send_request);
	        	$message ='User is invited to spot.';
	            $status_code = 200;
	            $status = true;
        	}
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        
        return response()->json([
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }


    public function notifications(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        
        if($user) {
            $all_notifications = Notification::where('notification_to_user',$user->id)
                ->orderBy('notification_time','desc')
                ->get();                
                
            if (count($all_notifications) > 0) {
                foreach ($all_notifications as $key => $value) {
                    if ($value->notification_to_user!=null || $value->notification_to_user!='') {
                        $notification_to_user = User::where('id',$value->notification_to_user)->first();
                        if ($notification_to_user) {
                            if ($notification_to_user->user_type=='normal') {
                                $value->notification_to_user_name = $notification_to_user->first_name.' '.$notification_to_user->last_name;
                            }else{
                                $notification_to_business_user = BusinessDetails::where('user_id',$value->notification_to_user)->first();
                                $value->notification_to_user_name = $notification_to_business_user->business_name;
                            }
                        }else{
                            $value->notification_to_user_name = null;
                        }
                        $notification_from_user = User::where('id',$value->notification_from_user)->first();
                        if ($notification_from_user) {
                            $value->unique_id = $notification_from_user->unique_id;
                            $value->user_profile = $notification_from_user->user_profile;
                            $value->user_slug = $notification_from_user->user_slug;
                            $value->user_type = $notification_from_user->user_type;
                            if ($notification_from_user->user_type=='normal') {
                                $value->notification_from_user_name = $notification_from_user->first_name.' '.$notification_from_user->last_name;
                            }else{
                                $notification_from_business_user = BusinessDetails::where('user_id',$value->notification_from_user)->first();
                                $value->notification_from_user_name = $notification_from_business_user->business_name;
                            }
                        }else{
                            $value->unique_id = null;
                            $value->user_profile = null;
                            $value->user_slug = null;
                            $value->user_type = null;
                            $value->notification_from_user_name = null;
                        }
                    }
                    $value->notification_post_title = null;
                    $value->spot_user_name = null;
                    $value->spot_user_slug = null;
                    $value->spot_user_unique_id = null;
                    if ($value->invited_spot_id!=null) {
                        $spot_user = User::where('id',$value->invited_spot_id)->first();
                        if ($spot_user) {
                            $spot_business_user = BusinessDetails::where('user_id',$value->invited_spot_id)->first();
                            $value->spot_user_name = $spot_business_user->business_name;
                            $value->spot_user_slug = $spot_user->user_slug;
                            $value->spot_user_unique_id = $spot_user->unique_id;
                        }
                    }
                    if ($value->invited_group_id!=null) {
                        $group = Group::where('id',$value->invited_group_id)->first();
                        if ($group) {
                            $value->group_name = $group->group_name;
                            $value->group_slug = $group->group_slug;
                            $value->group_unique_id = $group->group_unique_id;
                        }
                    }
                    if ($value->invited_event_id!=null) {
                        $event = Event::where('id',$value->invited_event_id)->first();
                        if ($event) {
                            $value->event_title = $event->event_title;
                            $value->event_slug = $event->event_slug;
                            $value->event_unique_id = $event->event_unique_id;   
                        }
                            
                    }
                    if ($value->post_id!=null) {
                        $check_in_details = CheckInDetail::where('id',$value->post_id)->first();
                        if ($check_in_details) {
                            if ($check_in_details->spot_description!=null) {
                                $value->notification_post_title = $check_in_details->spot_description;
                            }
                        }else{
                            $value->notification_post_title = null;
                        }
                        
                    }
                    $value->notification_time = Carbon::parse($value->notification_time)->diffForHumans();
                }
            }
            //print_r(json_decode(json_encode($all_notifications)));

            return response()->json([
                'all_notifications' => $all_notifications,
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

    public function changeStatus(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();        
        if($user) {
            Notification::where('notification_to_user',$user->id)->update(['notification_read'=>1]);
        }
    }

    public function addReview(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if($user) {
            $spot_details = SpotDetail::where('user_id',$user->id)->where('spot_id',$request->input('spot_id'))->first();
            $files = $request->file('file');
            $spot_id = $request->input('spot_id');
            $rating = 0;
            if($request['rating']!=null && isset($request['rating'])){
                if ((int)$request['rating'] > 10) {
                    $rating = 0;
                }else{
                    $rating = $request['rating'];
                }
                
            }
            $send_request = [];
            if($spot_details){
                $send_request = array(
                    'spot_id' => $spot_id,
                    'rating' => $rating,
                    'review' => $request->input('review'),
                    'review_date_time' => now(),
                    'user_id' => $user->id
                );
            }else{
                $send_request = array(
                    'review_date_time' => now(),
                    'rating' => $rating,
                    'review' => $request->input('review')
                );
            }
            if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
            {
                $imageExtensions = ['png','jpeg','jpg','tiff','pjp','pjpeg','jfif','tif','gif','svg','bmp','svgz','webp','ico','xbm','dib'];
                $videoExtensions = ['mp4','ogm','wmv','mpg','webm','ogv','mov','asx','mpeg','m4v','avi'];
                $photo_array = [];
                $video_array = [];
                foreach ($files as $file) {
                    $filename = $file->getClientOriginalName();
                    //$filemimetype = $file->getMimeType();
                    $extension = $file->getClientOriginalExtension();
                    if (in_array($extension, $imageExtensions)) {
                        $picture = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_review_images/'), $picture);
                        array_push($photo_array, $picture);
                    }else if(in_array($extension, $videoExtensions)){
                        $video = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_review_videos/'), $video);
                        array_push($video_array, $video);
                    }   
                }
                $send_request['review_photos'] = serialize($photo_array);
                $send_request['review_videos'] = serialize($video_array);

            }
            if ($spot_details) {
                SpotDetail::where('id',$spot_details->id)->update($send_request);
                $message ='Review saved.';
                $status_code = 200;
                $status = true;
            }else{
                SpotDetail::create($send_request);
                $message ='Review added.';
                $status_code = 200;
                $status = true;
            }
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function addFilesByUser(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if($user) {
            $spot_details = SpotPhotoVideo::where('user_id',$user->id)->where('spot_id',$request->input('spot_id'))->first();
            $files = $request->file('file');
            
            $spot_id = $request->input('spot_id');
            $send_request = array(
                'spot_id' => $spot_id,
                'user_id' => $user->id,
            );
            if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
            {
                $imageExtensions = ['png','jpeg','jpg','tiff','pjp','pjpeg','jfif','tif','gif','svg','bmp','svgz','webp','ico','xbm','dib'];
                $videoExtensions = ['mp4','ogm','wmv','mpg','webm','ogv','mov','asx','mpeg','m4v','avi'];
                $photo_array = [];
                $video_array = [];
                foreach ($files as $file) {
                    $filename = $file->getClientOriginalName();
                    //$filemimetype = $file->getMimeType();
                    $extension = $file->getClientOriginalExtension();
                    if (in_array($extension, $imageExtensions)) {
                        $picture = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_photos_by_user/'), $picture);
                        array_push($photo_array, $picture);
                    }else if(in_array($extension, $videoExtensions)){
                        $video = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_videos_by_user/'), $video);
                        array_push($video_array, $video);
                    }   
                }
                $send_request['user_to_spot_photos'] = serialize($photo_array);
                $send_request['user_to_spot_videos'] = serialize($video_array);
            }
            

            SpotPhotoVideo::create($send_request);
            $message ='Photos/Videos added.';
            $status_code = 200;
            $status = true;
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function updateFilesByUser(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if($user) {
            $spot_details = SpotPhotoVideo::where('user_id',$user->id)->where('spot_id',$request->input('spot_id'))->first();
            $files = $request->file('file');
            
            $photo_array = [];
            $video_array = [];

            if (isset($request['db_files_array']) && $request['db_files_array']!='') {
                $db_files = explode(',', $request['db_files_array']);
                $imageExtensions = ['png','jpeg','jpg','tiff','pjp','pjpeg','jfif','tif','gif','svg','bmp','svgz','webp','ico','xbm','dib'];
                $videoExtensions = ['mp4','ogm','wmv','mpg','webm','ogv','mov','asx','mpeg','m4v','avi'];

                foreach ($db_files as $value) {
                    $variable = trim(substr($value, strpos($value, '.') + 1));
                    if(in_array($variable, $imageExtensions)){
                        array_push($photo_array, $value);    
                    }
                    if (in_array($variable, $videoExtensions)) {
                        array_push($video_array, $value);
                    }
                }
            }

            if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
            {
                $imageExtensions = ['png','jpeg','jpg','tiff','pjp','pjpeg','jfif','tif','gif','svg','bmp','svgz','webp','ico','xbm','dib'];
                $videoExtensions = ['mp4','ogm','wmv','mpg','webm','ogv','mov','asx','mpeg','m4v','avi'];
                foreach ($files as $file) {
                    $filename = $file->getClientOriginalName();
                    //$filemimetype = $file->getMimeType();
                    $extension = $file->getClientOriginalExtension();
                    if (in_array($extension, $imageExtensions)) {
                        $picture = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_photos_by_user/'), $picture);
                        array_push($photo_array, $picture);
                    }else if(in_array($extension, $videoExtensions)){
                        $video = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_videos_by_user/'), $video);
                        array_push($video_array, $video);
                    }   
                }
            }
            $send_request['user_to_spot_photos'] = serialize($photo_array);
            $send_request['user_to_spot_videos'] = serialize($video_array);
            SpotPhotoVideo::where('id',$spot_details->id)->update($send_request);
            $message ='Photos/Videos Updted.';
            $status_code = 200;
            $status = true;
           
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function addReply(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if($user) {
            $extra_details = SpotDetail::where('id',$request->input('review_id'))->first(); 
            //$spot_details = SpotDetail::where('user_id',$user->id)->where('id',$request->input('review_id'))->first();
            
                $send_request = array(
                    'spot_id' => $extra_details->spot_id,
                    'review' => $request->input('reply'),
                    'review_date_time' => now(),
                    'parent_review_id' => $request->input('review_id'),
                    'user_id' => $extra_details->spot_id
                );
                SpotDetail::create($send_request);
                $message ='Reply added.';
                $status_code = 200;
                $status = true;
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function updateReply(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if($user) {
            //$extra_details = SpotDetail::where('id',$request->input('review_id'))->first(); 
            
            $send_request = array(
                'review' => $request->input('reply')                
            );
            SpotDetail::where('id',$request->input('review_id'))->update($send_request);
            $message ='Reply updated.';
            $status_code = 200;
            $status = true;
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function deleteReply(Request $request,$id)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if($user) {
            SpotDetail::where('id',$id)->delete(); 
            $message ='Reply deleted.';
            $status_code = 200;
            $status = true;
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function updateReview(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if($user) {
            $spot_details = SpotDetail::where('user_id',$user->id)->where('spot_id',$request->input('spot_id'))->first();
            $files = $request->file('file');
            $spot_id = $request->input('spot_id');
            $rating = $spot_details->rating;
            if($request['rating']!=null && isset($request['rating'])){

                if ((int)$request['rating'] > 10) {
                    $rating = 0;
                }else{
                    $rating = $request['rating'];
                }
                
            }
            
            $send_request = array(
                'review_date_time' => now(),
                'rating' => $rating,
                'review' => $request->input('review')
            );
            $photo_array = [];
            $video_array = [];

            if (isset($request['db_files_array']) && $request['db_files_array']!='') {
                $db_files = explode(',', $request['db_files_array']);
                $imageExtensions = ['png','jpeg','jpg','tiff','pjp','pjpeg','jfif','tif','gif','svg','bmp','svgz','webp','ico','xbm','dib'];
                $videoExtensions = ['mp4','ogm','wmv','mpg','webm','ogv','mov','asx','mpeg','m4v','avi'];

                foreach ($db_files as $value) {
                    $variable = trim(substr($value, strpos($value, '.') + 1));
                    if(in_array($variable, $imageExtensions)){
                        array_push($photo_array, $value);    
                    }
                    if (in_array($variable, $videoExtensions)) {
                        array_push($video_array, $value);
                    }
                }
            }

            if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
            {
                $imageExtensions = ['png','jpeg','jpg','tiff','pjp','pjpeg','jfif','tif','gif','svg','bmp','svgz','webp','ico','xbm','dib'];
                $videoExtensions = ['mp4','ogm','wmv','mpg','webm','ogv','mov','asx','mpeg','m4v','avi'];
                foreach ($files as $file) {
                    $filename = $file->getClientOriginalName();
                    //$filemimetype = $file->getMimeType();
                    $extension = $file->getClientOriginalExtension();
                    if (in_array($extension, $imageExtensions)) {
                        $picture = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_review_images/'), $picture);
                        array_push($photo_array, $picture);
                    }else if(in_array($extension, $videoExtensions)){
                        $video = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_review_videos/'), $video);
                        array_push($video_array, $video);
                    }   
                }
            }
            $send_request['review_photos'] = serialize($photo_array);
            $send_request['review_videos'] = serialize($video_array);
            SpotDetail::where('id',$spot_details->id)->update($send_request);
            $message ='Review saved.';
            $status_code = 200;
            $status = true;
           
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function deleteReview(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if($user) {
            $spot_details = SpotDetail::where('user_id',$user->id)->where('spot_id',$request->input('spot_id'))->first();
            $send_request = array(
                'rating' => null,
                'review' => null,
                'review_photos' => null,
                'review_videos' => null,
            );
            SpotDetail::where('id',$spot_details->id)->update($send_request);
            $message ='Review deleted.';
            $status_code = 200;
            $status = true;
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    public function demoaddSpot(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if($user) {
            $tag_users = $request->input('tag_friends');
            $files = $request->file('file');
            $spot_id = $request->input('spot_id');
            $send_request = array(
                'spot_id' => $spot_id,
                'tagged_users' => $tag_users,
                'share_with_friends'=>$request->input('share_friends'),
                'spot_description' => $request->input('review'),
                'checked_in_datetime' => now(),
                'user_id' => $user->id
            );

           
            if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
            {
                $imageExtensions = ['png','jpeg','jpg','tiff','pjp','pjpeg','jfif','tif','gif','svg','bmp','svgz','webp','ico','xbm','dib'];
                $videoExtensions = ['mp4','ogm','wmv','mpg','webm','ogv','mov','asx','mpeg','m4v','avi'];
                $audioExtensions = ['mp3'];
                $photo_array = [];
                $video_array = [];
                $audio_array = [];
                $file_extension=[];
                foreach ($files as $file) {
                    $filename = $file->getClientOriginalName();
                    //$filemimetype = $file->getMimeType();
                    $extension = $file->getClientOriginalExtension();
                    if (in_array($extension, $imageExtensions)) {
                        $picture = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_images/'), $picture);
                        array_push($photo_array, $picture);
                    }else if(in_array($extension, $videoExtensions)){
                        $video = date('dmyHis').'-'.$filename;
                       $file->move(public_path('spot_videos/'), $video);
                        array_push($video_array, $video);
                    }else if(in_array($extension,$audioExtensions)){
                        $audio = date('dmyHis').'-'.$filename;
                        $file->move(public_path('spot_audio/'),$audio);
                        array_push($audio_array, $audio); 
                    }   
                }

                $send_request['photos'] = serialize($photo_array);
                $send_request['videos'] = serialize($video_array);
                $send_request['audios'] = serialize($audio_array);

            }
            CheckInDetail::create($send_request);
            $message ='Spot saved.';
            $status_code = 200;
            $status = true;
        }else{
            $message ='User not found.';
            $status_code = 404;
            $status = false;
        }
        return response()->json([
            'message'=> $message,
            'status_code' => $status_code,
            'status' => $status,
        ]);
    }

    
}
