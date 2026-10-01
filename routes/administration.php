<?php

use App\Http\Controllers\Admin\ArchiveController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\PermissionMatrixController;
use App\Http\Controllers\Admin\PrivacyGovernanceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Privacy\DsarDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('audit-trail/suggestions', [AuditLogController::class, 'suggestions'])->name('audit-logs.suggestions');
Route::get('audit-trail', [AuditLogController::class, 'index'])->name('audit-logs.index');
Route::get('audit-trail/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

Route::get('users', [UserController::class, 'index'])->name('users.index');
Route::get('users/create', [UserController::class, 'create'])->name('users.create');
Route::post('users', [UserController::class, 'store'])->name('users.store');
Route::post('users/confirm-password', [UserController::class, 'confirmPassword'])->name('users.confirm-password');
Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
Route::patch('users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
Route::patch('users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
Route::post('users/{user}/verification-notification', [UserController::class, 'resendVerification'])
    ->middleware('throttle:6,1')
    ->name('users.verification.send');
Route::post('users/{user}/archive', [ArchiveController::class, 'archiveUser'])->name('users.archive');
Route::post('users/{user}/unarchive', [ArchiveController::class, 'unarchiveUser'])->name('users.unarchive');

Route::get('archive', [ArchiveController::class, 'index'])->name('archive.index');
Route::post('archive/items/{item}/archive', [ArchiveController::class, 'archiveItem'])->name('archive.items.archive');
Route::post('archive/items/{item}/unarchive', [ArchiveController::class, 'unarchiveItem'])->name('archive.items.unarchive');
Route::post('archive/suppliers/{supplier}/archive', [ArchiveController::class, 'archiveSupplier'])->name('archive.suppliers.archive');
Route::post('archive/suppliers/{supplier}/unarchive', [ArchiveController::class, 'unarchiveSupplier'])->name('archive.suppliers.unarchive');
Route::post('archive/users/{user}/archive', [ArchiveController::class, 'archiveUser'])->name('archive.users.archive');
Route::post('archive/users/{user}/unarchive', [ArchiveController::class, 'unarchiveUser'])->name('archive.users.unarchive');

Route::get('permissions', [PermissionMatrixController::class, 'index'])->name('permissions');

Route::get('privacy', [PrivacyGovernanceController::class, 'index'])->name('privacy.index');
Route::post('privacy/requests/{privacyRequest}/approve', [PrivacyGovernanceController::class, 'approveRequest'])->name('privacy.requests.approve');
Route::post('privacy/requests/{privacyRequest}/under-review', [PrivacyGovernanceController::class, 'markUnderReview'])->name('privacy.requests.under-review');
Route::post('privacy/requests/{privacyRequest}/fulfill', [PrivacyGovernanceController::class, 'fulfillRequest'])->name('privacy.requests.fulfill');
Route::post('privacy/requests/{privacyRequest}/reject', [PrivacyGovernanceController::class, 'rejectRequest'])->name('privacy.requests.reject');
Route::post('privacy/requests/{privacyRequest}/regenerate', [PrivacyGovernanceController::class, 'regeneratePackage'])->name('privacy.requests.regenerate');
Route::get('privacy/requests/{privacyRequest}/export', [PrivacyGovernanceController::class, 'exportUserData'])->name('privacy.requests.export');
Route::get('privacy/requests/{privacyRequest}/download', [DsarDownloadController::class, 'download'])->name('privacy.requests.download-package');
Route::post('privacy/incidents', [PrivacyGovernanceController::class, 'storeIncident'])->name('privacy.incidents.store');
Route::put('privacy/incidents/{incident}', [PrivacyGovernanceController::class, 'updateIncident'])->name('privacy.incidents.update');
Route::post('privacy/retention/sweep', [PrivacyGovernanceController::class, 'sweepRetention'])->name('privacy.retention.sweep');
