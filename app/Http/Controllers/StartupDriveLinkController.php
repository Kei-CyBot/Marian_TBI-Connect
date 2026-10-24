<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStartupDriveLinkRequest;
use App\Models\StartupDriveLink;
use App\Models\Startup;
use App\Support\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StartupDriveLinkController extends Controller
{
    public function store(StoreStartupDriveLinkRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $startup = Startup::query()->findOrFail($validated['startup_id']);
        abort_unless($startup->userCanAccessFolder($request->user()), 403);

        StartupDriveLink::query()->create([
            ...$validated,
            'startup_name' => $startup->name,
            'user_id' => $request->user()->id,
        ]);

        $folderId = GoogleDriveService::parseFolderId($validated['url']);
        if ($folderId !== null) {
            $startup->forceFill([
                'google_drive_folder_id' => $folderId,
                'google_drive_folder_url' => $validated['url'],
            ])->save();

            return to_route('incubatees')->with(
                'drive_link_status',
                'Google Drive link added to '.$startup->name.' and registered as its custom Drive folder.'
            );
        }

        return to_route('incubatees')->with('drive_link_status', 'Google Drive link added to '.$startup->name.'.');
    }

    public function destroy(Request $request, StartupDriveLink $driveLink): RedirectResponse
    {
        $startup = Startup::withTrashed()->find($driveLink->startup_id);
        abort_unless(
            $startup
                ? $startup->userCanAccessFolder($request->user())
                : $driveLink->user_id === $request->user()->id,
            403
        );

        $startupName = $driveLink->startup_name;
        $driveLink->delete();

        return to_route('incubatees')->with('drive_link_status', 'Google Drive link removed from '.$startupName.'.');
    }
}
