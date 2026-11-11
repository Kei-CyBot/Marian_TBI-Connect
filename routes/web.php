<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\GoogleIntegrationController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactRegistrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventEmailController;
use App\Http\Controllers\EventInvitationController;
use App\Http\Controllers\EventOutreachController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicIntakeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StartupAssessmentController;
use App\Http\Controllers\StartupController;
use App\Http\Controllers\StartupDriveLinkController;
use App\Http\Controllers\StartupFolderAccessController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TechnicalNeedController;
use App\Http\Middleware\EnsurePasswordIsConfigured;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

Route::view('/startup-form', 'public.startup-form')->name('public.startup-form');
Route::post('/startup-form', [PublicIntakeController::class, 'storeStartup'])
    ->middleware('throttle:10,1')
    ->name('startup-form.store');
Route::view('/apply', 'public.startup-form')->name('public.apply');
Route::view('/apply/status', 'public.apply-status')->name('public.apply-status');
Route::view('/alumni-form', 'public.alumni-form')->name('alumni-form');
Route::post('/alumni-form', [PublicIntakeController::class, 'storeAlumni'])
    ->middleware('throttle:10,1')
    ->name('alumni-form.store');
Route::get('/events/invitations/{token}/respond/{status}', [EventInvitationController::class, 'respond'])
    ->name('events.invitations.respond');
Route::get('/contact-registration', [ContactRegistrationController::class, 'create'])->name('contact-registration');
Route::post('/contact-registration', [ContactRegistrationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('contact-registration.store');

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->middleware('throttle:10,1')
    ->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])
    ->middleware('throttle:10,1')
    ->name('google.callback');
Route::get('/auth/magic-login/{token}', [GoogleAuthController::class, 'handleMagicLogin'])
    ->middleware('throttle:5,1')
    ->name('auth.magic-login');

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

        ActivityLogger::log('login', Auth::user()->name.' signed in', Auth::user());

        URL::defaults(['rolePrefix' => Auth::user()->rolePrefix()]);

        return redirect()->intended(route('dashboard'));
    })->middleware('throttle:5,1')->name('login.store');

    Route::get('/invites/accept/{token}', [TeamController::class, 'acceptInviteForm'])->name('invites.accept');
    Route::post('/invites/accept/{token}', [TeamController::class, 'processAcceptInvite'])
        ->middleware('throttle:5,1')
        ->name('invites.accept.process');
});

$legacyPrefixedPaths = [
    'dashboard' => 'dashboard',
    'dashboard/export-csv' => 'dashboard/export-csv',
    'files' => 'files',
    'drive' => 'files',
    'documents' => 'files',
    'profile' => 'profile',
    'technical-needs' => 'technical-needs',
    'events' => 'events',
    'events-list' => 'events',
    'reports' => 'reports',
    'reports/export-csv' => 'reports/export-csv',
    'startups' => 'incubatees',
    'incubatees' => 'incubatees',
    'contacts' => 'contacts',
    'network' => 'contacts',
    'intake' => 'contact-intake',
    'contact-intake' => 'contact-intake',
    'activity-logs' => 'activity-logs',
    'team' => 'team',
    'users' => 'team',
    'archives' => 'archives',
    'archive-vault' => 'archives',
    'settings' => 'settings/google',
    'exports/form-submissions' => 'exports/form-submissions',
    'exports/technical-needs' => 'exports/technical-needs',
    'google-drive/connect' => 'google-drive/connect',
    'google-drive/callback' => 'google-drive/callback',
];

Route::middleware('auth')->group(function () use ($legacyPrefixedPaths): void {
    foreach ($legacyPrefixedPaths as $legacy => $target) {
        Route::get($legacy, function (Request $request) use ($target) {
            $query = $request->getQueryString();

            return redirect()->to('/'.$request->user()->rolePrefix().'/'.$target.($query !== null && $query !== '' ? '?'.$query : ''));
        });
    }
});

