<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\interest;
use Carbon\Carbon;
use App\Admin;
use Validator;
use App\User;
use App\BusinessDetails;
use App\Subscription;
use App\Payment;
use App\Verifyuser;
use Hash;
use Illuminate\Support\Str;
use DB;

class BusinessUserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function list()
    {
        
        $users = User::select('users.id','users.user_profile','users.user_about','users.mobile','users.user_cover','business_details.full_address','business_details.business_name','business_details.phone_number','business_details.business_type','users.unique_id','users.user_slug')
                ->join('business_details','business_details.user_id','=','users.id')
                ->get();
        foreach ($users as $user) {
            $rating = DB::table('spot_details')->select(DB::raw('sum(rating) as rating_sum'),DB::raw('count(id) as count_rating'))
                ->where('spot_id',$user->id)
                ->get();
            $avg_rating = 0;
            if ($rating[0]->rating_sum==0 || $rating[0]->count_rating==0) {
                $avg_rating = 0;
            }else{
                $avg_rating =  round($rating[0]->rating_sum/$rating[0]->count_rating, 1); 
            }
            $user->avg_rating = $avg_rating;
            
        }
        return response()->json([
                'users_details' => $users,
                'status_code' => 200,
                'status' => true
            ], 200);
       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request){        
        $rules = [            
            'email' => 'unique:users,email',            
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
        } else {
            if($request->hasFile('file')){
                $file = $request->file('file');
                $filename = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $picture = date('dmyHis').'-'.$filename;
                $file->move(public_path('user_profiles'), $picture);
                //$bday = Carbon::parse($request['birth_date']);
                    
            } 
            $picture = null;
            $coverpicture = null;
            if ($request->hasFile('cover')) {
                $cover = $request->file('cover');
                $covername = $cover->getClientOriginalName();
                $coverextension = $cover->getClientOriginalExtension();
                $coverpicture = date('dmyHis').'-'.$covername;
                $cover->move(public_path('user_cover'), $coverpicture);
            }
            $number = null;
            $dialCode = null;
            $country_short_code = null;        
            if ($request->input('mobile')!='null') {
                $mobile_info = json_decode($request->input('mobile'));
                $dialCode = str_replace("+", "", $mobile_info->dialCode);
                $number = str_replace(" ", "", $mobile_info->number);
                $country_short_code = $mobile_info->countryCode;
                $number = str_replace("-", "", $number);
            }
            $now = Carbon::now();
            $unique_code = $now->format('mdHiYsu');
            
            
            $insert_array=array(
                'unique_id' => $unique_code.mt_rand(1, 999),
                'mobile'=>$number,
                'country_code'=>$dialCode,
                'country_short_code'=>$country_short_code,
                'user_slug'=>Str::slug($request['business_name'],'-'),
                'user_profile'=>$picture,
                'user_cover'=>$coverpicture,
                'user_about'=>$request['user_about'],
                'user_interests'=>$request['user_interest'],
                'email'=>$request['email'],
                'user_type' => 'business',
                'password'=>bcrypt($request['password']),
                'created_at'=>now(),
                'updated_at'=>now(),
            );            
            $user_details=User::create($insert_array);
            $business_array=array(
                'user_id'=>$user_details->id,
                'full_address'=>$request['full_address'],
                'latitude'=>$request['latitude'],
                'longitude'=>$request['longitude'],
                'phone_number'=>$request['phone_number'],
                'business_type'=>$request['business_type'],
                'business_name'=>$request['business_name'],
                'short_description' =>$request['short_description'],
                'place_id'=>$request['place_id'],
                'rating' => $request['rating'],
                'business_status'=>$request['business_status'],
                'user_ratings_total' => $request['user_ratings_total'],
                'created_at'=>now(),
                'updated_at'=>now(),
            );
            //print_r($request->all());
            if($request['business_type']=='premium'){
                $business_array['parking_details'] = $request['parking_details'];
            }

            $business_details=BusinessDetails::create($business_array);
            if(!empty($user_details))
            {
                if($request['business_type']=='premium')
                {
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
                $message ='You are registrered Successfully.';
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $token = $request->header('Authorization');
        $user = User::select('id','user_profile','user_about','mobile','user_cover')->where('user_api_token',$token)->first();
        if($user)
        {
            $business_user = BusinessDetails::select('business_name','full_address','phone_number')->where('user_id',$user->id)->first();
            return response()->json([
                    'user_details' => $user,
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

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = User::select('users.id','users.user_profile','users.user_about','users.mobile','users.user_cover','business_details.full_address','business_details.business_name','users.email','business_details.business_type','business_details.phone_number','users.country_short_code','business_details.short_description','business_details.parking_details','users.user_interests')
                ->join('business_details','business_details.user_id','=','users.id')
                ->where('users.id',$id)
                ->first();
        return response()->json([
            'user_details' => $user,
            'status_code' => 200,
            'status' => true
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,$id){
        $token = $request->header('Authorization');
        $user = Admin::where('api_token',$token)->first();
        if($user)
        {
            $check_details=User::find($id);

            if(!empty($check_details))
            {
                $rules = [
                 'email' => 'required|email',
                 'mobile' => 'required',
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

                    $mobile_info = json_decode($request->input('mobile'));
                    $dialCode = str_replace("+", "", $mobile_info->dialCode);
                    $country_short_code = $mobile_info->countryCode;
                    $number = str_replace(" ", "", $mobile_info->number);
                    $number = str_replace("-", "", $number);
                    
                    $picture='';
                    if ($request->hasFile('file')) {
                        $file = $request->file('file');
                        $filename = $file->getClientOriginalName();
                        $extension = $file->getClientOriginalExtension();
                        $picture = date('dmyHis').'-'.$filename;
                        $file->move(public_path('user_profiles'), $picture);
                    }
                    $coverpicture='';
                    if ($request->hasFile('cover')) {
                        $cover = $request->file('cover');
                        $covername = $cover->getClientOriginalName();
                        $coverextension = $cover->getClientOriginalExtension();
                        $coverpicture = date('dmyHis').'-'.$covername;
                        $cover->move(public_path('user_cover'), $coverpicture);
                    }
                    
                    $update_array=array(
                        'country_code'=>$dialCode,
                        'country_short_code'=>$country_short_code,
                        'mobile'=>$number,
                        'user_about'=>$request['user_about'],
                        'user_interests'=>$request['user_interest'],
                        'email'=>$request['email'],
                        'user_type' => 'business',
                        'created_at'=>now(),
                        'updated_at'=>now(),
                    );
                    if(isset($request['password']) && $request['password']!=''){
                        $update_array['password'] = bcrypt($request['password']);
                    }
                    if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
                    {
                        $update_array['user_profile']=$picture;
                    }
                    if(isset($request['cover']) && $request['cover']!='' && $request->hasFile('cover'))
                    {
                        $update_array['user_cover']=$coverpicture;
                    }
                    

                    User::where('id',$id)->update($update_array);
                    $business_array=array(
                        'user_id'=>$id,
                        'full_address'=>$request['full_address'],
                        'short_description'=>$request['short_description'],
                        'phone_number'=>$request['phone_number'],
                        'business_type'=>$request['business_type'],
                        'business_name'=>$request['business_name'],
                        'created_at'=>now(),
                        'updated_at'=>now(),
                        
                    );
                    if(isset($request['parking_details']) &&  $request->has('parking_details'))
                    {
                        $business_array['parking_details']=$request['parking_details'];
                    }

                    $business_details=BusinessDetails::where('user_id',$id)->update($business_array);
                    $insert_details=User::find($id);

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
   

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateFiles(Request $request,$id)
    {
        $user = User::where('id',$id)->first();
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
}
