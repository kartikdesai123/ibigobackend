		
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
   
});

//Admin routes
Route::post('login', 'Admin\AdminController@login');

//forget password 
Route::post('forget_password', 'Admin\ForgetPasswordController@create');
//Route::get('find/{reset_password_token}', 'Admin\ForgetPasswordController@find');
Route::post('reset/{token}', 'Admin\ForgetPasswordController@reset');
Route::get('verify/{token}', 'UserController@verifyUser');

//change password
Route::post('change_password','Admin\ForgetPasswordController@changePassword');
// Route::middleware('AdminAPIToken')->group(function () {
	
// }

//user side route
Route::post('user/forget_password', 'UserForgetPasswordController@create');
Route::post('user/reset/{token}', 'UserForgetPasswordController@reset');
Route::post('user/change_password','UserForgetPasswordController@changePassword');

Route::delete('interest/delete/{id}','InterestController@delete');
Route::post('interest/create','InterestController@create');
Route::post('interest/update/{id}','InterestController@update');
Route::get('interest','InterestController@list');
Route::get('interest/detail/{id}','InterestController@single_detail');

Route::get('get-interests','Admin\UserController@getInterests');
Route::post('user/create','Admin\UserController@create');
Route::get('users','Admin\UserController@list');
Route::post('user/update/{id}','Admin\UserController@update');
Route::get('user/edit/{id}','Admin\UserController@edit');
Route::delete('user/delete/{id}','Admin\UserController@delete');


Route::post('business/create','Admin\BusinessUserController@create');
Route::get('business','Admin\BusinessUserController@list');
Route::post('business/update/{id}','Admin\BusinessUserController@update');
Route::get('business/edit/{id}','Admin\BusinessUserController@edit');
Route::delete('business/delete/{id}','Admin\BusinessUserController@delete');
Route::post('admin/business/update-files/{id}','Admin\BusinessUserController@updateFiles');

Route::get('group-list','Admin\GroupController@list');

//Client APIS
Route::post('user/register','UserController@create');
Route::post('user/check-email','UserController@checkEmail');
Route::post('user/login', 'UserController@login');
Route::post('user/mobile/login', 'UserController@mobilelogin');

Route::get('get-user','UserUpdateController@getUser');
Route::post('user/update','UserUpdateController@update');
Route::get('change-interest/{changeid}','UserUpdateController@changeInterest');
//Route::post('user/edit/{id}','UserController@edit');





Route::get('get-update-user','UserProfileController@getUser');
Route::post('user/update-background','UserProfileController@changeBackground');
Route::post('user/update-about','UserProfileController@changeAbout');



Route::post('business/register','BusinessUserController@create');

Route::get('get-business-user','BusinessUserController@getUser');
Route::post('business/update','BusinessUserController@update');
Route::post('business/update-files','BusinessUserController@updateFiles');
Route::match(array('GET','POST'),'webhook/redirect','PaymentController@getPayment');
Route::match(array('GET','POST'),'webhook/redirect1','PaymentController@mail_test_for');

//Route::post('webhook/redirect','PaymentController@getPayment');

Route::post('facebook/login', 'SocialAuthController@login');

Route::get('friend_requests','FriendRelationController@friend_requests');

Route::get('send-request/{id}','FriendRelationController@sendRequest');
Route::get('accept-request/{id}','FriendRelationController@acceptRequest');
Route::get('reject-request/{id}','FriendRelationController@rejectRequest');

Route::get('unfriend/{id}','FriendRelationController@unfriend');

Route::get('getfriends','FriendRelationController@getFriends');
Route::get('getgroups','GroupController@getGroups');

Route::post('get-spots','SpotDetailController@getSpots');
Route::get('get-spot/{id}','SpotDetailController@getSpotDetail');

Route::get('get-users','SpotDetailController@getUsers');
Route::post('add-spot','SpotDetailController@addSpot');
Route::get('like-spot/{id}','SpotDetailController@like');
Route::post('invite-friend','SpotDetailController@inviteFriend');

Route::post('join-spot','SpotDetailController@joinSpot');
Route::get('get-user-spots','SpotDetailController@getUserSpots');
Route::get('spot/{id}','SpotDetailController@getSpot');
Route::get('connect-spot/{id}','SpotDetailController@connect');

Route::get('notifications','SpotDetailController@notifications');
Route::get('change-notification-status','SpotDetailController@changeStatus');
Route::get('get-posts','PostController@getAllPosts');
Route::post('load-posts','PostController@loadNewPost');

Route::get('get-spot-post/{id}','PostController@getAllPostsOfSpots');

Route::get('like-post/{id}','PostController@likePost');
Route::post('add-comment','PostController@addComment');
Route::post('update-comment','PostController@updateComment');
Route::post('delete-comment/{id}','PostController@deleteComment');

Route::get('edit-checkin/{id}','SpotDetailController@editCheckin');
Route::post('update-checkin','SpotDetailController@updateCheckin');
Route::get('delete-checkin/{id}','SpotDetailController@deleteCheckin');

