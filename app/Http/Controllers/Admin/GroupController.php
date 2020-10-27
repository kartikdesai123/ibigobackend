<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Group;
use App\Admin;
use App\CheckInDetail;

class GroupController extends Controller
{
    public function list(Request $request)
    {
		$groups = Group::select('users.first_name','users.last_name','groups.*')->join('users','users.id','=','groups.group_created_by')->get();
		return response()->json([
        	'groups' => $groups,
        	'status_code' => 201,
        	'status' => true
    	]);
    }

    public function getGroup(Request $request,$unique_id)
    {
		// $group = Group::select('users.first_name','users.last_name','groups.*')->join('users','users.id','=','groups.group_created_by')->where('group_unique_id',$unique_id)->first();
		// $group_members = GroupUser::select('users.first_name','users.last_name','users.user_profile')->join('users','users.id','=','group_users.user_id')->where('invited',0)->where('group_id',$group->id)->get();
		// return response()->json([
    //       	'group' => $group,
    //       	'group_members' => $group_members,
    //       	'status_code' => 201,
    //       	'status' => true
    //   	]);
    }

    public function groupPostList(Request $request)
    {
        $checkin_details = CheckInDetail::select('check_in_details.*','users.first_name','users.last_name','users.user_profile','users.user_type')
        ->join('users','users.id','=','check_in_details.user_id')        
        ->join('groups','groups.group_created_by','=','users.id')        
        ->where('check_in_details.group_id','!=',null)
        ->get()->toArray();

        return response()->json([
            'posts' => $checkin_details,
            'status_code' => 200,
            'status' => true,
        ]);
    }
}
