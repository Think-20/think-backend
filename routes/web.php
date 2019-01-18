<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
Route::get('/mod', function () {
    $jobs = App\Job::all();
    foreach ($jobs as $job) { 
        foreach($job->tasks as $task) {
            App\Task::modifyReopened($task);
        }
    }
});

Route::get('/test', function () {
    $estimated_time = 1;

    $arr = App\ActivityHelper::calculateNextDate(
        '2018-07-04',
        
        App\Employee::where('name', 'LIKE', 'Rafaela%')
            ->get(),

        $estimated_time,

        App\Budget::where('available_date', '>=', '2018-06-04')
            ->where('available_date', '<=', '2018-08-04')
            ->orderBy('available_date', 'ASC')
            ->orderBy('responsible_id', 'ASC')
            ->limit(30)
            ->get()
    );

    dd($arr);
});
*/

Route::post('/login', 'UserController@login')->name('login');
Route::post('/logout', 'UserController@logout')->name('logout');

/*  
    Construir authenticate request para imagens 
    http://blog.jsgoupil.com/request-image-files-with-angular-2-and-an-bearer-access-token/
*/
Route::get('/assets/images/temp/{filename}', function ($filename)
{
    $path = sys_get_temp_dir() . '/' . $filename;

    if (!File::exists($path)) {
        abort(404);
    }

    $file = File::get($path);
    $type = File::mimeType($path);

    $response = Response::make($file, 200);
    $response->header("Content-Type", $type);

    return $response;
});

Route::get('/assets/images/{filename}', function ($filename)
{
    $path = resource_path('assets/images/' . $filename);

    if (!File::exists($path)) {
        abort(404);
    }

    $file = File::get($path);
    $type = File::mimeType($path);

    $response = Response::make($file, 200);
    $response->header("Content-Type", $type);

    return $response;
});

Route::get('/assets/images/logo/{filename}', function ($filename)
{
    $path = resource_path('assets/images/logo/' . $filename);

    if (!File::exists($path)) {
        abort(404);
    }

    $file = File::get($path);
    $type = File::mimeType($path);

    $response = Response::make($file, 200);
    $response->header("Content-Type", $type);

    return $response;
});

Route::get('/assets/images/users/{filename}', function ($filename)
{
    $path = resource_path('assets/images/users/' . $filename);

    if (!File::exists($path)) {
        abort(404);
    }

    $file = File::get($path);
    $type = File::mimeType($path);

    $response = Response::make($file, 200);
    $response->header("Content-Type", $type);

    return $response;
});

Route::get('/project-files/view/{id}', function ($id)
{
    $projectFile = App\ProjectFile::find($id);
    $path = resource_path('/assets/files/project-files/' . $projectFile->name);

    if (!File::exists($path)) {
        abort(404);
    }

    $file = File::get($path);
    $type = File::mimeType($path);

    $response = Response::make($file, 200);
    $response->header("Content-Type", $type);

    return $response;
});

/*
Route::get('/pass', function() {
    dd( [
        'Marcelo' => bcrypt('m2633'),
    ]);
});
*/

