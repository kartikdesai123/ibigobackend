<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\MessageSent;
use Carbon\Carbon;
use App\User;
use App\Message;
use App\FriendRelation;
use DB; 
use Illuminate\Support\Facades\Config;
use \Pusher\Pusher;
use Illuminate\Support\Arr;

class ChatsController extends Controller
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

    public function demo()
    {
    	$mapData = array(
            ["year" => "2008","income"=>27,"expenses"=>27],
            ["year" => "2009","income"=>2,"expenses"=>29],
            ["year" => "2011","income"=>1,"expenses"=>30],
            ["year" => "2012","income"=>63,"expenses"=>93],
            ["year" => "2013","income"=>67,"expenses"=>160],
            ["year" => "2014","income"=>8,"expenses"=>168],
            ["year" => "2015","income"=>183,"expenses"=>351],
            ["year" => "2016","income"=>214,"expenses"=>565],
            ["year" => "2017","income"=>63,"expenses"=>628],
            ["year" => "2018","income"=>50,"expenses"=>678],
            ["year" => "2019","income"=>134,"expenses"=>812]
        );
        print(21%6);echo "<br>";
        print(15%6);echo "<br>";
        print(11%6);echo "<br>";
        print(6%6);echo "<br>";
        print(3%6);echo "<br>";
        die;
    	foreach ($mapData as $value) {    		
    		print_r($value['expenses']);
    		echo "<br>";
    	}
    }

	public function index()
    {
	  	return view('chat');
	}

    public function recentChats(Request $request)
    {
        $token = $request->header('Authorization');
        $user = User::select('id','user_profile','user_type','user_about','mobile','user_cover','country_short_code','unique_id','user_slug')->where('user_api_token',$token)->first();
        // $user = User::where('id',25)->first();
        if($user){
            $user_id = $user->id;
            //echo "<pre>";
            $from_messages = Message::select('id','from_user_id','to_user_id','message','message_date_time')
                    //->where('to_user_id', $user_id)
                    ->where('from_user_id', $user_id)
                    //->groupBy('to_user_id','from_user_id')
                    ->orderBy('message_date_time','DESC')
                    ->get();
              //$from = $from_messages->toArray();      
            $from = $from_messages->groupBy('to_user_id')->toArray();

            $to_messages = Message::select('id','from_user_id','to_user_id','message','message_date_time')
                    ->where('to_user_id', $user_id)
                    // ->orWhere('from_user_id', $user_id)
                    //->groupBy('to_user_id','from_user_id')
                    ->orderBy('message_date_time','DESC')
                    ->get()->toArray();

                    
                    // $from_messages = $from_messages->groupBy('to_user_id')->map(function($groupItems){
                    //     return $groupItems->sort('id');
                    // })->toArray();
                    
                    // $to_messages = $to_messages->groupBy(function($item){ 
                    //     return $item->to_user_id;
                    // });


                    // foreach ($from as $key => $message) {
                        
                    //     if ($key!=$user_id) {
                    //         $array = [];
                    //         foreach ($to_messages as $to_message){

                    //             if ($to_message['from_user_id']==$key){
                    //                 array_push($array, $to_message);
                    //             }
                    //         }
                    //         $from[$key] = array_merge($message,$array);   
                    //         //print_r($from_messages[$key])                         ;
                    //     }
                    // }


                    //$orderedItems = collect($array)->sortBy('weight');
                    //$from_messages = collect($from_messages)->sortBy('id');                    
                    foreach ($from as $key => $value) {                        
                        $from[$key] = array_values($value)[0];
                        $to_user = User::where('id',$value[0]['to_user_id'])->first();
                        $from[$key]['user_slug'] = $to_user->user_slug;
                        $from[$key]['unique_id'] = $to_user->unique_id;
                        $from[$key]['user_profile'] = $to_user->user_profile;
                        $from[$key]['first_name'] = $to_user->first_name;
                        $from[$key]['last_name'] = $to_user->last_name;
                        $from[$key]['user_name'] = $to_user->first_name.' '.$to_user->last_name;
                    }                    
            //$messages = $messages->groupBy('to_user_id','from_user_id')->get();
            //echo "<pre>";
            //die;
                    
            //print_r($from_messages);
            //die;
            return response()->json([
                'recent_chats' => array_values($from),                
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

    public function getRecentFriends(Request $request)
    {
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        //$user = User::where('id',25)->first();
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
            
            $friend_list = User::select('id','user_profile','first_name','user_slug','unique_id','last_name',DB::raw("CONCAT(first_name,' ',last_name) AS user_name"))->whereIn('id', $users_id_array)->get()->toArray();
            foreach ($friend_list as $k => $v) {
                
                $from_messages = Message::select('id','from_user_id','to_user_id','message','message_date_time')
                        ->where('to_user_id', $v['id'])
                        ->where('from_user_id', $user_id)
                        //->groupBy('to_user_id','from_user_id')
                        ->orderBy('message_date_time','DESC')
                        ->get();
                  //$from = $from_messages->toArray();      
                $from = $from_messages->groupBy('to_user_id')->toArray();
                foreach ($from as $key => $value) { 
                    $friend_list[$k]['message'] = $value[0]['message'];    
                    $from[$key] = array_values($value)[0];
                    $to_user = User::where('id',$value[0]['to_user_id'])->first();
                    $from[$key]['user_slug'] = $to_user->user_slug;
                    $from[$key]['unique_id'] = $to_user->unique_id;
                    
                    $from[$key]['user_profile'] = $to_user->user_profile;
                    $from[$key]['first_name'] = $to_user->first_name;
                    $from[$key]['last_name'] = $to_user->last_name;
                    $from[$key]['user_name'] = $to_user->first_name.' '.$to_user->last_name;
                }
            }
            
            return response()->json([
                'friend_list'=> $friend_list,
                'status_code' => 200,
                'status' => true,
            ]);
        }
    }

	public function like(Request $request)
	{
		$likes = $request->input('likes');
		//broadcast(new MessageSent($likes))->toOthers();
		//$this->pusher->trigger('chat','like',response()->json(['likes' => $likes]));
		return response()->json([
            'status' => 200,
        ]);
	}
	/**
	 * Fetch all messages
	 *
	 * @return Message
	 */
	public function fetchMessages(Request $request,$user_id)
	{
		$token = $request->header('Authorization');
        $user = User::select('id','user_profile','user_type','user_about','mobile','user_cover','country_short_code','unique_id','user_slug')->where('user_api_token',$token)->first();

        if($user)
        {
	  		//$messages = Message::with('to_user')->where('from_user_id',$user->id)->where('to_user_id',$to_user_id)->get();
	  		$user_messages = Message::where(function ($q) use ($user_id,$user) {
                                    $q->where('to_user_id', $user_id)
                                    	->orWhere('from_user_id',$user->id)->get();
                                })->orWhere(function ($query) use ($user_id,$user) {
                                    $query->where('to_user_id', $user->id)
                                          ->orWhere('from_user_id',$user_id)->get();
                                })->get();
	  		return response()->json([
                'messages' => $user_messages,
                'status_code' => 200,
                'status' => true
            ]);
	  	}else{
	  		return response()->json([
                'error' => 'Unauthenticate User',
                'status_code' => 401,
                'status' => false
            ]);
	  	}
	}

	/**
	 * Persist message to database
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function sendMessage(Request $request,$to_user_id)
	{
		$token = $request->header('Authorization');
        $user = User::select('id','user_profile','user_type','user_about','mobile','user_cover','country_short_code','unique_id','user_slug')->where('user_api_token',$token)->first();

        if($user)
        {
			$message = Message::create([
				'from_user_id' => $user->id,
				'to_user_id' => $to_user_id,
				'message' => $request->input('message'),
				'message_date_time' => now()
			]);

            
            $new_message = new \stdClass();
            
            $new_message->id = $message->id;
            $new_message->message = $message->message;
            $new_message->message_date_time = $message->message_date_time;
            $new_message->from_user_id = $message->from_user_id;
            $new_message->to_user_id = $message->to_user_id;

            $to_user = User::where('id',$new_message->to_user_id)->first();
            $new_message->to_user_name = $to_user->first_name.' '.$to_user->last_name;
            $new_message->to_user_slug = $to_user->user_slug;
            $new_message->to_user_unique_id = $to_user->unique_id;

            $from_user = User::where('id',$new_message->from_user_id)->first();
            $new_message->from_user_name = $from_user->first_name.' '.$from_user->last_name;
            $new_message->from_user_slug = $from_user->user_slug;
            $new_message->from_user_unique_id = $from_user->unique_id;    
            
            
            
			//print_r($user);
            //$message = 
			broadcast(new MessageSent($user,$new_message))->toOthers();
			
			return response()->json([
                'message' => 'Message Sent!',
                'status_code' => 200,
                'status' => true
            	]);
		}else{
			return response()->json([
                'error' => 'Unauthenticate User',
                'status_code' => 401,
                'status' => false
            ]);
		}
		//return ['status' => 'Message Sent!'];
	}
}
