<?php

use Illuminate\Support\Facades\Route;

/*
| Pages are thin shells; the data and access rules live in the Sanctum-protected API.
*/

Route::view('/', 'welcome');

Route::view('/dashboard', 'pages.dashboard')->name('dashboard');
Route::view('/pos', 'pages.pos')->name('pos');
Route::view('/transactions', 'pages.transactions')->name('transactions');
Route::view('/medicines', 'pages.medicines')->name('medicines');
Route::view('/stock', 'pages.stock')->name('stock');
Route::view('/suppliers', 'pages.suppliers')->name('suppliers');
Route::view('/reports', 'pages.reports')->name('reports');
Route::view('/users', 'pages.users')->name('users');
Route::view('/settings', 'pages.settings')->name('settings');
