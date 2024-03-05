<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;
use App\Http\Controllers\InvestorController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TestingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\IntegrityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FoldersController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\TaskController;


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

// Use middleware clientuser if url is accessable to client, user and admin. For admin and standard user use standarduser middleware, for admin use middleware adminuser
Route::middleware(['clientuser'])->group(function () {

    /********************** GET ***********************/
    //Users
    Route::get('user/view/{userid}', [UserController::class, 'viewuser'])->name('viewuser');
    Route::get('allusers', [UserController::class, 'allusers'])->name('allusers');
    // Files
    Route::get('allfiles', [FilesController::class, 'allfiles'])->name('allfiles');
    Route::get('file/view/{fileid}', [FilesController::class, 'viewfile'])->name('viewfile');
    Route::get('alldownloadlogs', [FilesController::class, 'downloadlogslist'])->name('downloadlogslist');
    // Folders
    // Route::get('folder/get-folder-details/{$folderid}', [FoldersController::class, 'getfolderdetails'])->name('getfolderdetails');
    Route::get('folder/view-all', [FoldersController::class, 'viewall'])->name('viewall');
    Route::get('folder/view/{folderid}', [FoldersController::class, 'viewfolder'])->name('viewfolder');
    Route::get('folder/delete/{folderid}', [FoldersController::class, 'deletefolder'])->name('deletefolder');

    /********************** POST ***********************/
    //Users
    Route::post('user/logout', [UserController::class, 'logout'])->name('logout');
    Route::post('user/current', [UserController::class, 'currentuser'])->name('currentuser');
    Route::post('user/update/{userid}', [UserController::class, 'updateuser'])->name('updateuser');
    // Files
    Route::post('file/download', [FilesController::class, 'downloadfile'])->name('downloadfile');
    Route::post('file/add', [FilesController::class, 'addfile'])->name('addfile');
    Route::post('file/update', [FilesController::class, 'updatefile'])->name('updatefile');
    Route::post('file/delete/{fileid}', [FilesController::class, 'deletefile'])->name('deletefile');
    Route::post('file/delete-files', [FilesController::class, 'deletemultiplefilesnfolders'])->name('deletemultiplefilesnfolders');
    // Folders
    Route::post('folder/add-folder', [FoldersController::class, 'addfolder'])->name('addfolder');
    Route::post('folder/edit-folder', [FoldersController::class, 'editfolder'])->name('editfolder');
    Route::post('folder/add-nested-folders', [FoldersController::class, 'addnestedfolders'])->name('addnestedfolders');
    // Get shortcut link 
    Route::post('dashboard/get-url-shortcut', [DashboardController::class, 'generateshortcut'])->name('generateshortcut');
});


// Use middleware standarduser if url is accessable to user and admin. For admin only urls,use middleware adminuser
Route::middleware(['standarduser'])->group(function () {
    /********************** GET ***********************/
    //Users
    Route::get('testing', [UserController::class, 'testing'])->name('testing');
    // Project
    Route::get('allprojects', [ProjectController::class, 'allprojects'])->name('allprojects');
    Route::get('project/view/{projectid}', [ProjectController::class, 'viewproject'])->name('viewproject');
    Route::get('all-archives', [ProjectController::class, 'allArchive'])->name('all-archives');
    // Review
    Route::get('review/category-questions', [ReviewController::class, 'categoryquestions'])->name('categoryquestions');
    Route::get('review/getrow', [ReviewController::class, 'getsinglerow'])->name('getsinglerow');
    // Integrity
    Route::get('integrity/getrow', [IntegrityController::class, 'getsingleintegrityrow'])->name('getsingleintegrityrow');
    Route::get('integrity/getrowview', [IntegrityController::class, 'getsingleintegrityrowview'])->name('getsingleintegrityrowview');
    // Task 
    Route::get('task/getreviewdetailsview', [TaskController::class, 'getreviewdetailsview'])->name('getreviewdetailsview');
    // Dashboard api 
    Route::get('dashboard/dashboard-details', [DashboardController::class, 'dashboarddetails'])->name('dashboarddetails');    
    

    /********************** POST ***********************/
    // Review
    Route::post('review/saverow', [ReviewController::class, 'saverowdetails'])->name('saverowdetails');
    // Integrity
    Route::post('integrity/saverow', [IntegrityController::class, 'saverowintegritydetails'])->name('saverowintegritydetails');
    // Project
    Route::post('project/generate-report', [ProjectController::class, 'generatereport'])->name('generatereport');
    // Task 
    Route::post('task/view-project-tasks', [TaskController::class, 'viewprojecttasks'])->name('viewprojecttasks');
    Route::post('task/get-review-details/', [TaskController::class, 'getreviewdetails'])->name('getreviewdetails');
    Route::post('task/get-integrity-details/', [TaskController::class, 'getintegritydetails'])->name('getintegritydetails');
});


