<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('transactions', 'pages::transactions.index')->name('transactions.index');
    Route::livewire('transactions/create', 'pages::transactions.form')->name('transactions.create');
    Route::livewire('transactions/{transaction}/edit', 'pages::transactions.form')
        ->whereNumber('transaction')->name('transactions.edit');
    Route::livewire('categories', 'pages::categories.index')->name('categories.index');
});

require __DIR__.'/settings.php';
