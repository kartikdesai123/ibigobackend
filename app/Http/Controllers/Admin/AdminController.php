<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Hash;
use App\Admin;
use Lcobucci\JWT\Parser; 
use Illuminate\Support\Str;
use App\CheckInDetail;
use App\SpotDetail;
use App\PostLike;
use App\Group;
use Carbon\Carbon;
use App\PostComment;
use App\BusinessDetails;
use App\User;




class AdminController extends Controller
{
    public function login(Request $request)
    {
    	$rules = [
             'email' => 'required|string|email|max:255',
             'password' => 'required|string',
         ];

         $messages = [
             'email.required' => 'Please enter your email',
             'email.email' => 'Please enter valid email',
             'email.max' => 'Maximum 255 character will be allow',
             'password.required' => 'Please enter your password',  
         ];

        $validator = Validator::make($request->all(), $rules, $messages);
        
        if($validator->fails())
        {
            return response()->json([
            	'error' => $validator->errors()->all(),
            	'status_code' => 401,
            	'status' => false
            ], 401);
        } 
	    $finduser = Admin::firstOrNew(['email' => $request->email]);
        
	    if($finduser->exists)
        {
        	if(Hash::check($request->password, $finduser->password))
        	{
                $token = '';
                if ($finduser->api_token == NULL) {
                    $token = uniqid(base64_encode(Str::random(60)));
                    Admin::where('id',$finduser->id)->update(['api_token'=>$token]);
                }else{
                    $token = $finduser->api_token;
                }
                
        		//$token = $finduser->createToken('Laravel Password Grant Client')->accessToken;
                
        		return response()->json([
		        		'message' => 'Login successfully.',
		        		'token' => $token,
		        		'user' => $finduser->first_name.' '.$finduser->last_name,
		        		'status_code' => 200,
		        		'status' => true
		        	]);
        	}else{
        		return response()->json([
		        		'message' => 'Password is incorrect.',
		        		'status' => false
		        	]);
        	}
        }
        else
        {
	    	//$token = $user->createToken('Laravel Password Grant Client')->accessToken;
	    	return response()->json([
	        		'message' => 'This email is not matched with record.',
	        		'status_code' => 404,
	        		'status' => false
	        	]);
        }
        
    }

    public function checkinList()
    {

        $checkin_details=CheckInDetail::select('check_in_details.*','business_details.business_name','users.first_name','users.last_name','users.user_profile')->join('users','users.id','=','check_in_details.user_id')->join('business_details','business_details.user_id','=','check_in_details.spot_id')->get()->toArray();

        return response()->json([
            'posts' => $checkin_details,
            'status_code' => 200,
            'status' => true,
        ]);

    }

    public function postList(Request $request)
    {
        $checkin_details = CheckInDetail::select('check_in_details.*','users.first_name','users.last_name','users.user_profile')->join('users','users.id','=','check_in_details.user_id')
        ->where('spot_id',null)
        ->where('user_type','normal')
        ->get()->toArray();

        return response()->json([
            'posts' => $checkin_details,
            'status_code' => 200,
            'status' => true,
        ]);
    }

    public function spotPostList(Request $request)
    {
        $checkin_details = CheckInDetail::select('check_in_details.*','users.first_name','users.last_name','users.user_profile','business_details.business_name','users.user_type')
        ->join('users','users.id','=','check_in_details.user_id')
        ->join('business_details','business_details.user_id','=','users.id')
        ->where('spot_id',null)
        ->where('user_type','business')
        ->get()->toArray();

        return response()->json([
            'posts' => $checkin_details,
            'status_code' => 200,
            'status' => true,
        ]);
    }

    public function removePost($id)
    {

        CheckInDetail::where('id',$id)->delete();
        //  $user = User::where('id',$check_in_details->user_id)->first();
        //$check_in_details->delete();
        return response()->json([
            'message' => 'Post Deleted',
            'status_code' => 200,
            'status' => true,
        ]);

    }

