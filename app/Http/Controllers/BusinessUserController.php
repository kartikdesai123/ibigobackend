<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\interest;
use DB;
use Carbon\Carbon;
use App\Admin;
use Validator;
use App\User;
use App\BusinessDetails;
use App\Subscription;
use App\Payment;
use App\Verifyuser;
use App\CheckInDetail;
use Hash;
use App\SpotDetail;
use App\SpotPhotoVideo;
use Illuminate\Support\Str;

class BusinessUserController extends Controller
{
    public function create(Request $request){
		$rules = [
         'email' => 'required|email|unique:users,email',
         'mobile' => 'required'
     	];
     	$messages=[];

     	$validator = Validator::make($request->all(), $rules, $messages);
     	if($validator->fails())
        {
            return response()->json([
            	'messages' => $validator->errors()->all(),
            	'status_code' => 401,
            	'status' => false
            ]);
        }else{
        	//$bday = Carbon::parse($request['birth_date']);
            $now = Carbon::now();
            $unique_code = $now->format('mdHiYsu');
            $mobile_info = json_decode($request->input('mobile'));
            $dialCode = str_replace("+", "", $mobile_info->dialCode);
            $number = str_replace(" ", "", $mobile_info->number);
            $country_short_code = $mobile_info->countryCode;
            $number = str_replace("-", "", $number);
        	$insert_array=array(
                'user_interests'=>$request['user_interest'],
                'unique_id' => $unique_code.mt_rand(1, 999),
        		'mobile'=>$number,
                'country_code'=>$dialCode,
                'country_short_code'=>$country_short_code,
                'user_slug'=>Str::slug($request['business_name'],'-'),
        		'email'=>$request['email'],
        		'password'=>bcrypt($request['password']),
        		'is_receive_commercial_email'=>($request['receive_email']==true)?1:0,
        		'user_type' => 'business',
        		'created_at'=>now(),
        		'updated_at'=>now(),
        	);
            if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
            {
                $file = $request->file('file');
                $filename = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $picture = date('dmyHis').'-'.$filename;
                $file->move(public_path('user_profiles'), $picture);
                $insert_array['user_profile']=$picture;
            }
        	$user_details=User::create($insert_array);
            $business_array=array(
                'user_id'=>$user_details->id,
                'full_address'=>$request['full_address'],
                'latitude'=>$request['latitude'],
                'longitude'=>$request['longitude'],
                'phone_number'=>$request['phone_number'],
                'business_type'=>$request['business_type'],
                'business_name'=>$request['business_name'],
                'business_status'=>$request['business_status'],
                'photos'=>'blank',
                'place_id'=>$request['place_id'],
                'rating' => $request['rating'],
                'user_ratings_total' => $request['user_ratings_total'],
                'created_at'=>now(),
                'updated_at'=>now(),
            );
            
            $business_details=BusinessDetails::create($business_array);
        	if(!empty($user_details))
        	{
                if ($request['business_type']=='premium') {
                    $mollie = new \Mollie\Api\MollieApiClient();
                    $mollie->setApiKey("test_s4w9grSfW8BvBTpGjCkvwdAmAxABvk");

                    $customer = $mollie->customers->create(array(
                        "name" => $request['business_name'],
                        "email" => $request['email']
                    ));

                    $mandate = $mollie->customers->get($customer->id)->createMandate([
                       "method" => \Mollie\Api\Types\MandateMethod::DIRECTDEBIT,
                       "consumerName" => "Chirag Patel",
                       "consumerAccount" => "NL55INGB0000000000",
                       "consumerBic" => "INGBNL2A",
                       "signatureDate" => "2018-05-07",
                       "mandateReference" => "YOUR-COMPANY-MD13804",
                    ]);
                    $subscription = $customer->createSubscription([
                       "amount" => [
                             "currency" => "EUR",
                             "value" => "8.95",
                       ],
                       "interval" => "1 month",
                       "description" => "Daily Payment",
                       "webhookUrl" => "https://ibigo.shadowis.nl/server-api/api/webhook/redirect",
                    ]);

                    $new_subscription = new Subscription();
                    $new_subscription->user_id = $user_details->id;
                    $new_subscription->subscription_id = $subscription->id;
                    $new_subscription->customer_id = $customer->id;
                    $new_subscription->mandate_id = $mandate->id;
                    $new_subscription->subscription_status = $subscription->status;
                    $new_subscription->subscription_date = Carbon::parse($subscription->createdAt);
                    $new_subscription->save();
                }
                $t = sha1(time());
                $verifyUser = VerifyUser::create([
                    'user_id' => $user_details->id,
                    'token' => $t
                ]);

                $url = 'https://ibigo.shadowis.nl/#/user/verify/'.$t;
                $to = $user_details->email;
                $subject = "Verify Email Address";
                $txt = '<html> 
                <head> 
                    <title>Welcome to IBIGO!</title> 
                </head> 
                <body style="margin-left:200px;"> 
                    <div>
                    <h3>Hello!</h3>
                    <br>
                    <p>Please click the button below to verify your email address.</p>
                    <br>
                    <a href="'.$url.'">Veriy Email Address</a>
                    <br>
                    <p>If you did not create an account, no further action is required.</p>
                    <p>Regards,</p>
                    <p>IBIGO</p>
                    </div>
                </body> 
                </html>';
                $headers = "MIME-Version: 1.0" . "\r\n"; 
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
               
                mail($to,$subject,$txt,$headers);
                $message ='Registration done successfully. Please confirm your email.';
                $status_code = 201;
                $status = true;

        		return response()->json([
                	'messages' => $message,
                	'status_code' => $status_code,
                	'status' => $status
            	]);
        	}
        	else
        	{
        		return response()->json([
	            	'messages' => 'Something went wrong ! Please try again.',
	            	'status_code' => 401,
	            	'status' => false
            	]);
        	}
        } 
	}

