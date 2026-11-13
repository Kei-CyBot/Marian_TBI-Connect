<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoogleDriveConnection;
use App\Models\Setting;
use App\Support\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoogleIntegrationController extends Controller
{
    private const FOLDER_SETTING_KEY = 'google.drive_folder_id';

    public function index(): View
    {
        $connection = GoogleDriveConnection::query()
            ->where('singleton_key', 'marian-tbi')
            ->first();

        $connected = ($connection && filled($connection->refresh_token))
            || filled(config('services.google.refresh_token'));

        return view('admin.settings.google', [
            'connection' => $connection,
            'connected' => $connected,
            'accountEmail' => $connection?->google_email ?: config('services.google.drive_account_email'),
            'tokenExpiresAt' => $connection?->expires_at,
            'folderId' => Setting::query()->where('key', self::FOLDER_SETTING_KEY)->value('value')
                ?: config('services.google.folder_id'),
        ]);
    }

    public function updateFolder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'folder_id' => ['required', 'string', 'max:255'],
        ]);

        Setting::query()->updateOrCreate(
            ['key' => self::FOLDER_SETTING_KEY],
            ['value' => $validated['folder_id']],
        );

        $connection = GoogleDriveConnection::query()
            ->where('singleton_key', 'marian-tbi')
            ->first();

        $connection?->forceFill([
            'root_folder_id' => $validated['folder_id'],
            'root_folder_url' => 'https://drive.google.com/drive/folders/'.$validated['folder_id'],
        ])->save();

        return back()->with('success', 'Google Drive target folder updated successfully.');
    }

    public function createFolder(GoogleDriveService $drive): RedirectResponse
    {
        try {
            $drive->getOrCreateTargetFolderId();
        } catch (\Throwable $exception) {
            $drive->logSyncFailure($exception);

            return back()->with('error', 'Could not create the Google Drive storage folder: '.$exception->getMessage());
        }

        return back()->with('success', 'Google Drive storage folder is configured and ready.');
    }

    public function disconnect(): RedirectResponse
    {
        GoogleDriveConnection::query()->where('singleton_key', 'marian-tbi')->delete();

        return back()->with('success', 'Google Workspace account disconnected.');
    }
}
