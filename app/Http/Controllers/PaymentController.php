<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Response;

class PaymentController extends Controller
{
    public function getPayment(Request $request)
    {
    	//dd($request);
    	$trans_id = $request->id;
    	/*$data = file_get_contents("php://input");
		$events = json_decode($data, true);*/
    	$to = 'mk@logixbuilt.com';
		$subject = "Redirected Webhook";
		//$txt = implode('',$request->all());
		 $txt =  "Transation ID : ".$trans_id;
		//print_r( $request->all());
		$headers = "MIME-Version: 1.0" . "\r\n"; 
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
		$headers .= "From: demo.logixbuiltinfo@gmail.com" . "\r\n" .
		"CC: kl@logixbuilt.com";
		mail($to,$subject,$txt,$headers);
		echo "Something happening here...";
    }

   	/* 
    public function handle(Request $request) {
      dd($request->all()); // <--  Like this
      $donateid = $request->id;
      $donate = Donate::where('payment_id', $donateid);
      $payment = Mollie::api()->payments()->get($donateid);
      
      if($payment->isPaid()) {
        $donate->payment_status = "Paid";
        $donate->save();
      }
    }
    */

    public function mail_test(Request $request){
        $to = 'kl@logixbuilt.com';
    		$subject = "Redirected Webhook";
    		//$txt = implode('',$request->all());
    		 $txt =  "Transation ID : ".$request->id;
    		//print_r( $request->all());
    		$headers = "MIME-Version: 1.0" . "\r\n"; 
    		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    		$headers .= "From: demo.logixbuiltinfo@gmail.com";
    		 
    		mail($to,$subject,$txt,$headers);
    		echo "Something happening here...";
    }

     public function mail_test_for(Request $request){
        
        header('Content-Type: application/json');
        $request = file_get_contents('php://input');
        $req_dump = print_r( $request, true );
         

        $to = 'kl@logixbuilt.com';
        $subject = "Redirected Webhook";
        $txt = "Contents goe here".$req_dump;
        //$txt = file_get_contents($request->all())
        //print_r( $request->all());
        $headers = "MIME-Version: 1.0" . "\r\n"; 
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: demo.logixbuiltinfo@gmail.com";
         
        mail($to,$subject,$txt,$headers);
        echo "Something happening here...";
    }
}
