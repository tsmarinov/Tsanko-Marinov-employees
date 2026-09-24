<?php

use App\Http\Controllers\EmployeePairController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EmployeePairController::class, 'show'])->name('employee-pairs.show');
Route::post('/import', [EmployeePairController::class, 'store'])->name('employee-pairs.store');
