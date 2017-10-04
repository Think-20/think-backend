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

Route::group(['middleware' => ['auth.api']], function() {
    Route::get('/states/all', 'AddressController@allStates')->name('allStates');
    Route::get('/states/{stateName}', 'AddressController@states')->name('states');
    Route::get('/cities/{stateId}/{cityName}', 'AddressController@cities')->name('cities');
    Route::post('/item/image', 'ItemController@image');
    Route::get('/client-types/all', 'ClientTypeController@all');
    Route::get('/client-status/all', 'ClientStatusController@all');
    Route::get('/employees/can-insert-clients', 'EmployeeController@canInsertClients');
    Route::get('/person-types/all', 'PersonTypeController@all');
    Route::get('/bank-account-types/all', 'BankAccountTypeController@all');
    Route::get('/banks/all', 'BankController@all');
});

Route::group(['middleware' => ['auth.api','permission']], function() {
    Route::get('/employees/all', 'EmployeeController@all');
    Route::get('/employees/filter/{query}', 'EmployeeController@filter');

    Route::post('/client/save', 'ClientController@save');
    Route::put('/client/edit', 'ClientController@edit');
    Route::delete('/client/remove/{id}', 'ClientController@remove');
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
});