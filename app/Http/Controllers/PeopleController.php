<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\FriendRelation;
use Carbon\Carbon;
use App\Admin;
use Validator;
use App\User;
use App\interest;
use DB;
use App\SpotDetail;
use App\SpotPhotoVideo;
class PeopleController extends Controller
{
    public function getAllFriends(Request $request)
    {
        $searchText = $request->input('searchText');
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
            $friend_list = User::select('id','user_profile','first_name','user_slug','unique_id','last_name',DB::raw("CONCAT(first_name,' ',last_name) AS user_name"))->where(function ($q) use ($user_id,$user,$searchText) {
                                    $q->where('last_name', 'like', '%' . $searchText . '%')
                                    ->orWhereRaw("concat(first_name, ' ', last_name) like '%".$searchText."%' ")
                                    ->orWhereRaw("concat(last_name, ' ', first_name) like '%".$searchText."%' ")
                                    ->orWhere('first_name', 'like', '%' . $searchText . '%')->get();
                                })->whereIn('id', $users_id_array)->get();
            return response()->json([
                'friend_list'=> $friend_list,
                'status_code' => 200,
                'status' => true,
            ]);
        }
    }

    public function getAllPeoples(Request $request)
    {
        $searchText = $request->input('searchText');
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
            
            $users_id_array = array_unique($users_id_array);
            if (($key = array_search($user_id, $users_id_array)) !== false) {
                unset($users_id_array[$key]);
            }

            $people_list = User::select('id','user_profile','first_name','user_slug','unique_id','last_name',DB::raw("CONCAT(first_name,' ',last_name) AS user_name"))->where(function ($q) use ($user_id,$user,$searchText) {
                                    $q->where('last_name', 'like', '%' . $searchText . '%')
                                    ->orWhereRaw("concat(first_name, ' ', last_name) like '%".$searchText."%' ")
                                    ->orWhereRaw("concat(last_name, ' ', first_name) like '%".$searchText."%' ")
                                    ->orWhere('first_name', 'like', '%' . $searchText . '%')->get();
                                })->whereNotIn('id', $users_id_array)->where('user_type','normal')->get();
            return response()->json([
                'people_list'=> $people_list,
                'status_code' => 200,
                'status' => true,
            ]);
        }
    }

    public function getPeople(Request $request,$id)
    {
        
        $token = $request->header('Authorization');
        if($token)
            $user = User::where('user_api_token',$token)->first();
        else
            $user = null;
        $spot_user = User::select('id','first_name','last_name','user_interests','user_profile','user_about','mobile','user_cover','unique_id','user_slug','birth_date')->where('unique_id',$id)->first();
        if($spot_user){
        	$id=$spot_user->id;
        	$user_interests = explode(',', $spot_user->user_interests);

			$years = Carbon::parse($spot_user->birth_date)->age;
			$spot_user->age = $years;
		
			$interests = interest::select('title')->whereIn('id',$user_interests)->get();
			$friend_relation = [];
            $friend_relation_count = [];
            if ($user!=null) {
            	$friend_relation = FriendRelation::where(function ($q) use ($id,$user) {
				                    $q->where('to_user_id', $user->id)
				                          ->where('from_user_id', $id);
				                })->orWhere(function ($query) use ($id,$user) {
				                    $query->where('from_user_id', $user->id)
				                          ->where('to_user_id', $id);
				                })->first();

                
            }
            $reviews = SpotDetail::select('spot_id','id','user_id','rating','review','review_date_time','review_photos','review_videos')
                ->where(function ($query) {
                    $query->whereNotNull('review')
                          ->orWhereNotNull('rating');
                })->where('user_id',$id)->whereNull('parent_review_id')->orderBy('review_date_time','desc')->get();
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
                $spot_review_user = User::select('users.id','business_details.business_name','users.user_profile','users.unique_id','users.user_slug')
                                ->join('business_details','business_details.user_id','=','users.id')
                                ->where('users.id',$value->spot_id)
                                ->first();
                $value->spot_name = $spot_review_user->business_name;
                $value->spot_profile = $spot_review_user->user_profile;
                $value->spot_slug = $spot_review_user->user_slug;
                $value->spot_unique_id = $spot_review_user->unique_id;
                $value->user_profile = $rated_user->user_profile;
                $value->first_name = $rated_user->first_name;
                $value->review_date_time = date('F, d Y h:i A', strtotime($value->review_date_time));
                $value->last_name = $rated_user->last_name;
                $value->unique_id = $rated_user->unique_id;
                $value->user_slug = $rated_user->user_slug;
            }
            $friend_relation_count = FriendRelation::select('from_user_id','to_user_id')->where(function ($q) use ($id,$user) {
                                $q->where('to_user_id', $id)
                                ->where('relation_status',1)->get();
                            })->orWhere(function ($query) use ($id,$user) {
                                $query->where('from_user_id', $id)
                                      ->where('relation_status',1)->get();
                            })->get();
            $users_id_array = [];
            foreach ($friend_relation_count as $value) {
                array_push($users_id_array, $value->to_user_id);
                array_push($users_id_array, $value->from_user_id);
            }
            $review_count = SpotDetail::where('user_id',$id)->whereNotNull('rating')->count();
            $review_places = SpotDetail::select('spot_id','rating')->where('user_id',$id)->whereNotNull('rating')->limit(4)->get();
            $spot_photos_videos_by_user = SpotPhotoVideo::where('user_id',$id)->get();
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
            $liked_places = SpotDetail::select('spot_id',)->where('user_id',$id)->where('is_like',1)->limit(4)->get();
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
            if (($key = array_search($id, $users_id_array)) !== false) {
                unset($users_id_array[$key]);
            }
            $friends = User::select('id','user_slug','unique_id','first_name','last_name')->whereIn('id',$users_id_array)->get();

            return response()->json([
                    'reviews'=>$reviews,
                    'friends_count' => count($users_id_array),
                    'friends' => $friends,
                    'review_count' => $review_count,
                    'liked_places' => $liked_places,
                    'spot_videos_by_user' => $spot_videos_by_user,
                    'spot_photos_by_user' => $spot_photos_by_user,
                    'review_places' => $review_places,
            		'user_interests' => $interests,
            		'people_details'=>$spot_user,
                    'friend_relation' => $friend_relation,
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
}