    public function spotDetails($checkin_id='')
    {
            $posts = CheckInDetail::where('id',$checkin_id)->orderBy('checked_in_datetime','desc')->get();
            if (count($posts) > 0) {
                foreach ($posts as $key => $value) {
                    $likes = PostLike::where('post_id',$value->id)->first();
                    $value->liked_users_names = [];
                    $value->likes = 0;
                    if ($likes) {
                        if ($likes->liked_users!=null || $likes->liked_users!='') {
                            $value->likes = count(explode(',', $likes->liked_users));
                        }
                    }
                    if ($value->spot_description==null) {
                        $value->spot_description = '';
                    }
                    $value->liked_by_logged_in_user = 0;
                    $comments = PostComment::where('post_id',$value->id)->orderBy('comment_date_time','desc')->get();
                    if (count($comments)>0) {
                        foreach ($comments as $c) {
                            $comment_user = User::where('id',$c->comment_user_id)->first();
                            $c->comment_user_name = $comment_user->first_name.' '.$comment_user->last_name;
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
                            //$c->comment_date_time = chop($c->comment_date_time,'ago');
                        }
                    }
                    $from_user = User::where('id',$value->user_id)->first();
                    $value->comments = $comments;                    
                    $value->user_id = $from_user->id;
                    if ($from_user->user_type=='normal') {
                        $value->user_name = $from_user->first_name.' '.$from_user->last_name;
                    }else{
                        $buser = BusinessDetails::where('user_id',$from_user->id)->first();
                        $value->user_name = $buser->business_name;
                    }
                    if($value->spot_id!=null){
                        $spot_user = BusinessDetails::where('user_id',$value->spot_id)->first();
                        $value->spot_name = $spot_user->business_name;
                        $value->spot_id = $spot_user->user_id;
                        $value->user_profile = $from_user->user_profile;
                    }

                    if($value->group_id!=null){
                        $group = Group::where('id',$value->group_id)->first();
                        $value->group_name = $group->group_name;
                        $value->group_unique_id = $group->group_unique_id;
                        $value->group_profile = $group->group_profile;
                        $value->group_slug = $group->group_slug;
                    }
                    
                    $tagged_user_id = explode(",", $value->tagged_users);
                    $photos = unserialize($value->photos);
                    
                    $videos = unserialize($value->videos);
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
                                array_push($tagged_users_names, $to_user->first_name.' '.$to_user->last_name);
                            }
                        }
                        $value->tagged_users_names = $tagged_users_names;
                    }
                    
