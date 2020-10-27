<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\FriendRelation;
use Carbon\Carbon;
use App\Admin;
use Validator;
use App\User;
use App\interest;
use App\Group;
use App\Event;
use DB;
use App\SpotDetail;
use DateTime;
use App\SpotPhotoVideo;
use Pnlinh\GoogleDistance\Facades\GoogleDistance;

class SearchController extends Controller
{
    public function search(Request $request)
    {
    	$searchText = $request->input('searchText');
        $selected_user_type = $request->input('selected_user_type');
        $selected_duration = $request->input('selected_duration');
        $searchAddress  = $request->input('searchAddress');
        if($selected_duration=='' || $selected_duration=='undefined' || $selected_duration==null || $selected_duration=='null'){
            $selected_duration = null;
        }

        if($selected_user_type=='' || $selected_user_type=='undefined' || $selected_user_type==null || $selected_user_type=='null'){
            $selected_user_type = null;
        }
        //print_r($selected_user_type);
        $search_interest = $request->input('search_interest');
        if ($search_interest=='' || $search_interest=='undefined' || $search_interest==null || $search_interest=='null') {
            $search_interest = null;
        }
        if ($searchText=='' || $searchText=='undefined' || $searchText==null || $searchText=='null' || $searchText==' ') {
            $searchText = null;
        }else{
            $searchText = addslashes($searchText);
        }
        if ($searchAddress=='' || $searchAddress=='undefined' || $searchAddress==null || $searchAddress=='null' || $searchAddress==' ') {
            $searchAddress = null;
        }else{
            $searchAddress = addslashes($searchAddress);
        }
        
        $token = $request->header('Authorization');
        if($token)
            $user = User::where('user_api_token',$token)->first();
        else
            $user = null;
        //$user = User::where('user_api_token',$token)->first();
        $users_id_array = [];
        $id = null;
        $today = Carbon::now()->format('Y-m-d');

        $current_month = date('m');
        $current_year = date('Y');
        $current_month_carbon = Carbon::now();
        $next_month_date = $current_month_carbon->addMonthsNoOverflow(1);
        $next_month = $next_month_date->format('m');
        if($user) {
        	$user_id = $user->id;
        	$id = $user->id;
            $friend_relation = FriendRelation::select('from_user_id','to_user_id')->where(function ($q) use ($user_id,$user) {
                                    $q->where('to_user_id', $user_id)
                                    ->where('relation_status',1)->get();
                                })->orWhere(function ($query) use ($user_id,$user) {
                                    $query->where('from_user_id', $user_id)
                                          ->where('relation_status',1)->get();
                                })->get();
            
            
            foreach ($friend_relation as $value) {
                array_push($users_id_array, $value->to_user_id);
                array_push($users_id_array, $value->from_user_id);
            }
            
            $users_id_array = array_unique($users_id_array);
            if (($key = array_search($user_id, $users_id_array)) !== false) {
                unset($users_id_array[$key]);
            }
        }
            $counts = [];
            $counts['spot_count'] = 0;
            $counts['people_list'] = 0;
            $counts['group_list'] = 0;
            $counts['event_list'] = 0;
            $all_interests = interest::all();
            // foreach ($all_interests as $i) {
            //     $interest.'_'.$i->title;
            // }
            $people_list = User::select('id','user_profile','first_name','user_interests','user_slug','unique_id','last_name',DB::raw("CONCAT(first_name,' ',last_name) AS user_name"))->where(function ($q) use ($user,$searchText) {
                                    $q->where('last_name', 'like', $searchText . '%')
                                    ->orWhereRaw("concat(first_name, ' ', last_name) like '".$searchText."%' ")
                                    ->orWhereRaw("concat(last_name, ' ', first_name) like '".$searchText."%' ")
                                    ->orWhere('first_name', 'like', $searchText . '%')->get();
                                })
                                ->when(!empty($search_interest) , function ($query) use($search_interest){
                                            return $query->whereRaw('FIND_IN_SET(?,user_interests)', [$search_interest]);
                                })->where('user_type','normal')->get();
                                //->whereRaw("find_in_set('".$search_interest."',user_interests)")

            $date1 = null;
            $date2 = null;
            $date_ranges = [];
            if ($selected_duration=='date_range') {
                //$date_ranges = explode(',', $request->input('date_range'));
                $date1 = Carbon::parse($request->input('start_date'))->format('Y-m-d');
                $date2 = Carbon::parse($request->input('end_date'))->format('Y-m-d');
            }
            //print_r($date1);
            //$date_my = DateTime::createFromFormat('d/m/Y H:i', $date_ranges[0]);
            
            //print_r($date_my);
            $group_list = Group::select('id','group_name as user_name','group_slug as user_slug','group_unique_id as unique_id','group_profile as user_profile')
                        ->where('group_name', 'like', '%' . $searchText . '%')->get();

            $event_list = Event::select('user_profile','user_type','user_type','first_name','last_name','unique_id','user_slug','groups.*','events.*','business_details.business_name')
                    ->leftjoin('groups','groups.id','=','events.host_group')
                    ->join('users','users.id','=','events.host_id')
                    ->leftjoin('business_details','business_details.user_id','=','users.id')
                    ->where('start_date_time','>=',$today)
                    ->when($selected_duration=='today' , function ($query) use($today){
                        return $query->where('start_date_time',$today);
                    })->when($selected_duration=='this_month' , function ($query) use($current_month,$today,$current_year){
                        return $query->whereMonth('start_date_time','=', $current_month)->whereYear('start_date_time','=', $current_year)->where('start_date_time','>=',$today);
                    })->when($selected_duration=='next_month' , function ($query) use($current_month,$today,$current_year,$next_month){
                        return $query->whereMonth('start_date_time','=', $next_month)->whereYear('start_date_time','=', $current_year)->where('start_date_time','>=',$today);
                    })->when($selected_duration=='date_range' , function ($query) use($today,$current_year,$next_month,$date2,$date1){
                        return $query->where('start_date_time','>=',$date1)->where('start_date_time','<=',$date2);
                    })->where('events.event_title', 'like', '%' . $searchText . '%')
                    ->get();

            $count_spots_array = [];
            $count_users_array = [];
            $count_groups_array = [];
            $count_events_array = [];
            foreach ($all_interests as $i) {
                $count_users = User::select('first_name','last_name',DB::raw("CONCAT(first_name,' ',last_name) AS user_name"))
                    ->when(!empty($searchText) , function ($query) use($searchText,$user){
                            return $query->where(function ($q) use ($user,$searchText) {
                                $q->where('last_name', 'like',$searchText . '%')
                                ->orWhereRaw("concat(first_name, ' ', last_name) like '".$searchText."%' ")
                                ->orWhereRaw("concat(last_name, ' ', first_name) like '".$searchText."%' ")
                                ->orWhere('first_name', 'like', $searchText . '%')->get();
                            });
                    })->where('user_type','normal')
                    ->when($search_interest==$i->id , function ($query) use($search_interest,$i){
                        return $query->whereRaw('FIND_IN_SET(?,user_interests)', [$search_interest]);
                    })->when($search_interest!=$i->id , function ($query) use($search_interest,$i){
                        return $query->whereRaw('FIND_IN_SET(?,user_interests)', [$i->id]);
                    })->get();

                $count_group =  Group::select('id','group_name as user_name','group_slug as user_slug','group_unique_id as unique_id','group_profile as user_profile')
                        ->where('group_name', 'like', '%' . $searchText . '%')->get();
                
                $count_event = Event::select('user_profile','user_type','user_type','first_name','last_name','unique_id','user_slug','groups.group_slug','groups.group_unique_id','groups.group_profile','groups.group_name','events.*','business_details.business_name')
                    ->leftjoin('groups','groups.id','=','events.host_group')
                    ->join('users','users.id','=','events.host_id')
                    ->leftjoin('business_details','business_details.user_id','=','users.id')
                    ->where('start_date_time','>',$today)
                    ->when($selected_duration=='today' , function ($query) use($today){
                        return $query->where('start_date_time',$today);
                    })->when($selected_duration=='this_month' , function ($query) use($current_month,$today,$current_year){
                        return $query->whereMonth('start_date_time','=', $current_month)->whereYear('start_date_time','=', $current_year)->where('start_date_time','>=',$today);
                    })->when($selected_duration=='next_month' , function ($query) use($current_month,$today,$current_year,$next_month){
                        return $query->whereMonth('start_date_time','=', $next_month)->whereYear('start_date_time','=', $current_year)->where('start_date_time','>=',$today);
                    })->when($selected_duration=='date_range' , function ($query) use($today,$current_year,$next_month,$date2,$date1){
                        return $query->where('start_date_time','>=',$date1)->where('end_date_time','<=',$date2);
                    })->where('events.event_title', 'like', '%' . $searchText . '%')
                    ->get();

                $count_spots = DB::table('business_details')->select('user_id','users.user_profile','business_details.business_name','business_details.short_description','business_details.business_type','users.unique_id','users.user_slug','business_details.latitude','business_details.longitude','users.user_interests')
                    ->join('users','users.id','=','business_details.user_id')
                    ->where('business_details.business_name', 'like', '%' . $searchText . '%')
                    ->when($search_interest==$i->id, function ($query) use($search_interest,$i){
                        return $query->whereRaw('FIND_IN_SET(?,users.user_interests)', [$search_interest]);
                    })
                    ->when($search_interest!=$i->id, function ($query) use($search_interest,$i){
                        return $query->whereRaw('FIND_IN_SET(?,users.user_interests)', [$i->id]);
                    })->orderBy('business_type','desc')
                    ->get();
                
                $count_spots_array = array_merge($count_spots_array,array_values($count_spots->pluck('user_id')->toArray()));
                $count_users_array = array_merge($count_users_array,array_values($count_users->pluck('id')->toArray()));                
                $count_groups_array = array_merge($count_groups_array,array_values($count_group->pluck('id')->toArray()));
                $count_events_array = array_merge($count_events_array,array_values($count_event->pluck('id')->toArray()));
                //print_r($count_spots->count());
                //$i->count =  $count_spots->count() + $count_users->count();
                if ($selected_user_type=='people') {
                    $i->count = $count_users->count();
                }else if($selected_user_type=='spot') {
                    $i->count = $count_spots->count();
                }else if($selected_user_type=='group') {
                    $i->count = 0;                    
                }else if($selected_user_type=='event') {
                    $i->count = 0;                    
                }else{
                    $i->count = $count_spots->count() + $count_users->count();
                }

            }

            $count_spots_array = array_unique($count_spots_array);
            //print_r($count_spots_array);
            $count_users_array = array_unique($count_users_array);
            $count_groups_array = array_unique($count_groups_array);
            $count_events_array = array_unique($count_events_array);
            $all_spots = DB::table('business_details')->select('user_id','users.user_profile','business_details.business_name','business_details.short_description','business_details.business_type','users.unique_id','users.user_slug','business_details.latitude','business_details.longitude','users.user_interests','business_details.full_address')
                ->join('users','users.id','=','business_details.user_id')
                ->where('business_details.business_name', 'like', '%' . $searchText . '%')
                ->where('business_details.full_address', 'like', '%' . $searchAddress . '%')
                ->when(!empty($search_interest) , function ($query) use($search_interest){
                    return $query->whereRaw('FIND_IN_SET(?,users.user_interests)', [$search_interest]);
                })->orderBy('business_type','desc')
                ->get();
            foreach ($all_spots as $key => $value) {
                $liked_users = DB::table('spot_details')->select('user_id')
                     ->where('spot_id',$value->user_id)
                     ->when(!empty($id) , function ($query) use($id){
                        return $query->where('user_id','!=',$id);
                     })->where('is_like',1)
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
                    $all_spots[$key]->liked_users = $users_details;
                    $rating = DB::table('spot_details')->select(DB::raw('sum(rating) as rating_sum'),DB::raw('count(id) as count_rating'))
                     ->where('spot_id',$value->user_id)
                     ->get();
                    $avg_rating = null;
                    if ($rating[0]->rating_sum==0 || $rating[0]->count_rating==0) {
                        $avg_rating = 0;
                    }else{
                        $avg_rating =  round($rating[0]->rating_sum/$rating[0]->count_rating, 1);           
                    }
                    $all_spots[$key]->avg_rating = $avg_rating;
                    
            }
            //print_r($search_interest);
            if($search_interest==null){
                //print('if');
                $counts['spot_count'] = $all_spots->count();
                $counts['people_list'] = $people_list->count();
                $counts['group_list'] = $group_list->count();
                $counts['event_list'] = $event_list->count();

            }else{
                ///print('else');
                $counts['spot_count'] = $all_spots->count();
                $counts['people_list'] = $people_list->count();
                $counts['group_list'] = $group_list->count();
                $counts['event_list'] = $event_list->count();
                // $counts['spot_count'] = count($count_spots_array);
                // $counts['people_list'] = count($count_users_array);
                // $counts['group_list'] = count($count_groups_array);
                // $counts['event_list'] = count($count_events_array);
            }
            // $counts['spot_count'] = count($count_spots_array);
            // $counts['people_list'] = count($count_users_array);
            // $counts['friend_count'] = count($count_friend_array);
            if ($selected_user_type=='people') {
                $all_spots = [];                
                $group_list = [];
                $event_list = [];
            }else if($selected_user_type=='spot') {                
                $people_list = [];
                $group_list = [];
                $event_list = [];
            }else if($selected_user_type=='group') {
                $people_list = [];
                $all_spots = [];                
                $event_list = [];
            }else if($selected_user_type=='event') {
                $people_list = [];
                $all_spots = [];                
                $group_list = [];
            }else if($selected_user_type=='people_group'){                
                $all_spots = []; 
                $event_list = [];               
            }else if($selected_user_type=='spot_event'){
                $people_list = [];                
                $group_list = [];
            }

            
            return response()->json([
                'counts'=>$counts,
                'people_list'=> $people_list,
                'all_interests' => $all_interests,
                'spots'=> $all_spots,
                'event_list'=> $event_list,
                'group_list'=> $group_list,
                'status_code' => 200,
                'status' => true,
            ]);
        
    }

