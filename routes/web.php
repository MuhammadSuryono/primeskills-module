<?php

use Illuminate\Support\Facades\Route;
use Primeskills\Web\Exceptions\PrimeskillsException;
use Primeskills\Web\Response\Response;

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
    return Response::builder()->version()->buildJson();
});

Route::get('/exception', function () {
    request()->validate(['str' => 'required']);
    throw new PrimeskillsException(500, "Error", ["ERRR" => "E"]);
});
