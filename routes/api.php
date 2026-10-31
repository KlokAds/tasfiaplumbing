<?php

use App\Http\Controllers\Api\ContentApiController;
use App\Http\Middleware\ContentApiToken;
use Illuminate\Support\Facades\Route;

/*
| Content hub API (/api/content/…): the company's billing app reads this site's services, approved
| prices and articles, manages the articles it made itself (draft, edit, publish, unpublish, delete),
| and revises other articles in place with a backup (and puts them back from it).
*/
Route::prefix('content')->middleware([ContentApiToken::class, 'throttle:60,1'])->group(function () {
    Route::get('/info', [ContentApiController::class, 'info']);
    Route::post('/chat', [ContentApiController::class, 'chat']);
    // The billing app runs the website: people and roles, keys, tracking codes.
    Route::get('/manage', [\App\Http\Controllers\Api\ManageApiController::class, 'show']);
    Route::post('/manage/users', [\App\Http\Controllers\Api\ManageApiController::class, 'user']);
    Route::post('/manage/roles', [\App\Http\Controllers\Api\ManageApiController::class, 'role']);
    Route::put('/manage/roles/{name}', [\App\Http\Controllers\Api\ManageApiController::class, 'role'])->where('name', '[a-z0-9-]+');
    Route::put('/manage/settings', [\App\Http\Controllers\Api\ManageApiController::class, 'settings']);
    Route::put('/manage/keys/{name}', [\App\Http\Controllers\Api\ManageApiController::class, 'key'])->where('name', '[a-z_]+');
    Route::get('/manage/pages/{page}', [\App\Http\Controllers\Api\ManagePagesController::class, 'show'])->where('page', '[a-z-]+');
    Route::post('/manage/pages/{page}/{action}/{id?}', [\App\Http\Controllers\Api\ManagePagesController::class, 'act'])->where(['page' => '[a-z-]+', 'action' => '[a-z-]+', 'id' => '[0-9]+']);
    Route::post('/check', [ContentApiController::class, 'check']);
    Route::get('/media', [ContentApiController::class, 'media']);
    Route::post('/articles', [ContentApiController::class, 'storeDraft']);
    Route::get('/articles/{id}', [ContentApiController::class, 'show'])->whereNumber('id');
    Route::put('/articles/{id}', [ContentApiController::class, 'update'])->whereNumber('id');
    Route::get('/articles/{id}/text', [ContentApiController::class, 'text'])->whereNumber('id');
    Route::post('/articles/{id}/revise', [ContentApiController::class, 'revise'])->whereNumber('id');
    Route::post('/articles/{id}/restore', [ContentApiController::class, 'restore'])->whereNumber('id');
    Route::post('/articles/{id}/publish', [ContentApiController::class, 'publish'])->whereNumber('id');
    Route::post('/articles/{id}/unpublish', [ContentApiController::class, 'unpublish'])->whereNumber('id');
    Route::delete('/articles/{id}', [ContentApiController::class, 'destroy'])->whereNumber('id');
});
