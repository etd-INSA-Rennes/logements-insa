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

Route::group(['middleware' => ['cas.auth', 'register']], function() {

    Route::get('/', 'PagesController@welcome');
    Route::get('home', 'PagesController@home');
    Route::get('about', 'PagesController@about');

    Route::post('upload', 'UploadController@upload');
    Route::post('upload/delete', 'UploadController@delete');

    Route::get('photos/{id?}', 'UploadController@getPhotos');

    Route::resource('bids', 'BidController');

    Route::group(['namespace' => 'Admin', 'prefix' => 'admin'], function()
    {
        Route::resource('types', 'TypeController', ['as' => 'admin']);

        Route::get('bids/{bid}/approve', 'BidController@approve');
        Route::get('bids/{bid}/reject', 'BidController@reject');
        Route::get('bids/{bid}/postpone', 'BidController@postpone');
        Route::resource('bids', 'BidController', ['as' => 'admin']);
    });

    Route::post('logout', function() {
        cas()->logout();
    });
});