    public function getSuggestions(Request $request)
    {
        $searchText = $request->input('searchText');
        $selected_user_type = $request->input('selected_user_type');
        
        if($selected_user_type=='' || $selected_user_type=='undefined' || $selected_user_type==null || $selected_user_type=='null'){
            $selected_user_type = null;
        }

        if ($searchText=='' || $searchText=='undefined' || $searchText==null || $searchText=='null' || $searchText==' ') {
            $searchText = null;
        }else{
            $searchText = addslashes($searchText);
        }
        $today = Carbon::now()->format('Y-m-d');
        $people_list = User::select('first_name','last_name',DB::raw("CONCAT(first_name,' ',last_name) AS user_name"))->where(function ($q) use ($searchText) {
                                $q->where('last_name', 'like', $searchText . '%')
                                ->orWhereRaw("concat(first_name, ' ', last_name) like '".$searchText."%' ")
                                ->orWhereRaw("concat(last_name, ' ', first_name) like '".$searchText."%' ")
                                ->orWhere('first_name', 'like', $searchText . '%')->get();
                            })->where('user_type','normal')->distinct('user_name')->get()->toArray();
                            //->whereRaw("find_in_set('".$search_interest."',user_interests)")

        $all_spots = DB::table('business_details')->select('business_details.business_name as user_name')
            ->join('users','users.id','=','business_details.user_id')
            ->where('business_details.business_name', 'like', '%' . $searchText . '%')
            ->orderBy('business_type','desc')
            ->distinct('business_details.business_name')
            ->get()->toArray();
       
        $event_list = Event::select('events.event_title as user_name')
                    ->leftjoin('groups','groups.id','=','events.host_group')
                    ->join('users','users.id','=','events.host_id')
                    ->leftjoin('business_details','business_details.user_id','=','users.id')
                    ->where('events.event_title', 'like', '%' . $searchText . '%')
                    ->where('start_date_time','>',$today)
                    ->get()->toArray();

        $group_list = Group::select('group_name as user_name')
                        ->where('group_name', 'like', '%' . $searchText . '%')
                        ->distinct('group_name')
                        ->get()->toArray();
        
        if ($selected_user_type=='people') {
            $all_spots = [];
            $group_list = [];
            $event_list = [];
        }else if($selected_user_type=='spot') {
            $people_list = [];
            $group_list = [];
            $event_list = [];
        }else if($selected_user_type=='group') {
            $all_spots = [];
            $people_list = [];
            $event_list = [];
        }else if($selected_user_type=='event') {
            $all_spots = [];
            $group_list = [];
            $people_list = [];
        }else if($selected_user_type=='people_group'){                
            $all_spots = [];
            $event_list = [];
        }else if($selected_user_type=='spot_event'){
            $people_list = [];        
            $group_list = [];
        }

        $all_suggestions = array_merge($people_list,$all_spots);
        $all_suggestions = array_merge($all_suggestions,$group_list);
        $all_suggestions = array_merge($all_suggestions,$event_list);
        return response()->json([
            'all_suggestions' => $all_suggestions,
            'status_code' => 200,
            'status' => true,
        ]);
        

    }