Route::group(['middleware' => ['auth.api']], function() {

    Route::post('/upload-file', 'UploadFileController@upload');

    Route::get('/states/all', 'AddressController@allStates')->name('allStates');
    Route::get('/states/{stateName}', 'AddressController@states')->name('states');
    Route::get('/cities/{stateId}/{cityName}', 'AddressController@cities')->name('cities');

    Route::get('/client-types/all', 'ClientTypeController@all');
    Route::get('/client-status/all', 'ClientStatusController@all');
    Route::get('/client-comission/all', 'ClientComissionController@all');
    Route::get('/employees/can-insert-clients', 'EmployeeController@canInsertClients');

    Route::get('/person-types/all', 'PersonTypeController@all');
    Route::get('/bank-account-types/all', 'BankAccountTypeController@all');
    Route::get('/banks/all', 'BankController@all');
    
    Route::get('/measures/all', 'MeasureController@all');
    Route::get('/measures/filter/{query}', 'MeasureController@filter');
    
    Route::get('/job-activities/all', 'JobActivityController@all');
    Route::get('/job-activities/filter/{query}', 'JobActivityController@filter');
    
    Route::get('timecard/places/all', 'TimecardPlaceController@all');
    
    Route::get('/job-types/all', 'JobTypeController@all');
    Route::get('/job-types/filter/{query}', 'JobTypeController@filter');
    
    Route::get('/briefing-competitions/all', 'BriefingCompetitionController@all');
    Route::get('/briefing-competitions/filter/{query}', 'BriefingCompetitionController@filter');
    
    Route::get('/job-status/all', 'JobStatusController@all');
    Route::get('/job-status/filter/{query}', 'JobStatusController@filter');
    
    Route::get('/briefing-presentations/all', 'BriefingPresentationController@all');
    Route::get('/briefing-presentations/filter/{query}', 'BriefingPresentationController@filter');
    
    Route::get('/stand-configurations/all', 'StandConfigurationController@all');
    Route::get('/stand-configurations/filter/{query}', 'StandConfigurationController@filter');
    
    Route::get('/stand-genres/all', 'StandGenreController@all');
    Route::get('/stand-genres/filter/{query}', 'StandGenreController@filter');
    
    Route::get('/briefing-main-expectations/all', 'BriefingMainExpectationController@all');
    Route::get('/briefing-main-expectations/filter/{query}', 'BriefingMainExpectationController@filter');
    
    Route::get('/briefing-levels/all', 'BriefingLevelController@all');
    Route::get('/briefing-levels/filter/{query}', 'BriefingLevelController@filter');
    
    Route::get('/briefing-how-comes/all', 'BriefingHowComeController@all');
    Route::get('/briefing-how-comes/filter/{query}', 'BriefingHowComeController@filter');
    
    Route::get('/jobs/load-form', 'JobController@loadForm');
    Route::get('/tasks/get-next-available-date/{availableDate}/{estimatedTime}/{jobActivity}', 'TaskController@getNextAvailableDate');
    Route::post('/tasks/get-available-dates', 'TaskController@getNextAvailableDates');
    Route::get('/tasks/updated-info', 'TaskController@updatedInfo');

    Route::get('/notifications/all', 'NotificationController@all');
    Route::get('/notifications/recents', 'NotificationController@recents');
    Route::get('/notifications/listen', 'NotificationController@listen');
    Route::put('/notifications/read', 'NotificationController@read');

    Route::post('/departments/all', 'DepartmentController@all');
    Route::post('/departments/filter', 'DepartmentController@filter');

    Route::post('/positions/all', 'PositionController@all');
    Route::post('/positions/filter', 'PositionController@filter');
});

