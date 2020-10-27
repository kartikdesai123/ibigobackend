<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendMail;
use App\User;
use Hash;

class UserForgetPasswordController extends Controller
{
    public function create(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user)
        {
            $message ='We can not find a user with that e-mail address.';
            $status_code = 404;
            $status = false;
        }else{
        	$t = Str::random(60);
            User::where('email',$user->email)->update(['reset_password_token'=>$t,'updated_at'=>now()]);
            //print_r($passwordReset);
            $url = 'https://ibigo.shadowis.nl/#/user/reset/'.$t;
            $to = $user->email;
            $subject = "Reset Password Notification";
            $txt = '<html> 
                <head> 
                    <title>Welcome to IBIGO!</title> 
                </head> 
                <body style="margin-left:200px;"> 
                    <div>
                    <h3>Hello!</h3>
                    <br>
                    <p>You are receiving this email because we received a password reset request for your account.</p>
                    <br>
                    <a href="'.$url.'">Reset Password</a>
                    <br>
                    <p>This password reset link will expire in 60 minutes.</p>
                    <p>If you did not request a password reset, no further action is required.</p>
                    <p>Regards,</p>
                    <p>IBIGO</p>
                    </div>
                </body> 
                </html>';
                $headers = "MIME-Version: 1.0" . "\r\n"; 
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            //$headers .= "From: demo.logixbuiltinfo@gmail.com" . "\r\n";

            mail($to,$subject,$txt,$headers);
            $message ='We have e-mailed your password reset link!';
            $status_code = 200;
            $status = true;
        }
       
    	
        return response()->json([
            'message' => $message,
            'status_code' => $status_code,
            'status' => $status
        ]);
    }

    public function reset(Request $request,$token)
    {
        $passwordReset = User::where('reset_password_token', $token)->first();
        if (!$passwordReset){
            return response()->json([
                'message' => 'This password reset token is invalid.',
                'status_code' => 404,
                'status' => false
            ]);
        }
            
        if (Carbon::parse($passwordReset->updated_at)->addMinutes(720)->isPast()) {
            $passwordReset->reset_password_token = null;
            $passwordReset->save();
            return response()->json([
                'message' => 'This password reset token is expired.',
                'status_code' => 404,
                'status' => false
            ]);
        }
        else
        {
            $request->validate([
                'password' => 'required|string|confirmed',
            ]);
            
            $user = User::where('reset_password_token', $token)->first();
            
            $user->password = bcrypt($request->password);
            $user->reset_password_token = null;
            $user->save();
            //$passwordReset->delete();

            $to = $user->email;
            $subject = "Reset Password Notification";
            $txt = '<html> 
                <head> 
                    <title>Welcome to IBIGO!</title> 
                </head> 
                <body style="margin-left:200px;"> 
                    <div>
                    <h3>Hello!</h3>
                    <br>
                    <p>You are changed your password successful.</p>
                    <br>
                    <a href="https://ibigo.shadowis.nl/#/user/login">Login</a>
                    <br>
                    <p>If you did change password, no further action is required.</p>
                    <p>If you did not change password, protect your account.</p>
                    <p>Regards,</p>
                    <p>IBIGO</p>
                    </div>
                </body> 
                </html>';
                $headers = "MIME-Version: 1.0" . "\r\n"; 
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            mail($to,$subject,$txt,$headers);
            return response()->json([
                'message'=> "Password is successfully changed.",
                'status_code' => 200,
                'status' => true
            ]);
        }
        
        //$user->notify(new PasswordResetSuccess($passwordReset));
        
    }

    public function changePassword(Request $request)
    {
        $token = $request->header('Authorization');
        
     	$user = User::where('user_api_token',$token)->first();
        if($user) {
            if ($user->password==null) {
                $user->password = bcrypt($request->password);
                $user->save();
                $message ='Successfully changed your password.';
                $status_code = 200;
                $status = true;
            }else{
                if(Hash::check($request->oldpassword, $user->password)){
                    $user->password = bcrypt($request->password);
                    $user->save();
                    $message ='Successfully changed your password.';
                    $status_code = 200;
                    $status = true;
                }else{
                    $message = 'Old Password is wrong';
                    $status_code = 404;
                    $status = false;
                }
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
            'old_password_req' => bcrypt($request->oldpassword),
            'old_pwd' => $user->password

        ]);   
    }
}
