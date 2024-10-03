<?php

use App\Http\Controllers\S3controller;
use Illuminate\Support\Facades\Route;

Route::get('/', [S3controller::class, 'index'])->name('index');
Route::post('/upload-test-file', [S3Controller::class, 'uploadTestFile'])->name('upload.test.file');
Route::delete('/delete-file', [S3Controller::class, 'deleteFile'])->name('delete.file');

