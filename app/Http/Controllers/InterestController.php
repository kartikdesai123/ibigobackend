<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\interest;
use App\Admin;
use Validator;

class InterestController extends Controller
{
    public function create(Request $request)
    {

    	$token = $request->header('Authorization');
    	
    	$user = Admin::where('api_token',$token)->first();
    	if($user)
    	{
    		$rules = [
             'title' => 'required|string|max:255',
             'description' => 'required|string',
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
	        }else if(!$request->hasFile('file')){
	        	return response()->json([
	            	'messages' => 'Please Select File First',
	            	'status_code' => 401,
	            	'status' => false
	            ]);
	        }else{
	        	
	        	$file = $request->file('file');
			    $filename = $file->getClientOriginalName();
			    $extension = $file->getClientOriginalExtension();
			    $picture = date('dmyHis').'-'.$filename;
			    $file->move(public_path('interest_images'), $picture);
	        	
	        	$insert_array=array(
	        		'title'=>$request['title'],
	        		'description'=>$request['description'],
	        		'fa_icon'=>$request['fa_icon'],
	        		'created_by'=>$user->id,
	        		'created_at'=>now(),
	        		'updated_at'=>now(),
	        		'image'=>$picture,
	        	);
	        	$insert_details=interest::create($insert_array);

	        	if(!empty($insert_details))
	        	{
	        		return response()->json([
	            	'messages' => 'Interst is Added Successfully.',
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
	            	'messages' => 'Unauthenticate User',
	            	'status_code' => 401,
	            	'status' => false
	            ]);

    	}
    }

    public function update(Request $request,$id='')
    {
    	$token = $request->header('Authorization');
    	$user = Admin::where('api_token',$token)->first();
    	if($user)
    	{
    		$check_details=interest::find($id);

    		if(!empty($check_details))
    		{
    			$rules = [
	             'title' => 'required|string|max:255',
	             'description' => 'required|string',
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
		        	if ($request->hasFile('file')) {
		        		$file = $request->file('file');
					    $filename = $file->getClientOriginalName();
					    $extension = $file->getClientOriginalExtension();
					    $picture = date('dmyHis').'-'.$filename;
					    $file->move(public_path('interest_images'), $picture);
		        	}
		        	
		        	$update_array=array(
		        		'title'=>$request['title'],
		        		'description'=>$request['description'],
		        		'fa_icon'=>$request['fa_icon'],
		        		'created_by'=>$user->id,
		        		'updated_at'=>now()
		        	);

		        	if(isset($request['file']) && $request['file']!='' && $request->hasFile('file'))
		        	{
		        		$update_array['image']=$picture;
		        	}

		        	interest::where('id',$id)->update($update_array);

		        	$insert_details=interest::find($id);

		        	if(!empty($insert_details))
		        	{
		        		return response()->json([
		            	'messages' => 'Interst updated successfully.',
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

    public function getInterests()
	{
		//$token = $request->header('Authorization');
    	
		$interests = interest::all();
		return response()->json([
		            	'interest_details' => $interests,
		            	'status_code' => 201,
		            	'status' => true
		            	]);
	}

    public function list(Request $request)
    {
    	$token = $request->header('Authorization');
    	
    	$user = Admin::where('api_token',$token)->first();
    	if($user)
    	{
    		$list=interest::all();

    		return response()->json([
		            	'interest_details' => $list,
		            	'status_code' => 201,
		            	'status' => true
		            	], 201);
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

    public function delete(Request $request,$id)
    {
    	$token = $request->header('Authorization');
    	$user = Admin::where('api_token',$token)->first();
    	if($user)
    	{
    		 $check_details=interest::find($id);

    		if(!empty($check_details))
    		{
    			$check_details->delete();
    			
    			return response()->json([
		            	'message' => 'Successfully Record deleted. ',
		            	'status_code' => 201,
		            	'status' => true
		            	], 201);
			}
    		else
    		{
    			return response()->json([
	            	'message' => 'Record is not found',
	            	'status_code' => 401,
	            	'status' => false
	            ], 401);
    		}
    	}
    	else
    	{
    		 return response()->json([
	            	'message' => 'Unauthenticate User',
	            	'status_code' => $token,
	            	'status' => false
	            ], 401);
    	}
    }

    public function single_detail(Request $request,$id='')
    {
    	$token = $request->header('Authorization');
    	$user = Admin::where('api_token',$token)->first();
    	if($user)
    	{
    		 $check_details=interest::find($id);

    		if(!empty($check_details))
    		{
    			
    			return response()->json([
		            	'interest' => $check_details,
		            	'status_code' => 201,
		            	'status' => true
		            	], 201);
			}
    		else
    		{
    			return response()->json([
	            	'error' => 'Record is not found',
	            	'status_code' => 401,
	            	'status' => false
	            ], 401);
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
}
