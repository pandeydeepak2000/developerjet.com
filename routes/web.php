<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.home')->name('home');
Route::view('/hermes', 'pages.hermes-overview')->name('hermes');
Route::view('/hermes-cli', 'pages.hermes')->name('hermes.cli');
Route::view('/hermes-workspace', 'pages.hermes-workspace')->name('hermes.workspace');
Route::view('/n8n', 'pages.n8n')->name('n8n');
Route::view('/login', 'pages.login')->name('login');
Route::view('/register', 'pages.register')->name('register');
Route::view('/forgot-password', 'pages.forgot-password')->name('password.request');
Route::view('/about', 'pages.about')->name('about');
Route::view('/contact', 'pages.contact')->name('contact');
Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/refund', 'pages.refund')->name('refund');
