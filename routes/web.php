<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ContentAdminController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\DiaryCommentController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InitialSetupController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\RequiredPasswordController;
use App\Http\Controllers\UserAvatarController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('search', [HomeController::class, 'search'])->middleware('throttle:60,1')->name('search');
Route::get('announcements/{id}', [HomeController::class, 'announcement'])->whereUlid('id')->name('announcements.show');
Route::get('community', [CommunityController::class, 'index'])->name('community.index');
Route::get('community/{thread}', [CommunityController::class, 'show'])->whereUlid('thread')->name('community.show');
Route::get('images/{attachment}/{variant?}', [MediaController::class, 'show'])->whereUlid('attachment')->name('images.show');
Route::get('characters', [CharacterController::class, 'index'])->name('characters.index');
Route::get('characters/{character}', [CharacterController::class, 'show'])->whereUlid('character')->name('characters.show');
Route::get('diaries', [DiaryController::class, 'index'])->name('diaries.index');
Route::get('diaries/{diary}', [DiaryController::class, 'show'])->whereUlid('diary')->name('diaries.show');

Route::middleware('throttle:5,1')->group(function () {
    Route::get('setup', [InitialSetupController::class, 'create'])->name('setup.create');
    Route::post('setup', [InitialSetupController::class, 'store'])->name('setup.store');
});

Route::middleware(['auth'])->group(function () {
    Route::post('settings/avatar', [UserAvatarController::class, 'update'])->name('profile.avatar.update');
    Route::delete('settings/avatar', [UserAvatarController::class, 'destroy'])->name('profile.avatar.destroy');
    Route::get('password/change-required', [RequiredPasswordController::class, 'edit'])->name('password.change-required.edit');
    Route::put('password/change-required', [RequiredPasswordController::class, 'update'])->name('password.change-required.update');
});

