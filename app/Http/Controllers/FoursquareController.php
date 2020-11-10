<?php
//require_once 'vendor/autoload.php';

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
//use Iivannov\Larasquare\Foursquare;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use FoursquareApi;

class FoursquareController extends Controller
{
    public function find(Request $request)
    {
//        echo "adad"; exit();
//    	$searchText = $request->input('searchText');
//        $selected_user_type = $request->input('selected_user_type');
//        $selected_duration = $request->input('selected_duration');
//        $searchAddress  = $request->input('searchAddress');
        
        
        
        
        
        $endpoint = "venues/search";
//        $near = "Chicago, IL";
        $ll = 	'40.74224,-73.99386';
        $categoryId = '5bae9231bedf3950379f89c5'; //Sporting Event
        
        $foursquare = new FoursquareApi("4JYM4HIQG0KDNLXSVMD3JAB4AW2OCUXKV3H54QUEOCJMSQ4R", "WE1IGOH20042IBONYFXWWZXIWUATMBJO0RUTOA5JW3XE43WU");

// Searching for venues nearby Montreal, Quebec
$endpoint = "venues/search";
	
// Prepare parameters
$params = array("near"=>"Chicago, IL");

// Perform a request to a public resource
$response = $foursquare->GetPublic($endpoint,$params);
print_r($response); exit();
//        $config = [
//            'clientId' => '4JYM4HIQG0KDNLXSVMD3JAB4AW2OCUXKV3H54QUEOCJMSQ4R',
//            'clientSecret' => 'WE1IGOH20042IBONYFXWWZXIWUATMBJO0RUTOA5JW3XE43WU',
//            'apiUrl' => $endpoint, //optional
//        ];
//        
//        $foursquare = new Foursquare($config);
////        print_r($foursquare);
//        $search = [
//            'll' => $ll,
//            'near' => $near, 
//            'categoryId' => $categoryId,
//            'radius' => 10,
//            'limit' => 10
//        ];
//        $venues = $foursquare->venues($search);
//        print_r($venues); exit();
        
//        $client = new Client();
//        $result = $client->request('GET', 'https://api.foursquare.com/v2/venues/search', [
//            'query' => [
//                'client_id' => '4JYM4HIQG0KDNLXSVMD3JAB4AW2OCUXKV3H54QUEOCJMSQ4R',
//                'client_secret' => 'WE1IGOH20042IBONYFXWWZXIWUATMBJO0RUTOA5JW3XE43WU',
//                'v' => "20201110",
//                'll' => '40.7,-74',
//            ]
//        ]);
//        print_r($result); exit();
//        $token = $request->header('Authorization');
//        if($token)
//            $user = User::where('user_api_token',$token)->first();
//        else
//            $user = null;
        
        
            return response()->json([
                'status_code' => 200,
                'status' => true,
            ]);
        
    }

}
