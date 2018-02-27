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

Route::post('/login', 'UserController@login')->name('login');
Route::post('/logout', 'UserController@logout')->name('logout');

/*  
    Construir authenticate request para imagens 
    http://blog.jsgoupil.com/request-image-files-with-angular-2-and-an-bearer-access-token/
*/
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

Route::get('/pass', function() {
    dd( [
        'Tais' => bcrypt('t2027'),
        'Elaine' => bcrypt('e2128'),
    ]);
});

Route::group(['middleware' => ['auth.api']], function() {

    Route::post('/upload-file', 'UploadFileController@upload');

    Route::get('/states/all', 'AddressController@allStates')->name('allStates');
    Route::get('/states/{stateName}', 'AddressController@states')->name('states');
    Route::get('/cities/{stateId}/{cityName}', 'AddressController@cities')->name('cities');
    
    Route::get('/client-types/all', 'ClientTypeController@all');
    Route::get('/client-status/all', 'ClientStatusController@all');
    Route::get('/employees/can-insert-clients', 'EmployeeController@canInsertClients');

    Route::get('/person-types/all', 'PersonTypeController@all');
    Route::get('/bank-account-types/all', 'BankAccountTypeController@all');
    Route::get('/banks/all', 'BankController@all');
    
    Route::get('/measures/all', 'MeasureController@all');
    Route::get('/measures/filter/{query}', 'MeasureController@filter');
    
    Route::get('/jobs/all', 'JobController@all');
    Route::get('/jobs/filter/{query}', 'JobController@filter');
    
    Route::get('/job-types/all', 'JobTypeController@all');
    Route::get('/job-types/filter/{query}', 'JobTypeController@filter');
    
    Route::get('/briefing-competitions/all', 'BriefingCompetitionController@all');
    Route::get('/briefing-competitions/filter/{query}', 'BriefingCompetitionController@filter');
    
    Route::get('/briefing-presentations/all', 'BriefingPresentationController@all');
    Route::get('/briefing-presentations/filter/{query}', 'BriefingPresentationController@filter');
    
    Route::get('/briefing-special-presentations/all', 'BriefingSpecialPresentationController@all');
    Route::get('/briefing-special-presentations/filter/{query}', 'BriefingSpecialPresentationController@filter');
    
    Route::get('/stand-configurations/all', 'StandConfigurationController@all');
    Route::get('/stand-configurations/filter/{query}', 'StandConfigurationController@filter');
    
    Route::get('/stand-genres/all', 'StandGenreController@all');
    Route::get('/stand-genres/filter/{query}', 'StandGenreController@filter');
    
    Route::get('/briefings/load-form', 'BriefingController@loadForm');
});

Route::group(['middleware' => ['auth.api','permission']], function() {
    Route::get('/employees/all', 'EmployeeController@all');
    Route::get('/employees/filter/{query}', 'EmployeeController@filter');

    Route::post('/client/save', 'ClientController@save');
    Route::put('/client/edit', 'ClientController@edit');
    Route::delete('/client/remove/{id}', 'ClientController@remove');
    Route::post('/client/import', 'ClientController@import');
    Route::get('/clients/all', 'ClientController@all');
    Route::get('/clients/get/{id}', 'ClientController@get');
    Route::get('/clients/filter/{query}', 'ClientController@filter');

    Route::post('/my-client/save', 'ClientController@saveMyClient');
    Route::put('/my-client/edit', 'ClientController@editMyClient');
    Route::delete('/my-client/remove/{id}', 'ClientController@removeMyClient');
    Route::get('/my-clients/all', 'ClientController@allMyClient');
    Route::get('/my-clients/get/{id}', 'ClientController@getMyClient');
    Route::get('/my-clients/filter/{query}', 'ClientController@filterMyClient');

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

    Route::post('/briefing/save', 'BriefingController@save');
    Route::put('/briefing/edit', 'BriefingController@edit');
    Route::delete('/briefing/remove/{id}', 'BriefingController@remove');
    Route::get('/briefings/all', 'BriefingController@all');
    Route::get('/briefings/get/{id}', 'BriefingController@get');
    Route::get('/briefings/filter/{query}', 'BriefingController@filter');
    Route::get('/briefing/download/{id}/{type}/{file}', 'BriefingController@downloadFile');

    Route::post('/my-briefing/save', 'BriefingController@saveMyBriefing');
    Route::put('/my-briefing/edit', 'BriefingController@editMyBriefing');
    Route::delete('/my-briefing/remove/{id}', 'BriefingController@removeMyBriefing');
    Route::get('/my-briefings/all', 'BriefingController@allMyBriefing');
    Route::get('/my-briefings/get/{id}', 'BriefingController@getMyBriefing');
    Route::get('/my-briefings/filter/{query}', 'BriefingController@filterMyBriefing');
    Route::get('/my-briefing/download/{id}/{type}/{file}', 'BriefingController@downloadFileMyBriefing');
});