Route::middleware(['auth', 'verified', 'password.changed', 'active.member'])->group(function () {
    Route::get('community/create', [CommunityController::class, 'create'])->name('community.create');
    Route::post('community', [CommunityController::class, 'store'])->middleware('throttle:10,1')->name('community.store');
    Route::get('community/{thread}/edit', [CommunityController::class, 'edit'])->whereUlid('thread')->name('community.edit');
    Route::put('community/{thread}', [CommunityController::class, 'update'])->whereUlid('thread')->name('community.update');
    Route::delete('community/{thread}', [CommunityController::class, 'destroy'])->whereUlid('thread')->name('community.destroy');
    Route::post('community/{thread}/posts', [CommunityController::class, 'reply'])->whereUlid('thread')->middleware('throttle:10,1')->name('community.reply');
    Route::get('community-posts/{post}/edit', [CommunityController::class, 'editPost'])->whereUlid('post')->name('community-posts.edit');
    Route::put('community-posts/{post}', [CommunityController::class, 'updatePost'])->whereUlid('post')->name('community-posts.update');
    Route::delete('community-posts/{post}', [CommunityController::class, 'deletePost'])->whereUlid('post')->name('community-posts.destroy');
    Route::get('media/{type}/{id}', [MediaController::class, 'edit'])->whereUlid('id')->name('media.edit');
    Route::post('media/{type}/{id}', [MediaController::class, 'store'])->whereUlid('id')->middleware('throttle:10,1')->name('media.store');
    Route::delete('media/{type}/{id}/{kind}/{mediaId}', [MediaController::class, 'destroy'])->whereUlid('id')->whereUlid('mediaId')->name('media.destroy');
    Route::redirect('dashboard', 'my/characters')->name('dashboard');
    Route::get('my/characters', [CharacterController::class, 'mine'])->name('characters.mine');
    Route::get('characters/create', [CharacterController::class, 'create'])->name('characters.create');
    Route::post('characters', [CharacterController::class, 'store'])->name('characters.store');
    Route::get('characters/{character}/edit', [CharacterController::class, 'edit'])->name('characters.edit');
    Route::put('characters/{character}', [CharacterController::class, 'update'])->name('characters.update');
    Route::delete('characters/{character}', [CharacterController::class, 'destroy'])->name('characters.destroy');
    Route::get('my/diaries', [DiaryController::class, 'mine'])->name('diaries.mine');
    Route::get('diaries/create', [DiaryController::class, 'create'])->name('diaries.create');
    Route::post('diaries', [DiaryController::class, 'store'])->middleware('throttle:10,1')->name('diaries.store');
    Route::get('diaries/{diary}/edit', [DiaryController::class, 'edit'])->whereUlid('diary')->name('diaries.edit');
    Route::put('diaries/{diary}', [DiaryController::class, 'update'])->whereUlid('diary')->name('diaries.update');
    Route::delete('diaries/{diary}', [DiaryController::class, 'destroy'])->whereUlid('diary')->name('diaries.destroy');
    Route::post('diaries/{diary}/comments', [DiaryCommentController::class, 'store'])->whereUlid('diary')->middleware('throttle:10,1')->name('diary-comments.store');
    Route::delete('diary-comments/{comment}', [DiaryCommentController::class, 'destroy'])->whereUlid('comment')->name('diary-comments.destroy');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::patch('characters/bulk', [CharacterController::class, 'bulkUpdate'])->name('characters.bulk');
        Route::get('community-categories', [ContentAdminController::class, 'categories'])->name('community-categories');
        Route::get('content', [ContentAdminController::class, 'content'])->name('content');
        Route::delete('content', [ContentAdminController::class, 'deleteContent'])->name('content.destroy');
        Route::post('community-categories', [ContentAdminController::class, 'saveCategory'])->name('community-categories.store');
        Route::put('community-categories/{category}', [ContentAdminController::class, 'saveCategory'])->whereNumber('category')->name('community-categories.update');
        Route::delete('community-categories/{category}', [ContentAdminController::class, 'deleteCategory'])->whereNumber('category')->name('community-categories.destroy');
        Route::get('announcements', [ContentAdminController::class, 'announcements'])->name('announcements');
        Route::post('announcements', [ContentAdminController::class, 'saveAnnouncement'])->name('announcements.store');
        Route::put('announcements/{id}', [ContentAdminController::class, 'saveAnnouncement'])->whereUlid('id')->name('announcements.update');
        Route::delete('announcements/{id}', [ContentAdminController::class, 'deleteAnnouncement'])->whereUlid('id')->name('announcements.destroy');
        Route::get('images', [ContentAdminController::class, 'images'])->name('images');
        Route::post('images', [ContentAdminController::class, 'upload'])->middleware('throttle:10,1')->name('images.store');
        Route::delete('images', [ContentAdminController::class, 'deleteImages'])->name('images.destroy');
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('settings', [AdminController::class, 'settings'])->name('settings');
        Route::put('settings', [AdminController::class, 'updateSettings'])->name('settings.update');
        Route::get('users', [AdminController::class, 'users'])->name('users');
        Route::patch('users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::delete('users/{user}', [AdminController::class, 'deleteUser'])->name('users.destroy');
        Route::get('diary-categories', [AdminController::class, 'diaryCategories'])->name('diary-categories');
        Route::post('diary-categories', [AdminController::class, 'storeDiaryCategory'])->name('diary-categories.store');
        Route::put('diary-categories/{category}', [AdminController::class, 'updateDiaryCategory'])->name('diary-categories.update');
        Route::delete('diary-categories/{category}', [AdminController::class, 'deleteDiaryCategory'])->name('diary-categories.destroy');
        Route::get('feeds', [AdminController::class, 'feeds'])->name('feeds');
        Route::post('feeds', [AdminController::class, 'storeFeed'])->name('feeds.store');
        Route::put('feeds/{feed}', [AdminController::class, 'updateFeed'])->name('feeds.update');
        Route::delete('feeds/{feed}', [AdminController::class, 'deleteFeed'])->name('feeds.destroy');
        Route::get('banners', [AdminController::class, 'banners'])->name('banners');
        Route::post('banners', [AdminController::class, 'storeBanner'])->name('banners.store');
        Route::put('banners/{banner}', [AdminController::class, 'updateBanner'])->name('banners.update');
        Route::delete('banners/{banner}', [AdminController::class, 'deleteBanner'])->name('banners.destroy');
    });
});

require __DIR__.'/settings.php';
