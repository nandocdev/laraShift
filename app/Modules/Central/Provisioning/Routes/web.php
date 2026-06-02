<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:central'])->group(function () {
    // Routes for provisioning management
});