Route::post('add-review','SpotDetailController@addReview');
Route::post('add-reply','SpotDetailController@addReply');
Route::post('delete-reply/{id}','SpotDetailController@deleteReply');
Route::post('update-reply','SpotDetailController@updateReply');
Route::post('update-review','SpotDetailController@updateReview');
Route::post('delete-review','SpotDetailController@deleteReview');

Route::get('admin/checkin/list','Admin\AdminController@checkinList');
Route::get('admin/spot-post/{id}','Admin\AdminController@spotDetails');

Route::get('admin/post/{id}','Admin\AdminController@checkinDetails');

Route::get('admin/posts/list','Admin\AdminController@postList');
Route::get('admin/spot-posts/list','Admin\AdminController@spotPostList');

Route::post('admin/remove-review/{id}','Admin\AdminController@removeReview');

Route::post('admin/remove-comment/{id}','Admin\AdminController@removeComment');
Route::get('admin/remove-post/{id}','Admin\AdminController@removePost');

Route::get('admin/post-reviews','Admin\AdminController@postReviewList');


Route::get('admin/group-posts/list','Admin\GroupController@groupPostList');



Route::post('add-demo-spot','SpotDetailController@demoaddSpot');

Route::post('add-spot-files','SpotDetailController@addFilesByUser');
Route::post('update-spot-files','SpotDetailController@updateFilesByUser');

Route::get('get-all-interests','InterestController@getInterests');

Route::post('get-all-friends','PeopleController@getAllFriends');
Route::post('get-all-spots','BusinessUserController@getAllSpots');
Route::post('get-all-peoples','PeopleController@getAllPeoples');

Route::post('get-all-suggestions','SearchController@getSuggestions');
Route::get('people/{id}','PeopleController@getPeople');
//Route::get('get-all-groups','GroupController@getAllGroups');
Route::get('friend-suggestions','FriendRelationController@friendSuggestions');
Route::post('spot-suggestions','SearchController@spotSuggestions');

Route::post('search','SearchController@search');

Route::post('foursquare','FoursquareController@find');

Route::get('/', 'ChatsController@index');
Route::get('fetch-message/{to_user_id}', 'ChatsController@fetchMessages');
Route::post('send-message/{to_user_id}', 'ChatsController@sendMessage');

Route::post('create-group','GroupController@createGroup');
Route::get('list-group','GroupController@list');
Route::get('get-group/{id}','GroupController@getGroup');
Route::get('get-event/{id}','EventController@getEvent');
Route::post('group/update-background/{id}','GroupController@updateBackground');

Route::get('join-group/{id}','GroupController@joinGroup');
Route::get('leave-group/{id}','GroupController@leaveGroup');
Route::get('cancel-group-request/{id}','GroupController@cancelGroupRequest');
Route::get('confirm-group-invitation/{id}','GroupController@confirmGroupInvitation');
Route::get('reject-group-invitation/{id}','GroupController@rejectGroupInvitation');
Route::get('get-group-posts/{id}','GroupController@getGroupPost');

Route::post('invite-to-group','GroupController@inviteFriend');
Route::get('invite-list-friend-for-group/{id}','GroupController@getFriends');

Route::post('add-update-goto','GoToListController@addUpdateGoto');
Route::post('add-to-goto','GoToListController@addToGoto');


Route::get('get-goto','GoToListController@getGoto');

Route::get('like-goto/{goto_id}','GoToListController@likeGoTo');

Route::post('add-planning','GoToListController@addPlanning');
Route::get('get-planning','GoToListController@getPlanning');

Route::post('like', 'ChatsController@like');

Route::get('just-checkedin','PostController@justCheckedIn');

Route::get('recently-users','PostController@recentlyUsers');
Route::get('group-or-user/{id}','GroupController@getGroupOrUser');

Route::get('recent-chats','ChatsController@recentChats');
Route::get('recent-friends','ChatsController@getRecentFriends');
Route::get('get-other-spots','GoToListController@getOtherSpots');

Route::get('get-share-data/{id}','PostController@getShareData');
Route::post('update-share-data','PostController@updateShareData');


Route::post('create-event','EventController@create_event');
Route::get('get-user-events','EventController@listEvent');
Route::post('event/update-background/{id}','EventController@updateBackground');
Route::get('edit-event/{id}','EventController@edit');
Route::get('delete-event/{id}','EventController@delete');

Route::get('calendar_events','GoToListController@getCalendarEvents');

Route::post('update-event','EventController@update');
Route::get('demotask', 'ChatsController@demo');
Route::post('invite-to-event', 'EventController@inviteToEvent');

Route::get('get-connected-users', 'EventController@getConnectedUser');

/*Route::group(['middleware' => ['web']], function () {
    Route::get('facebook/redirect', 'SocialAuthController@redirect');
    Route::get('facebook/callback', 'SocialAuthController@callback');
});
Route::group(['middleware' => 'cors', 'prefix' => 'api'], function()
{
    Route::get('social/redirect',  'SocialAuthController@redirect');
    Route::get('social/callback', 'SocialAuthController@callback');
});*/

Route::get('logout','UserController@logout');