Route::group(['middleware' => ['auth.api','permission']], function() {
    Route::post('/employees/all', 'EmployeeController@all');
    Route::post('/employees/filter', 'EmployeeController@filter');
    Route::get('/employees/get/{id}', 'EmployeeController@get');
    Route::post('/employee/save', 'EmployeeController@save');
    Route::put('/employee/edit', 'EmployeeController@edit');
    Route::delete('/employee/remove/{id}', 'EmployeeController@remove');

    Route::post('/users/all', 'UserController@all');
    Route::post('/users/filter', 'UserController@filter');
    Route::get('/users/get/{id}', 'UserController@get');
    Route::post('/user/save', 'UserController@save');
    Route::put('/user/edit', 'UserController@edit');

    Route::post('/employees/office-hours/register/another', 'TimecardController@registerAnother');
    Route::post('/employees/office-hours/register/yourself', 'TimecardController@registerYourself');
    Route::post('/employees/office-hours/show/another', 'TimecardController@showAnother');
    Route::post('/employees/office-hours/show/yourself', 'TimecardController@showYourself');
    Route::get('/employees/office-hours/get/{id}', 'TimecardController@getOfficeHour');
    Route::put('/employees/office-hours/edit', 'TimecardController@editOfficeHour');
    Route::delete('/employees/office-hours/remove/{id}', 'TimecardController@removeOfficeHour');
    Route::get('/employees/office-hours/approvals-pending/show', 'TimecardController@showApprovalsPending');
    Route::get('/employees/office-hours/approvals-pending/approve/{id}', 'TimecardController@approvePending');
    Route::get('/employees/office-hours/status/yourself', 'TimecardController@statusYourself');

    Route::post('/client/save', 'ClientController@save');
    Route::put('/client/edit', 'ClientController@edit');
    Route::delete('/client/remove/{id}', 'ClientController@remove');
    Route::post('/client/import', 'ClientController@import');
    Route::post('/clients/all', 'ClientController@all');
    Route::get('/clients/get/{id}', 'ClientController@get');
    Route::post('/clients/filter', 'ClientController@filter');

    Route::post('/my-client/save', 'ClientController@saveMyClient');
    Route::put('/my-client/edit', 'ClientController@editMyClient');
    Route::delete('/my-client/remove/{id}', 'ClientController@removeMyClient');
    //Route::get('/my-clients/all', 'ClientController@allMyClient');
    Route::get('/my-clients/get/{id}', 'ClientController@getMyClient');
    //Route::get('/my-clients/filter/{query}', 'ClientController@filterMyClient');

    Route::post('/provider/save', 'ProviderController@save');
    Route::put('/provider/edit', 'ProviderController@edit');
    Route::delete('/provider/remove/{id}', 'ProviderController@remove');
    Route::get('/providers/all', 'ProviderController@all');
    Route::get('/providers/get/{id}', 'ProviderController@get');
    Route::get('/providers/filter/{query}', 'ProviderController@filter');

    Route::post('/cost-category/save', 'CostCategoryController@save');
    Route::put('/cost-category/edit', 'CostCategoryController@edit');
    Route::delete('/cost-category/remove/{id}', 'CostCategoryController@remove');
    Route::get('/cost-categories/all', 'CostCategoryController@all');
    Route::get('/cost-categories/get/{id}', 'CostCategoryController@get');
    Route::get('/cost-categories/filter/{query}', 'CostCategoryController@filter');

    Route::post('/item-category/save', 'ItemCategoryController@save');
    Route::put('/item-category/edit', 'ItemCategoryController@edit');
    Route::delete('/item-category/remove/{id}', 'ItemCategoryController@remove');
    Route::get('/item-categories/all', 'ItemCategoryController@all');
    Route::get('/item-categories/get/{id}', 'ItemCategoryController@get');
    Route::get('/item-categories/filter/{query}', 'ItemCategoryController@filter');
    Route::get('/item-categories/items-group-by-category', 'ItemCategoryController@itemsGroupByCategory');

    Route::post('/item/save', 'ItemController@save');
    Route::put('/item/edit', 'ItemController@edit');
    Route::delete('/item/remove/{id}', 'ItemController@remove');
    Route::get('/items/all', 'ItemController@all');
    Route::get('/items/get/{id}', 'ItemController@get');
    Route::get('/items/filter/{query}', 'ItemController@filter');
    Route::post('/item/save-pricing/{id}', 'ItemController@savePricing');
    Route::delete('/item/{itemId}/remove-pricing/{pricingId}', 'ItemController@removePricing');
    Route::post('/item/save-child-item/{id}', 'ItemController@saveChildItem');
    Route::delete('/item/{itemId}/remove-child-item/{childItemId}', 'ItemController@removeChildItem');

    Route::post('/job/save', 'JobController@save');
    Route::put('/job/edit', 'JobController@edit');
    Route::delete('/job/remove/{id}', 'JobController@remove');
    Route::get('/jobs/all', 'JobController@all');
    Route::get('/jobs/get/{id}', 'JobController@get');
    Route::post('/jobs/filter', 'JobController@filter');
    Route::get('/job/download/{id}/{type}/{file}', 'JobController@downloadFile');

    Route::post('/my-job/save', 'JobController@saveMyJob');
    Route::put('/my-job/edit', 'JobController@editMyJob');
    Route::delete('/my-job/remove/{id}', 'JobController@removeMyJob');
    Route::get('/my-jobs/all', 'JobController@allMyJob');
    Route::get('/my-jobs/get/{id}', 'JobController@getMyJob');
    Route::post('/my-jobs/filter', 'JobController@filterMyJob');
    Route::get('/my-job/download/{id}/{type}/{file}', 'JobController@downloadFileMyJob');
    
    /*
    Route::post('/briefing/save', 'BriefingController@save');
    Route::put('/briefing/edit', 'BriefingController@edit');
    Route::put('/briefing/edit-available-date', 'BriefingController@editAvailableDate');
    Route::put('/my-briefing/edit-available-date', 'BriefingController@myEditAvailableDate');
    */

    Route::post('/task/save', 'TaskController@save');
    Route::put('/task/edit', 'TaskController@edit');
    Route::post('/tasks/filter', 'TaskController@filter');
    Route::get('/tasks/get/{id}', 'TaskController@get');
    Route::put('/task/edit-available-date', 'TaskController@editAvailableDate');
    Route::delete('/task/remove/{id}', 'TaskController@remove');
    
    Route::get('/task/memorial/{id}', 'TaskController@memorialPdf');

    Route::post('/my-task/save', 'TaskController@save');
    Route::post('/my-tasks/filter', 'TaskController@filterMyTask');
    Route::put('/my-task/edit-available-date', 'TaskController@myEditAvailableDate');
    Route::delete('/my-task/remove/{id}', 'TaskController@removeMyTask');
    
    Route::post('/budget/save', 'BudgetController@save');
    Route::put('/budget/edit', 'BudgetController@edit');

    Route::post('/project-files/save-multiple', 'ProjectFileController@saveMultiple');
    Route::delete('/project-files/remove/{id}', 'ProjectFileController@remove');
    Route::get('/project-files/download/{id}', 'ProjectFileController@downloadFile');
    Route::get('/project-files/download-all/{taskId}', 'ProjectFileController@downloadAll');
    
    Route::post('/schedule-block/save', 'ScheduleBlockController@save');
    Route::delete('/schedule-block/remove/{id}', 'ScheduleBlockController@remove');
    Route::post('/schedule-blocks/all', 'ScheduleBlockController@all');
    Route::get('/schedule-blocks/valid', 'ScheduleBlockController@valid');
    Route::get('/my-schedule-blocks/valid', 'ScheduleBlockController@myValid');
});