Route::prefix('{rolePrefix}')
    ->whereIn('rolePrefix', ['superadmin', 'admin', 'member'])
    ->middleware(['auth', EnsurePasswordIsConfigured::class])
    ->group(function (): void {
        Route::post('/auth/google/disconnect', [GoogleAuthController::class, 'disconnect'])->name('auth.google.disconnect');

        Route::post('/form-submissions', [FormSubmissionController::class, 'store'])
            ->middleware('role:admin,super_admin')
            ->name('form-submissions.store');
        Route::get('/form-submissions/{submission}/attachment', [FormSubmissionController::class, 'downloadAttachment'])
            ->name('form-submissions.attachment');
        Route::put('/file-manager/submissions/{submission}', [FormSubmissionController::class, 'update'])
            ->middleware('role:admin,super_admin')
            ->name('form-submissions.update');
        Route::patch('/form-submissions/{submission}/status', [FormSubmissionController::class, 'updateStatus'])
            ->middleware('role:admin,super_admin')
            ->name('form-submissions.status');
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
        Route::put('/profile/password', [PasswordController::class, 'update'])->name('profile.password.update');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/export-csv', [DashboardController::class, 'exportCsv'])->middleware('role:admin,super_admin')->name('dashboard.export-csv');
        Route::get('/reports', [ReportController::class, 'index'])->middleware('role:admin,super_admin')->name('reports.index');
        Route::get('/reports/export-csv', [ReportController::class, 'exportCsv'])->middleware('role:admin,super_admin')->name('reports.export-csv');
        Route::get('/incubatees', [StartupAssessmentController::class, 'index'])->name('incubatees');
        Route::post('/startup-intake/{submission}/approve', [StartupController::class, 'approveIntake'])
            ->middleware('role:admin,super_admin')
            ->name('startup-intake.approve');
        Route::post('/startup-intake/{submission}/reject', [StartupController::class, 'rejectIntake'])
            ->middleware('role:admin,super_admin')
            ->name('startup-intake.reject');
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
        Route::get('/contacts', [ContactController::class, 'index'])->middleware('role:admin,super_admin')->name('contacts');
        Route::post('/contacts', [ContactController::class, 'store'])->middleware('role:admin,super_admin')->name('contacts.store');
        Route::put('/contacts/{contact}', [ContactController::class, 'update'])->middleware('role:admin,super_admin')->name('contacts.update');
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->middleware('role:admin,super_admin')->name('contacts.destroy');
        Route::patch('/contacts/{contact}/credentials', [ContactController::class, 'updateCredentials'])->middleware('role:super_admin')->name('contacts.credentials.update');
        Route::post('/contacts/{contact}/send-credentials', [ContactController::class, 'sendOneTimeEmail'])->middleware('role:super_admin')->name('contacts.credentials.send');
        Route::post('/contacts/{contact}/assignments', [ContactController::class, 'assignStartup'])
            ->middleware('role:admin,super_admin')
            ->name('contacts.assignments.store');
        Route::delete('/contacts/{contact}/assignments/{startup}', [ContactController::class, 'removeStartupAssignment'])
            ->middleware('role:admin,super_admin')
            ->name('contacts.assignments.destroy');
        Route::get('/contact-intake', [ContactRegistrationController::class, 'index'])->middleware('role:admin,super_admin')->name('contact-intake');
        Route::post('/contact-intake/{application}/approve', [ContactRegistrationController::class, 'approveAndProvision'])
            ->middleware('role:admin,super_admin')
            ->name('contact-intake.approve');
        Route::post('/contact-intake/{application}/provision', [ContactRegistrationController::class, 'approveAndProvision'])
            ->middleware('role:admin,super_admin')
            ->name('contact-intake.provision');
        Route::delete('/contact-intake/{application}', [ContactRegistrationController::class, 'destroy'])
            ->middleware('role:admin,super_admin')
            ->name('contact-intake.destroy');
        Route::get('/files', [FileManagerController::class, 'index'])->name('files');
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->middleware('role:admin,super_admin')->name('activity-logs.index');
        Route::get('/api/activity-logs/stream', [ActivityLogController::class, 'stream'])->middleware('role:admin,super_admin')->name('activity-logs.stream');
        Route::get('/exports/form-submissions', [ExportController::class, 'exportSubmissions'])->middleware('role:admin,super_admin')->name('exports.form-submissions');
        Route::get('/exports/technical-needs', [ExportController::class, 'exportTechnicalNeeds'])->middleware('role:admin,super_admin')->name('exports.technical-needs');
        Route::get('/google-drive/connect', [GoogleDriveController::class, 'connect'])->middleware('role:admin,super_admin')->name('google-drive.connect');
        Route::get('/google-drive/callback', [GoogleDriveController::class, 'callback'])->middleware('role:admin,super_admin')->name('google-drive.callback');
        Route::post('/google-drive/sync', [GoogleDriveController::class, 'synchronize'])->middleware('role:admin,super_admin')->name('google-drive.sync');
        Route::get('/settings/google', [GoogleIntegrationController::class, 'index'])->middleware('role:admin,super_admin')->name('admin.settings.google.index');
        Route::post('/settings/google/folder', [GoogleIntegrationController::class, 'updateFolder'])->middleware('role:admin,super_admin')->name('admin.settings.google.update-folder');
        Route::post('/settings/google/create-folder', [GoogleIntegrationController::class, 'createFolder'])->middleware('role:admin,super_admin')->name('admin.settings.google.create-folder');
        Route::post('/settings/google/disconnect', [GoogleIntegrationController::class, 'disconnect'])->middleware('role:admin,super_admin')->name('admin.settings.google.disconnect');
        Route::post('/startup-folders/{startup}/access', [StartupFolderAccessController::class, 'update'])
            ->middleware('role:admin,super_admin')
            ->name('startup-folders.access.update');
        Route::get('/technical-needs', [TechnicalNeedController::class, 'index'])->name('technical-needs');
        Route::get('/technical-needs/{technicalNeed}/view-document', [TechnicalNeedController::class, 'viewDocument'])
            ->name('technical-needs.view-document');
        Route::get('/technical-needs/{technicalNeed}/attachment', [TechnicalNeedController::class, 'downloadAttachment'])
            ->name('technical-needs.attachment');
        Route::get('/technical-needs/{technicalNeed}/approval', [TechnicalNeedController::class, 'downloadApproval'])
            ->name('technical-needs.approval');
        Route::post('/technical-needs/{technicalNeed}/review', [TechnicalNeedController::class, 'review'])
            ->middleware('role:admin,super_admin')
            ->name('technical-needs.review');
        Route::post('/technical-needs', [TechnicalNeedController::class, 'store'])->middleware('role:admin,super_admin')->name('technical-needs.store');
        Route::put('/technical-needs/{technicalNeed}', [TechnicalNeedController::class, 'update'])->middleware('role:admin,super_admin')->name('technical-needs.update');
        Route::delete('/technical-needs/{technicalNeed}', [TechnicalNeedController::class, 'destroy'])->middleware('role:admin,super_admin')->name('technical-needs.destroy');
        Route::get('/events', [EventsController::class, 'index'])->name('events');
        Route::get('/events/create', [EventsController::class, 'create'])->middleware('role:admin,super_admin')->name('events.create');
        Route::post('/events', [EventsController::class, 'store'])->middleware('role:admin,super_admin')->name('events.store');
        Route::get('/events/{event}', [EventsController::class, 'show'])->name('events.show');
        Route::get('/events/{event}/edit', [EventsController::class, 'edit'])->middleware('role:admin,super_admin')->name('events.edit');
        Route::put('/events/{event}', [EventsController::class, 'update'])->middleware('role:admin,super_admin')->name('events.update');
        Route::post('/events/{event}/complete', [EventsController::class, 'markCompleted'])->middleware('role:admin,super_admin')->name('events.complete');
        Route::post('/events/{event}/cancel', [EventsController::class, 'cancel'])->middleware('role:admin,super_admin')->name('events.cancel');
        Route::post('/events/{event}/archive', [EventsController::class, 'archive'])->middleware('role:admin,super_admin')->name('events.archive');
        Route::post('/events/{event}/restore', [EventsController::class, 'restore'])->middleware('role:admin,super_admin')->name('events.restore');
        Route::get('/events/{event}/post-event-document', [EventsController::class, 'downloadPostEventDocument'])->name('events.post-event-document');
        Route::post('/events/{event}/send-invites', [EventInvitationController::class, 'sendInvites'])
            ->middleware('role:admin,super_admin')
            ->name('events.invitations.send');
        Route::post('/event-emails', [EventEmailController::class, 'send'])->middleware('role:admin,super_admin')->name('event-emails.send');
        Route::post('/event-outreach', [EventOutreachController::class, 'sendCommunication'])->middleware('role:admin,super_admin')->name('event-outreach.send');
        Route::get('/team', [TeamController::class, 'index'])->middleware('role:admin,super_admin')->name('team');
        Route::put('/team/{user}/role', [TeamController::class, 'updateRole'])->middleware('role:super_admin')->name('team.roles.update');
        Route::post('/team/invite', [TeamController::class, 'invite'])->middleware('role:admin,super_admin')->name('team.invite');
        Route::delete('/team/invitations/{invitation}', [TeamController::class, 'revokeInvite'])->middleware('role:admin,super_admin')->name('team.invitations.revoke');
        Route::get('/archives', [ArchiveController::class, 'index'])->middleware('role:admin,super_admin')->name('archives.index');
        Route::post('/archives/startups/{startup}/restore', [ArchiveController::class, 'restoreStartup'])->middleware('role:admin,super_admin')->name('archives.startups.restore');
        Route::delete('/archives/startups/{startup}', [ArchiveController::class, 'destroyStartup'])->middleware('role:admin,super_admin')->name('archives.startups.destroy');
        Route::post('/archives/contacts/{contact}/restore', [ArchiveController::class, 'restoreContact'])->middleware('role:admin,super_admin')->name('archives.contacts.restore');
        Route::delete('/archives/contacts/{contact}', [ArchiveController::class, 'destroyContact'])->middleware('role:admin,super_admin')->name('archives.contacts.destroy');
    });