                    $value->checked_in_datetime = Carbon::parse($value->checked_in_datetime)->diffForHumans();
                }
            }

            return response()->json([
                'post' => $posts,
                'status_code' => 200,
                'status' => true,
            ]);
    }

    public function checkinDetails($checkin_id='')
    {
        
            $post_ids = [];
            $tagged_posts = CheckInDetail::select('id','tagged_users')->where('id',$checkin_id)->get();
            
            if(count($tagged_posts) > 0){
                foreach ($tagged_posts as $value) {
                    $t_users = explode(',', $value->tagged_users);
                    if (count($t_users) > 0) {
                        if($value->tagged_users!=null){
                           // if (in_array($user_id, $t_users)) {
                                array_push($post_ids, $value->id);
                            //}
                        }
                    }
                }    
            }
            
            $posts = CheckInDetail::where('id',$checkin_id)->orWhereIn('id',$post_ids)->orderBy('checked_in_datetime','desc')->get();
            if (count($posts) > 0) {
                foreach ($posts as $key => $value) {
                    $likes = PostLike::where('post_id',$value->id)->first();
                    $value->liked_users_names = [];
                    $value->likes = 0;
                    if ($likes) {
                        if ($likes->liked_users!=null || $likes->liked_users!='') {
                            $value->likes = count(explode(',', $likes->liked_users));
                        }
                    }
                    if ($value->spot_description==null) {
                        $value->spot_description = '';
                    }
                    $value->liked_by_logged_in_user = 0;
                    $comments = PostComment::where('post_id',$value->id)->orderBy('comment_date_time','desc')->get();
                    if (count($comments)>0) {
                        foreach ($comments as $c) {
                            $comment_user = User::where('id',$c->comment_user_id)->first();
                            $c->comment_user_name = $comment_user->first_name.' '.$comment_user->last_name;
                            $c->comment_user_profile = $comment_user->user_profile;
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
                            }
                            $c->comment_date_time = chop($c->comment_date_time,'ago');
                        }
                    }
                    $from_user = User::where('id',$value->user_id)->first();
                    $value->comments = $comments;
                    $value->user_name = $from_user->first_name.' '.$from_user->last_name;
                    $value->user_id = $from_user->id;
                    
                    if($value->spot_id!=null){
                        $spot_user = BusinessDetails::where('user_id',$value->spot_id)->first();
                        $value->spot_name = $spot_user->business_name;
                        $value->spot_id = $spot_user->user_id;
                        $value->user_profile = $from_user->user_profile;
                    }
                    
                    $tagged_user_id = explode(",", $value->tagged_users);
                    $photos = unserialize($value->photos);
                    
                    $videos = unserialize($value->videos);
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
                                array_push($tagged_users_names, $to_user->first_name.' '.$to_user->last_name);
                            }
                        }
                        $value->tagged_users_names = $tagged_users_names;
                    }
                    
                    $value->checked_in_datetime = Carbon::parse($value->checked_in_datetime)->diffForHumans();
                }
            }


            return response()->json([
                'post' => $posts,
                'status_code' => 200,
                'status' => true,
            ]);
    }


    public function removeReview(Request $request,$review_id)
    {

        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $spot_detail=SpotDetail::where('id',$review_id)->first();

            if(!empty($spot_detail))
            {
                SpotDetail::where('id',$review_id)->update(['rating'=> null,'review'=>null]);

                return response()->json([
                        'message'=>'Successfully review is deleted',
                        'status_code' =>200,
                        'status' => true,
                    ]);
            }
            else
            {
                 return response()->json([
                        'message'=>'Review not found',
                        'status_code' =>404,
                        'status' => false,
                    ]);
            }

        }
        else
        {
            return response()->json([
                'message' => 'User not found.',
                'status_code' => 404,
                'status' => false,
            ]);
        }

        
    }

    public function removeComment(Request $request,$id)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $comment_details= PostComment::where('id',$id)->first();
            if(!empty($comment_details))
            {
                PostComment::where('id',$id)->delete();
                return response()->json([
                    'message' => 'Deleted',
                    'status_code' => 200,
                    'status' => true,
                ]);
            }
            else
            {
                return response()->json([
                        'message'=>'Comment not found',
                        'status_code' =>404,
                        'status' => false,
                    ]);
            }
            
        }else{
            return response()->json([
                'message' => 'User not found.',
                'status_code' => 404,
                'status' => false,
            ]);
        }
    }

    public function postReviewList(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $spotDetails=SpotDetail::whereNotNull('rating')->orwhereNotNull('review')->get();

            if(!empty($spotDetails))
            {
                foreach($spotDetails as $key => $value)
                {
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
                    $value->review_date_time = date('F, d Y h:i A', strtotime($value->review_date_time));
                    $value->last_name = $rated_user->last_name;
                }

                 return response()->json([
                    'reviews'=>$spotDetails,
                    'status_code' => 200,
                    'status' => true,
                ]);

            }
            else
            {
                return response()->json([
                    'review'=>[],
                    'message' => 'No any review found',
                    'status_code' => 200,
                    'status' => true,
                ]);
            }
        }
        else
        {
             return response()->json([
                    'message' => 'User not found.',
                    'status_code' => 404,
                    'status' => false,
                ]);
        }
    }

}
