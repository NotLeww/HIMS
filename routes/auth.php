<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\DeviceApprovalController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\ExpiredPasswordController;
use App\Http\Controllers\Auth\LoginMfaController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\PasswordResetOtpController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Support\AuthenticationPanel;
use Illuminate\Support\Facades\Route;

// This signed endpoint is intentionally outside the auth/guest groups so the
// browser timer can reach it. The controller independently requires an
// authenticated guard whose inactivity deadline has actually elapsed.
Route::get('session/expired', [AuthenticatedSessionController::class, 'expired'])
    ->middleware('signed:relative')
    ->name('session.expired');

// Email links are read-only GET previews. Only the signed POST confirmation
// can approve, trust, or deny a pending sign-in request.
Route::get('device-approval/{approvalRequest}/email/{decision}', [DeviceApprovalController::class, 'reviewEmailDecision'])
    ->where('decision', 'approve-once|approve-trust|deny')
    ->middleware(['signed', 'throttle:20,1'])
    ->name('auth.device-approval.email.review');
Route::post('device-approval/{approvalRequest}/email/{decision}', [DeviceApprovalController::class, 'confirmEmailDecision'])
    ->where('decision', 'approve-once|approve-trust|deny')
    ->middleware(['signed', 'throttle:10,1'])
    ->name('auth.device-approval.email.confirm');

Route::middleware(['guest:web', 'guest:admin', 'guest:super_admin'])->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('login/mfa', [LoginMfaController::class, 'show'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->name('login.mfa');
    Route::post('login/mfa', [LoginMfaController::class, 'verify'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->middleware('throttle:6,1')
        ->name('login.mfa.verify');
    Route::post('login/mfa/resend', [LoginMfaController::class, 'resend'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->middleware('throttle:3,1')
        ->name('login.mfa.resend');
    Route::post('login/mfa/continue', [LoginMfaController::class, 'continueSession'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->middleware('throttle:10,1')
        ->name('login.mfa.continue');
    Route::post('login/mfa/cancel', [LoginMfaController::class, 'cancel'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->name('login.mfa.cancel');

    Route::get('password-expired', [ExpiredPasswordController::class, 'show'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->name('password.expired');
    Route::put('password-expired', [ExpiredPasswordController::class, 'update'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->name('password.expired.update');
    Route::post('password-expired/cancel', [ExpiredPasswordController::class, 'cancel'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->name('password.expired.cancel');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->middleware('throttle:3,1')
        ->name('password.email');

    // Retire the former client-side OTP flow. It did not carry a Laravel
    // broker token, so these compatibility endpoints can never reset data.
    Route::get('reset-password', fn () => redirect()->route('password.request'))
        ->name('password.reset.legacy');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->name('password.store');

    Route::get('reset-password-otp', [PasswordResetOtpController::class, 'show'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->name('password.otp');

    Route::post('reset-password-otp', [PasswordResetOtpController::class, 'verify'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->middleware('throttle:6,1')
        ->name('password.otp.verify');

    // Device approval flow for unauthenticated/pending device (Device B)
    Route::get('device-approval/{approvalRequest}/waiting', [DeviceApprovalController::class, 'waiting'])
        ->name('auth.device-approval.waiting');
    Route::get('device-approval/{approvalRequest}/status', [DeviceApprovalController::class, 'status'])
        ->middleware('throttle:30,1')
        ->name('auth.device-approval.status');
    Route::post('device-approval/{approvalRequest}/claim', [DeviceApprovalController::class, 'claim'])
        ->middleware('throttle:10,1')
        ->name('auth.device-approval.claim');
    Route::get('device-approval/{approvalRequest}/cancel', [DeviceApprovalController::class, 'confirmCancellation'])
        ->middleware('throttle:10,1')
        ->name('auth.device-approval.cancel-confirmation');
    Route::post('device-approval/{approvalRequest}/cancel', [DeviceApprovalController::class, 'cancel'])
        ->middleware('throttle:10,1')
        ->name('auth.device-approval.cancel');
    Route::post('device-approval/{approvalRequest}/resend-email', [DeviceApprovalController::class, 'resendEmailOtp'])
        ->middleware('throttle:3,1')
        ->name('auth.device-approval.resend-email');
});

Route::middleware('auth:web,admin,super_admin')->group(function () {
    // In-app device approval actions by authenticated Device A
    Route::get('device-approvals/pending', [DeviceApprovalController::class, 'checkPending'])
        ->middleware('throttle:30,1')
        ->name('auth.device-approvals.pending');
    Route::post('device-approvals/{approvalRequest}/approve', [DeviceApprovalController::class, 'approve'])
        ->middleware('throttle:10,1')
        ->name('auth.device-approvals.approve');
    Route::post('device-approvals/{approvalRequest}/reject', [DeviceApprovalController::class, 'reject'])
        ->middleware('throttle:10,1')
        ->name('auth.device-approvals.reject');

    // Meaningful browser interaction is synchronized here. The global
    // inactivity middleware updates the authoritative timestamp before this
    // no-content response is returned.
    Route::post('session/activity', fn () => response()->noContent())
        ->name('session.activity');

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