    public function spotSuggestions(Request $request)
    {
        
        $lat = $request->input('lat');
        if($lat=='' || $lat=='undefined' || $lat==null || $lat=='null'){
            $lat = null;
        }else{
            $lat = (float) $request->input('lat');
        }
        $long = $request->input('long');
        if($long=='' || $long=='undefined' || $long==null || $long=='null'){
            $long = null;
        }else{
            $long = (float) $request->input('long');
        }

        //$lat = 21.2434944;
        //$long = 72.7810048;
        //print_r($request->all());
        
        if ($lat!=null && $long!=null) {
            $all_spots = DB::table('business_details')->select('user_id','users.user_profile','business_details.business_name','business_details.short_description','business_details.business_type','business_details.full_address','users.unique_id','users.user_slug','business_details.latitude','business_details.longitude','users.user_interests',DB::raw(' ( 6367 * acos( cos( radians('.$lat.') ) * cos( radians( business_details.latitude ) ) * cos( radians( business_details.longitude ) - radians('.$long.') ) + sin( radians('.$lat.') ) * sin( radians( business_details.latitude ) ) ) ) AS new_distance'))
                ->join('users','users.id','=','business_details.user_id')
                ->orderBy('business_type','desc')
                ->get();

                $all_spots = $all_spots->sortBy('new_distance')->filter(function ($item) {
                    return $item->new_distance < 100;
                })->values()->all();            
                
                foreach ($all_spots as $value) {
                    if (($value->latitude!=null && $value->longitude!=null)||$value->new_distance!=null) {
                        $distance = GoogleDistance::calculate($lat.','.$long, $value->latitude.','.$value->longitude);
                        $value->distance = $distance/1000;
                    }else{
                        $distance = GoogleDistance::calculate($lat.','.$long, $value->full_address);
                        $value->distance = $distance/1000;
                    }
                    
                }
                $all_spots = collect($all_spots);
                $all_spots = $all_spots->sortBy('distance')->filter(function ($item) {
                    return $item->distance < 10 && $item->distance !=null && $item->distance > 0;
                })->values()->all();
        }else{
            $all_spots = [];
        }
        return response()->json([
            'all_spots' => $all_spots,   
            'lat' => $lat,
            'long' => $long,
            'status_code' => 200,
            'status' => true,
        ]);
    }
}
