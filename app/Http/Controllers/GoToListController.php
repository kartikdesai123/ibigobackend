<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Group;
use App\User;
use DB;
use App\FriendRelation;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\GroupUser;
use App\Event;
use App\Notification;
use App\CheckInDetail;
use App\PostComment;
use App\PostLike;
use App\GoToList;
use App\Events\UserNotification;
use App\BusinessDetails;
use App\Planning;
use DateTime;

class GoToListController extends Controller
{
    public function addUpdateGoto(Request $request)
    {
    	$token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $spots = [];
            if ($request->input('spot_id')!=null || $request->input('spot_id')!='') {
                $spots = explode(',', $request->input('spot_id')); 
            }
            if (count($spots)> 0) {
                GoToList::whereNotIn('spot_id',$spots)->where('user_id',$user_id)->delete();
                foreach ($spots as $value) {
                    $mydemo = GoToList::where('spot_id',$value)->where('user_id',$user_id)->first();
                    if (!$mydemo) {
                        $goto_array = array(
                            'spot_id' => $value,
                            'user_id' => $user_id,
                            'is_liked' => 0,
                        );
                        $goto = GoToList::create($goto_array);
                    }
                }

            }
        	return response()->json([
                'message' => 'created',
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

    public function getGoto(Request $request)
    {
    	$token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
        	$goto_list = GoToList::select('business_details.business_name','users.user_slug','users.unique_id','business_details.full_address','users.user_profile','go_to_lists.spot_id','go_to_lists.is_liked','go_to_lists.id')
        				->where('go_to_lists.user_id',$user_id)
        				->join('users','users.id','=','go_to_lists.spot_id')
        				->join('business_details','business_details.user_id','=','users.id')
        				->get();

        	return response()->json([
                'message' => 'created',
                'goto_list' => $goto_list,
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

    public function likeGoTo(Request $request,$goto_id)
    {
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $goto_update = GoToList::where('id',$goto_id)->first();
            if ($goto_update) {
                if ($goto_update->is_liked==0) {
                    GoToList::where('id',$goto_id)->update(['is_liked'=>1]);
                }else{
                    GoToList::where('id',$goto_id)->update(['is_liked'=>0]);
                }
            }
            return response()->json([
                'message' => 'updated',
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

    public function addPlanning(Request $request)
    {
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        if ($user) {
            $user_id = $user->id;
            $spot_id = $request->input('spot_id');
            $event_id = $request->input('event_id');
            $planning_description = $request->input('planning_description');
            $planning_date = $request->input('planning_date');
            if ($request->input('event_id')=='null') {
                $event_id = null;
            }
            $planning_detail = Planning::where('event_id',$event_id)->where('user_id',$user_id)->get();
            if ($request->input('spot_id')=='null') {
                $spot_id = null;
                $event_detail = Event::where('id',$request->input('event_id'))->first();
                //print_r($event_detail->start_date_time);
                $planning_date = Carbon::parse($event_detail->start_date_time)->format('Y-m-d');                
            }
            if ($request->input('planning_description')=='null') {
                $planning_description = null;
            }
            $planning = array(
                'user_id' => $user_id,
                'spot_id' => $spot_id,
                'event_id' => $event_id,
                'planning_date' => $planning_date,
                'planning_description' => $planning_description,
                'is_liked' => 0,
            );
            if ($event_id!=null && count($planning_detail) == 0) {
                Planning::create($planning);    
            }else if ($event_id==null){
                Planning::create($planning);
            }
            
            return response()->json([
                'message' => 'created',
                'event_message' => 'Event is added to planning.',
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

    public function getPlanning(Request $request)
    {
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        $user_id = $user->id;
        if ($user) {
            $today = Carbon::now()->format('Y-m-d');
            $today_plannings = Planning::select('plannings.*','business_details.full_address','business_details.business_name','users.unique_id','users.user_slug','users.user_profile')
            ->leftjoin('users','users.id','=','plannings.spot_id')
            ->leftjoin('events','events.id','=','plannings.event_id')
            ->leftjoin('business_details','business_details.user_id','=','users.id')->where('plannings.user_id',$user_id)->where('planning_date',$today)->get();
            $plan_ids = [];
            if (count($today_plannings) > 0) {
                $plan_ids = $today_plannings->pluck('plannings.id')->toArray();
            }
            $nextweek_plannings = Planning::select('plannings.*','business_details.full_address','business_details.business_name','users.unique_id','users.user_slug','users.user_profile','event_title','event_slug','event_unique_id','location','event_cover')
            ->leftjoin('users','users.id','=','plannings.spot_id')
            ->leftjoin('events','events.id','=','plannings.event_id')
            ->leftjoin('business_details','business_details.user_id','=','users.id')->where('plannings.user_id',$user_id)->whereBetween('planning_date', [now()->endOfWeek()->format('Y-m-d'),now()->endOfWeek()->addDays(1)->endOfWeek()->format('Y-m-d')])->get();
            $next_plan_ids = [];
            if (count($nextweek_plannings) > 0) {
                $next_plan_ids = $nextweek_plannings->pluck('plannings.id')->toArray();
            }

            $plan_ids = array_merge($plan_ids,$next_plan_ids);
            $current_date = date('d');
            $current_month = date('m');
            $current_year = date('Y');
            $thismonth_plannings = Planning::select('plannings.*','business_details.full_address','business_details.business_name','users.unique_id','users.user_slug','users.user_profile')
            ->leftjoin('users','users.id','=','plannings.spot_id')
            ->leftjoin('events','events.id','=','plannings.event_id')
            ->leftjoin('business_details','business_details.user_id','=','users.id')->where('plannings.user_id',$user_id)->whereMonth('planning_date','=', $current_month)->whereYear('planning_date','=', $current_year)->where('planning_date','>',$today)
            ->whereNotIn('plannings.id',$plan_ids)
            ->get();            
            

            return response()->json([
                'today_plannings' => $today_plannings,
                'nextweek_plannings' => $nextweek_plannings,
                'thismonth_plannings' => $thismonth_plannings,
                'message' => 'created',
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

    public function getOtherSpots(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if ($user) {
            $id = $user->id;
            $liked_spots = DB::table('spot_details')->select('spot_id','users.user_profile','business_details.business_name','business_details.short_description')
                ->join('business_details','business_details.user_id','=','spot_details.spot_id')
                ->join('users','users.id','=','spot_details.spot_id')
                ->where(function ($query) {
                    $query->where('is_like', 1)
                          ->orWhere('is_connected',1);
                })
                ->where('spot_details.user_id',$id)
                ->groupBy('spot_id')
                ->get();
                $spot_ids = $liked_spots->pluck('spot_id')->toArray();
                $other_spots = DB::table('business_details')
                ->join('users','users.id','=','business_details.user_id')
                ->select('users.user_profile','business_details.business_name','business_details.short_description','user_id as spot_id')
                ->whereNotIn('user_id',$spot_ids)
                ->get();
                return response()->json([
                    'other_spots' => $other_spots,
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

    public function addToGoto(Request $request)
    {
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        if ($user) {
            $user_id = $user->id;
            if ($request->input('spot_id')!=null || $request->input('spot_id')!='') {
                $spot = $request->input('spot_id'); 
                $mydemo = GoToList::where('spot_id',$spot)->where('user_id',$user_id)->first();
                if (!$mydemo) {
                    $goto_array = array(
                        'spot_id' => $spot,
                        'user_id' => $user_id,
                        'is_liked' => 0,
                    );
                    $goto = GoToList::create($goto_array);
                }
                
            }
            
            return response()->json([
                'message' => 'created',
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

    public function getCalendarEvents(Request $request)
    {
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        // $user = User::where('id',25)->first();
        if ($user) {
            $user_id = $user->id;
            $plannings = Planning::where('user_id',$user_id)->get();
            //echo "<pre>";
            foreach ($plannings as $key => $value) {
                if($value->planning_description==null || $value->planning_description==''){
                    if ($value->event_id!=null) {
                        $event = Event::where('id',$value->event_id)->first();
                        $value->title = $event->event_title;
                    }
                    if ($value->spot_id!=null) {
                        $event = BusinessDetails::where('id',$value->spot_id)->first();
                        $value->title = $event->business_name;
                    }
                }else{
                    $value->title = $value->planning_description;    
                }                
                $value->start = $value->planning_date;
                $value->allDay = true;
            }
            //print_r(json_decode(json_encode($plannings)));
            //die;
            return response()->json([
                'events' => $plannings,
                'message' => 'created',
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
