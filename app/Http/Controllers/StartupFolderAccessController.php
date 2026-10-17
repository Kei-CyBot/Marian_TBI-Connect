<?php

namespace App\Http\Controllers;

use App\Models\Startup;
use App\Support\GoogleDriveService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;

class StartupFolderAccessController extends Controller
{
    public function update(Request $request, Startup $startup, GoogleDriveService $drive): RedirectResponse
    {
        abort_unless($startup->userCanManageFolderAccess($request->user()), 403);

        $validated = $request->validate([
            'restricted' => ['required', 'boolean'],
            'user_ids' => ['nullable', 'array', 'max:100'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $startup->folder_access_restricted = (bool) $validated['restricted'];
        if ($startup->folder_access_manager_id === null && $startup->folder_access_restricted) {
            $startup->folder_access_manager_id = $request->user()->id;
        }
        if (! $startup->folder_access_restricted) {
            $startup->folder_access_manager_id = null;
            $startup->folderAccessUsers()->detach();
        } else {
            $startup->folderAccessUsers()->syncWithPivotValues(
                array_values(array_unique($validated['user_ids'] ?? [])),
                ['granted_by' => $request->user()->id]
            );
        }
        $startup->save();

        $redirect = to_route('files', [
            'startup' => \Illuminate\Support\Str::slug($startup->name),
            'track' => $startup->track,
        ]);

        if (! $drive->isConnected()) {
            return $redirect
                ->with('status', $startup->folder_access_restricted
                    ? 'App folder access is restricted to the selected team members and the folder manager.'
                    : 'App folder access is available to signed-in team members.')
                ->with('drive_sync_warning', 'Google Drive is not connected yet, so its sharing permissions were not changed.');
        }

        try {
            $drive->syncPermissions($startup);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::error('Folder access was saved in Marian TBI Connect but could not be applied in Google Drive.', [
                'startup_id' => $startup->id,
                'exception' => $exception,
            ]);

            return $redirect
                ->with('status', 'App folder access was saved.')
                ->with('drive_sync_warning', 'Google Drive sharing could not be updated. The app access list is saved; retry after checking the Drive connection.');
        }

        return $redirect->with('status', 'Folder access and Google Drive sharing were updated.');
    }
}
