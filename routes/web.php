<?php

use App\Http\Controllers\InspectionController;
use Illuminate\Support\Facades\Route;

Route::controller(InspectionController::class)->group(function () {
    Route::get('/', 'alarms')->name('alarms');
    Route::get('/subjects/{slug}', 'subject')->name('subject');
    Route::get('/sources', 'sources')->name('sources');
    Route::post('/alarms/{alarm}/verdict', 'verdict')->whereNumber('alarm')->name('verdict');
});
