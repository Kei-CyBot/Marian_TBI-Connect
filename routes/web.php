<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactRegistrationController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\EventEmailController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StartupAssessmentController;
use App\Http\Controllers\StartupController;
use App\Http\Controllers\StartupDriveLinkController;
use App\Http\Controllers\StartupFolderAccessController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TechnicalNeedController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

Route::view('/startup-form', 'public.startup-form')->name('startup-form');
Route::view('/alumni-form', 'public.alumni-form')->name('alumni-form');
Route::get('/contact-registration', [ContactRegistrationController::class, 'create'])->name('contact-registration');
Route::post('/contact-registration', [ContactRegistrationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('contact-registration.store');

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : view('login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', fn () => view('login'))->name('login');

    Route::post('/login', function (Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if (! Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            $request->boolean('remember')
        )) {
            return back()
                ->withErrors(['email' => 'The provided credentials do not match our records.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    })->middleware('throttle:5,1')->name('login.store');

    Route::get('/invites/accept/{token}', [TeamController::class, 'acceptInviteForm'])->name('invites.accept');
    Route::post('/invites/accept/{token}', [TeamController::class, 'processAcceptInvite'])->name('invites.accept.process');

    Route::get('/auth/google/redirect', function () {
        abort_unless(
            class_exists(Socialite::class),
            503,
            'Google sign-in requires the laravel/socialite package and Google OAuth credentials.'
        );
        abort_unless(
            config('services.google.client_id') && config('services.google.client_secret'),
            503,
            'Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET before enabling Google sign-in.'
        );

        return Socialite::driver('google')->redirect();
    })->name('auth.google.redirect');

    Route::get('/auth/google/callback', function (Request $request) {
        abort_unless(
            class_exists(Socialite::class),
            503,
            'Google sign-in requires the laravel/socialite package and Google OAuth credentials.'
        );
        abort_unless(
            config('services.google.client_id') && config('services.google.client_secret'),
            503,
            'Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET before enabling Google sign-in.'
        );

        $googleUser = Socialite::driver('google')->user();
        $email = $googleUser->getEmail();
        $isVerified = filter_var($googleUser->user['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);

        abort_unless($email && $isVerified, 403, 'Google could not verify this account.');

        $user = User::query()->where('email', $email)->first();
        abort_unless($user, 403, 'This Google account is not authorized for Marian TBI Connect.');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    })->name('auth.google.callback');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/form-submissions', [FormSubmissionController::class, 'store'])
        ->middleware('role:admin,super_admin')
        ->name('form-submissions.store');
    Route::get('/form-submissions/{submission}/attachment', [FormSubmissionController::class, 'downloadAttachment'])
        ->name('form-submissions.attachment');
    Route::put('/file-manager/submissions/{submission}', [FormSubmissionController::class, 'update'])
        ->middleware('role:admin,super_admin')
        ->name('form-submissions.update');
    Route::delete('/file-manager/submissions/{submission}', [FormSubmissionController::class, 'destroy'])
        ->middleware('role:admin,super_admin')
        ->name('form-submissions.destroy');
    Route::post('/file-manager/submissions/{submission}/move', [FormSubmissionController::class, 'move'])
        ->middleware('role:admin,super_admin')
        ->name('form-submissions.move');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/incubatees', [StartupAssessmentController::class, 'index'])->name('incubatees');
    Route::post('/incubatees', [StartupController::class, 'store'])->middleware('role:admin,super_admin')->name('incubatees.store');
    Route::put('/incubatees/{startup}', [StartupController::class, 'update'])->middleware('role:admin,super_admin')->name('incubatees.update');
    Route::delete('/incubatees/{startup}', [StartupController::class, 'destroy'])->middleware('role:admin,super_admin')->name('incubatees.destroy');
    Route::post('/startup-assessments', [StartupAssessmentController::class, 'store'])
        ->middleware('role:admin,super_admin')
        ->name('startup-assessments.store');
    Route::post('/startup-drive-links', [StartupDriveLinkController::class, 'store'])
        ->middleware('role:admin,super_admin')
        ->name('startup-drive-links.store');
    Route::delete('/startup-drive-links/{driveLink}', [StartupDriveLinkController::class, 'destroy'])
        ->middleware('role:admin,super_admin')
        ->name('startup-drive-links.destroy');
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts');
    Route::post('/contacts', [ContactController::class, 'store'])->middleware('role:admin,super_admin')->name('contacts.store');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->middleware('role:admin,super_admin')->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->middleware('role:admin,super_admin')->name('contacts.destroy');
    Route::post('/contacts/{contact}/assignments', [ContactController::class, 'assignStartup'])
        ->middleware('role:admin,super_admin')
        ->name('contacts.assignments.store');
    Route::delete('/contacts/{contact}/assignments/{startup}', [ContactController::class, 'removeStartupAssignment'])
        ->middleware('role:admin,super_admin')
        ->name('contacts.assignments.destroy');
    Route::get('/contact-intake', [ContactRegistrationController::class, 'index'])->name('contact-intake');
    Route::patch('/contact-intake/{application}/verify', [ContactRegistrationController::class, 'verify'])
        ->middleware('role:admin,super_admin')
        ->name('contact-intake.verify');
    Route::post('/contact-intake/{application}/provision', [ContactRegistrationController::class, 'approveAndProvision'])
        ->middleware('role:admin,super_admin')
        ->name('contact-intake.provision');
    Route::delete('/contact-intake/{application}', [ContactRegistrationController::class, 'destroy'])
        ->middleware('role:admin,super_admin')
        ->name('contact-intake.destroy');
    Route::get('/files', [FileManagerController::class, 'index'])->name('files');
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/exports/form-submissions', [ExportController::class, 'exportSubmissions'])->middleware('role:admin,super_admin')->name('exports.form-submissions');
    Route::get('/exports/technical-needs', [ExportController::class, 'exportTechnicalNeeds'])->middleware('role:admin,super_admin')->name('exports.technical-needs');
    Route::get('/google-drive/connect', [GoogleDriveController::class, 'connect'])->middleware('role:admin,super_admin')->name('google-drive.connect');
    Route::get('/google-drive/callback', [GoogleDriveController::class, 'callback'])->middleware('role:admin,super_admin')->name('google-drive.callback');
    Route::post('/google-drive/sync', [GoogleDriveController::class, 'synchronize'])->middleware('role:admin,super_admin')->name('google-drive.sync');
    Route::post('/startup-folders/{startup}/access', [StartupFolderAccessController::class, 'update'])
        ->middleware('role:admin,super_admin')
        ->name('startup-folders.access.update');
    Route::get('/technical-needs', [TechnicalNeedController::class, 'index'])->name('technical-needs');
    Route::get('/technical-needs/{technicalNeed}/approval', [TechnicalNeedController::class, 'downloadApproval'])
        ->name('technical-needs.approval');
    Route::post('/technical-needs', [TechnicalNeedController::class, 'store'])->middleware('role:admin,super_admin')->name('technical-needs.store');
    Route::put('/technical-needs/{technicalNeed}', [TechnicalNeedController::class, 'update'])->middleware('role:admin,super_admin')->name('technical-needs.update');
    Route::delete('/technical-needs/{technicalNeed}', [TechnicalNeedController::class, 'destroy'])->middleware('role:admin,super_admin')->name('technical-needs.destroy');
    Route::get('/events', [EventsController::class, 'index'])->name('events');
    Route::post('/event-emails', [EventEmailController::class, 'send'])->middleware('role:admin,super_admin')->name('event-emails.send');
    Route::get('/team', [TeamController::class, 'index'])->name('team');
    Route::put('/team/{user}/role', [TeamController::class, 'updateRole'])->middleware('role:super_admin')->name('team.roles.update');
    Route::post('/team/invite', [TeamController::class, 'invite'])->middleware('role:admin,super_admin')->name('team.invite');
    Route::delete('/team/invitations/{invitation}', [TeamController::class, 'revokeInvite'])->middleware('role:admin,super_admin')->name('team.invitations.revoke');
    Route::get('/archives', [ArchiveController::class, 'index'])->name('archives');
    Route::post('/archives/startups/{startup}/restore', [ArchiveController::class, 'restoreStartup'])->middleware('role:admin,super_admin')->name('archives.startups.restore');
    Route::delete('/archives/startups/{startup}', [ArchiveController::class, 'destroyStartup'])->middleware('role:admin,super_admin')->name('archives.startups.destroy');
    Route::post('/archives/contacts/{contact}/restore', [ArchiveController::class, 'restoreContact'])->middleware('role:admin,super_admin')->name('archives.contacts.restore');
    Route::delete('/archives/contacts/{contact}', [ArchiveController::class, 'destroyContact'])->middleware('role:admin,super_admin')->name('archives.contacts.destroy');
});