//  For admin only urls
Route::middleware(['adminuser'])->group(function () {
    // Investors
    Route::get('allinvestors', [InvestorController::class, 'allinvestors'])->name('allinvestors');
    Route::get('investor/view/{investorid}', [InvestorController::class, 'viewinvestor'])->name('viewinvestor');
    // Platforms
    Route::get('allplatforms', [PlatformController::class, 'allplatforms'])->name('allplatforms');
    Route::get('platform/view/{platformid}', [PlatformController::class, 'viewplatform'])->name('viewplatform');
    // Project
    Route::get('project/sheetheaders', [ProjectController::class, 'getsheetheaders'])->name('getsheetheaders');
    // Route::get('project/view/{projectid}', [ProjectController::class, 'viewproject'])->name('viewproject');
    Route::get('project/import-progress', [ProjectController::class, 'importprogress'])->name('importprogress');
    // Questions
    Route::get('allquestions', [QuestionController::class, 'allquestions'])->name('allquestions');
    Route::get('question/view/{questionid}', [QuestionController::class, 'viewquestion'])->name('viewquestion');
    // Templates
    Route::get('alltemplates', [TemplateController::class, 'alltemplates'])->name('alltemplates');
    Route::get('template/view/{templateid}', [TemplateController::class, 'viewtemplate'])->name('viewtemplate');
    // //Dashboard
    // Route::get('dashboard/dashboard-details', [DashboardController::class, 'dashboarddetails'])->name('dashboarddetails');
    // Logs
    Route::get('logs/get-site-logs', [LogsController::class, 'getsitelogs'])->name('getsitelogs');
    Route::get('logs/get-session-logs', [LogsController::class, 'getsessionlogs'])->name('getsessionlogs');

    /********************** POST ***********************/
    //Users
    Route::post('user/add', [UserController::class, 'adduser'])->name('adduser');
    Route::post('user/delete/{userid}', [UserController::class, 'deleteuser'])->name('deleteuser');
    Route::post('user/resend-verification', [UserController::class, 'resendverifyemail'])->name('resendverifyemail');
    Route::post('user/restore/{userid}', [UserController::class, 'restoreuser'])->name('restoreuser');
    // Investors
    Route::post('investor/add', [InvestorController::class, 'addinvestor'])->name('addinvestor');
    Route::post('investor/update/{investorid}', [InvestorController::class, 'updateinvestor'])->name('updateinvestor');
    Route::post('investor/delete/{investorid}', [InvestorController::class, 'deleteinvestor'])->name('deleteinvestor');
    Route::post('investor/restore/{investorid}', [InvestorController::class, 'restoreinvestor'])->name('restoreinvestor');
    // Platforms
    Route::post('platform/add', [PlatformController::class, 'addplatform'])->name('addplatform');
    Route::post('platform/update/{platformid}', [PlatformController::class, 'updateplatform'])->name('updateplatform');
    Route::post('platform/delete/{platformid}', [PlatformController::class, 'deleteplatform'])->name('deleteplatform');
    Route::post('platform/restore/{platformid}', [PlatformController::class, 'restoreplatform'])->name('restoreplatform');
    // Project
    Route::post('project/addedit', [ProjectController::class, 'addeditproject'])->name('addeditproject');
    Route::post('project/importsheet', [ProjectController::class, 'importsheet'])->name('importsheet');
    Route::post('project/managesheetheaders', [ProjectController::class, 'managesheetheaders'])->name('managesheetheaders');
    Route::post('project/add-templatequestions', [ProjectController::class, 'addtemplatequestions'])->name('addtemplatequestions');
    Route::post('project/assign-users', [ProjectController::class, 'assignusers'])->name('assignusers');
    Route::post('project/delete/', [ProjectController::class, 'deleteproject'])->name('deleteproject');
    Route::post('project/restore/{projectid}', [ProjectController::class, 'restoreproject'])->name('restoreproject');
    Route::post('project/archive', [ProjectController::class, 'isArchived'])->name('archive');
    Route::post('project/unarchive', [ProjectController::class, 'Unarchive'])->name('unarchive');
    // Questions
    Route::post('question/add', [QuestionController::class, 'addquestion'])->name('addquestion');
    Route::post('question/update/{questionid}', [QuestionController::class, 'updatequestion'])->name('updatequestion');
    Route::post('question/delete/{questionid}', [QuestionController::class, 'deletequestion'])->name('deletequestion');
    Route::post('question/restore/{questionid}', [QuestionController::class, 'restorequestion'])->name('restorequestion');
    Route::post('question/reorder', [QuestionController::class, 'reorderquestions'])->name('reorderquestions');
    // Templates
    Route::post('template/add', [TemplateController::class, 'addtemplate'])->name('addtemplate');
    Route::post('template/update/{templateid}', [TemplateController::class, 'updatetemplate'])->name('updatetemplate');
    Route::post('template/delete/{templateid}', [TemplateController::class, 'deletetemplate'])->name('deletetemplate');
    Route::post('template/deletemultiple', [TemplateController::class, 'multidelete'])->name('multidelete');
    Route::post('template/assign-questions', [TemplateController::class, 'assignquestions'])->name('assignquestions');
    Route::post('template/restore/{templateid}', [TemplateController::class, 'restoretemplate'])->name('restoretemplate');
    // Dashboard
    // Route::get('dashboard/dashboard-details', [DashboardController::class, 'dashboarddetails'])->name('dashboarddetails');
    //logs
    Route::post('logs/delete-site-logs/{noofmonths}', [LogsController::class, 'deletesitelogs'])->name('deletesitelogs');
    Route::post('logs/delete-session-logs/{noofmonths}', [LogsController::class, 'deletesessionlogs'])->name('deletesessionlogs');

    Route::post('importsheetdata', [ProjectController::class, 'importsheetdata'])->name('importsheetdata');
    Route::get('readsheet', [TestingController::class, 'readsheet'])->name('readsheet');
    Route::get('deletejunkdata', [TestingController::class, 'deletejunkdata'])->name('deletejunkdata');

    // manually sending email for delete downloaded files
    Route::get('dashboard/mail-delete-downloaded-files', [DashboardController::class, 'maildeletedownloadedfiles'])->name('maildeletedownloadedfiles');
});

// All non-auth paths
Route::post('user/login', [UserController::class, 'login'])->name('login');
Route::post('user/forgot-password', [UserController::class, 'forgotpass'])->name('forgotpass');
Route::post('user/reset-password', [UserController::class, 'resetpassword'])->name('resetpassword');
Route::post('user/verifyemail', [UserController::class, 'verifyemail'])->name('verifyemail');
Route::post('user/setpassword', [UserController::class, 'setuserpassword'])->name('setuserpassword');

Route::get('temp-testing', [UserController::class, 'temptesting'])->name('temptesting');
// confirm delete email
Route::post('user/email-delete-confirmation', [UserController::class, 'verifyemaildelete'])->name('verifyemaildelete');

Route::get('redirect-url/{shortcutid}', [DashboardController::class, 'redirecturl'])->name('redirecturl');
Route::post('dashboard/import-excel-data', [DashboardController::class, 'importexceldata'])->name('importexceldata');
// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