    public function getUser(Request $request)
    {
        $token = $request->header('Authorization');
        $user = User::select('id','user_profile','user_type','user_interests','user_about','mobile','user_cover','country_short_code','unique_id','user_slug')->where('user_api_token',$token)->first();
        if($user)
        {
            $user_interests = explode(',', $user->user_interests);
            $interests = interest::select('title')->whereIn('id',$user_interests)->get();
            $business_user = BusinessDetails::select('business_name','full_address','short_description','phone_number','business_type','spot_photos','spot_videos','parking_details','place_id')->where('user_id',$user->id)->first();
            $videos_photos = CheckInDetail::where('spot_id',$user->id)->get();
            $liked_users = SpotDetail::where('spot_id',$user->id)->where('is_like',1)->get();
            $users_array = [];
            foreach ($liked_users as $key => $lu) {
                $user_detail = User::where('id',$lu->user_id)->first();
                $users_array[$key]['user_profile'] = $user_detail->user_profile;
                $users_array[$key]['first_name'] = $user_detail->first_name;
                $users_array[$key]['last_name'] = $user_detail->last_name;
                $users_array[$key]['unique_id'] = $user_detail->unique_id;
                $users_array[$key]['user_slug'] = $user_detail->user_slug;
            }
            $rating_users = SpotDetail::select('spot_id','id','user_id','rating')->whereNotNull('rating')->where('spot_id',$user->id)->limit(7)->get();
            foreach ($rating_users as $key => $value) {
                if ($value->rating!=null) {
                    $rated_user = User::where('id',$value->user_id)->first();
                    $value->user_profile = $rated_user->user_profile;
                    $value->first_name = $rated_user->first_name;
                    $value->unique_id = $rated_user->unique_id;
                    $value->user_slug = $rated_user->user_slug;
                }
            }
            $photos = [];
            $videos = [];
            if ($business_user->spot_photos!=null) {
                $photos = unserialize($business_user->spot_photos);
                //$photos = array_merge($business_user->spot_photos);
            }
            if ($business_user->spot_videos!=null) {
                $videos = unserialize($business_user->spot_videos);
                //$videos = array_merge($videos,$v);
            }
            $spot_photos_videos_by_user = SpotPhotoVideo::where('spot_id',$user->id)->get();
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
                
            
            $reviews_count = DB::table('spot_details')->select('review')
                ->where('spot_id',$user->id)
                ->where('user_id','!=',$user->id)
                ->where('review','!=',null)
                ->count();
            $is_connected_count = DB::table('spot_details')->select('is_connected')
                ->where('spot_id',$user->id)
                ->where('is_connected',1)
                ->count();
            $rating = DB::table('spot_details')->select(DB::raw('sum(rating) as rating_sum'),DB::raw('count(id) as count_rating'))
            ->where('spot_id',$user->id)
            ->get();
                $avg_rating = 0;
                if ($rating[0]->rating_sum==0 || $rating[0]->count_rating==0) {
                    $avg_rating = 0;
                }else{
                    $avg_rating =  round($rating[0]->rating_sum/$rating[0]->count_rating, 1);           
                }
            return response()->json([
                    'user_interests' => $interests,
                    'liked_users' => $users_array,
                    'reviews_count' => $reviews_count,
                    'is_connected_count' => $is_connected_count,
                    'spot_photos_by_user'=>$spot_photos_by_user,
                    'spot_videos_by_user'=>$spot_videos_by_user,
                    'rating_users' => $rating_users,
                    'user_details' => $user,
                    'photos' => $photos,
                    'avg_rating'=>$avg_rating,
                    'videos' => $videos,
                    'business_details' => $business_user,
                    'status_code' => 200,
                    'status' => true
                ], 200);
        }else{
            return response()->json([
                    'error' => 'Unauthenticate User',
                    'status_code' => 401,
                    'status' => false
                ], 401);
        }
    }

    public function update(Request $request)
    {
        
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        if($user)
        {
            $check_details=User::find($user->id);

            if(!empty($check_details))
            {
                $rules = [
                 'business_name' => 'required|string|max:50',
                 'mobile' => 'required',
                 'phone_number' => 'required',
                 'user_about' => 'required',
                 'full_address' => 'required'
                ];
                $messages=[];

                $validator = Validator::make($request->all(), $rules, $messages);

                if($validator->fails())
                {
                    return response()->json([
                        'messages' => $validator->errors()->all(),
                        'status_code' => 401,
                        'status' => false
                    ], 401);
                }
                else
                {   
                    $picture='';
                    $mobile_info = json_decode($request->input('mobile'));
                    $dialCode = str_replace("+", "", $mobile_info->dialCode);
                    $country_short_code = $mobile_info->countryCode;
                    $number = str_replace(" ", "", $mobile_info->number);
                    $number = str_replace("-", "", $number);
                    
                    if ($request->hasFile('file')) {
                        $file = $request->file('file');
                        $filename = $file->getClientOriginalName();
                        $extension = $file->getClientOriginalExtension();
                        $picture = date('dmyHis').'-'.$filename;
                        $file->move(public_path('user_profiles'), $picture);
                    }
                    //$bday = Carbon::parse($request['birth_date']);

                    $update_array=array(
                        'country_code'=>$dialCode,
                        'country_short_code'=>$country_short_code,
                        'mobile'=>$number,
                        'user_about'=>$request['user_about']
                    );

                    $business_array = array(
                        'business_name'=>$request['business_name'],
                        'full_address'=>$request['full_address'],
                        'phone_number'=>$request['phone_number'],
                        'short_description' =>$request['short_description'],
                    );
                    if(isset($request['parking_details']) && $request->has('parking_details')){
                        $business_array['parking_details'] = $request['parking_details'];
                    }
                    if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
                    {
                        $update_array['user_profile']=$picture;
                    }

                    User::where('id',$user->id)->update($update_array);
                    BusinessDetails::where('user_id',$user->id)->update($business_array);

                    $insert_details=User::find($user->id);

                    if(!empty($insert_details))
                    {
                        return response()->json([
                        'messages' => 'User updated successfully.',
                        'status_code' => 201,
                        'status' => true
                        ]);
                    }
                    else
                    {
                        return response()->json([
                            'messages' => 'Something went wrong ! Please try again.',
                            'status_code' => 401,
                            'status' => false
                        ]);
                    }
                }
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

    public function updateFiles(Request $request)
    {
        $token = $request->header('Authorization');
        $user = User::where('user_api_token',$token)->first();
        if($user)
        {
            //print_r($request->all());
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
                    $imageName = $file->getClientOriginalName();
                    $filename = pathinfo($imageName, PATHINFO_FILENAME);
                    //$filemimetype = $file->getMimeType();
                    $extension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));                    
                    if (in_array($extension, $imageExtensions)) {
                        $picture = date('dmyHis').'-'.mt_rand(10000,99999).'.'.$extension;
                        $file->move(public_path('business_images/'), $picture);
                        array_push($photo_array, $picture);
                    }else if(in_array($extension, $videoExtensions)){
                        $video = date('dmyHis').'-'.mt_rand(10000,99999).'.'.$extension;
                        $file->move(public_path('business_videos/'), $video);
                        array_push($video_array, $video);
                    }   
                }
            }
            $business_array['spot_photos'] = serialize($photo_array);
            $business_array['spot_videos'] = serialize($video_array);
            
            BusinessDetails::where('user_id',$user->id)->update($business_array);

            return response()->json([
                'message' => 'User updated successfully.',
                'status_code' => 201,
                'status' => true
            ]);

        }
        else
        {
             return response()->json([
                    'message' => 'Unauthenticate User',
                    'status_code' => 401,
                    'status' => false
                ], 401);

        }
    }

    public function getAllSpots(Request $request){
        $lat = 21.2004545;
        $lon = 72.8037208;
        $searchText = $request->input('searchText');
        $token = $request->header('Authorization');     
        $user = User::where('user_api_token',$token)->first();
        if ($user) {
            $id = $user->id;
            $all_spots = DB::table('business_details')->select('user_id','users.user_profile','business_details.business_name','business_details.short_description','business_details.business_type','users.unique_id','users.user_slug','business_details.latitude','business_details.longitude',DB::raw(' ( 6367 * acos( cos( radians('.$lat.') ) * cos( radians( business_details.latitude ) ) * cos( radians( business_details.longitude ) - radians('.$lon.') ) + sin( radians('.$lat.') ) * sin( radians( business_details.latitude ) ) ) ) AS distance'))
                ->join('users','users.id','=','business_details.user_id')
                ->where('business_details.business_name', 'like', '%' . $searchText . '%')
                ->orderBy('business_type','desc')
                ->get();
                foreach ($all_spots as $key => $value) {
                    $liked_users = DB::table('spot_details')->select('user_id')
                         ->where('spot_id',$value->user_id)
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
                        $all_spots[$key]->liked_users = $users_details;
                        $rating = DB::table('spot_details')->select(DB::raw('sum(rating) as rating_sum'),DB::raw('count(id) as count_rating'))
                         ->where('spot_id',$value->user_id)
                         ->get();
                        if ($rating[0]->rating_sum==0 || $rating[0]->count_rating==0) {
                            $avg_rating = 0;
                        }else{
                            $avg_rating =  round($rating[0]->rating_sum/$rating[0]->count_rating, 1);           
                        }
                        $all_spots[$key]->avg_rating = $avg_rating;
                        
                }
                // print_r($all_spots);
                return response()->json([
                    'spots' => $all_spots,
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
}
