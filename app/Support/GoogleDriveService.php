<?php

namespace App\Support;

use App\Models\FormSubmission;
use App\Models\GoogleDriveConnection;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GoogleDriveService
{
    private const API_URL = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';

    public function isConnected(): bool
    {
        return GoogleDriveConnection::query()->where('singleton_key', 'marian-tbi')->exists();
    }

    public function connection(): ?GoogleDriveConnection
    {
        return GoogleDriveConnection::query()->where('singleton_key', 'marian-tbi')->first();
    }

    public function ensureStartupFolder(Startup $startup): array
    {
        if ($startup->google_drive_folder_id && $startup->google_drive_folder_url) {
            return [
                'id' => $startup->google_drive_folder_id,
                'url' => $startup->google_drive_folder_url,
            ];
        }

        $metadata = [
            'name' => 'Marian TBI - '.$startup->name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$this->ensureRootFolder()['id']],
        ];

        $result = Http::withToken($this->accessToken())
            ->post(self::API_URL.'/files?fields=id,name,webViewLink', $metadata)
            ->throw()
            ->json();
        $folderId = $result['id'] ?? null;
        if (! is_string($folderId) || $folderId === '') {
            throw new RuntimeException('Google Drive did not return an ID for the startup folder.');
        }

        $folderUrl = $result['webViewLink'] ?? 'https://drive.google.com/drive/folders/'.$folderId;
        $startup->forceFill([
            'google_drive_folder_id' => $folderId,
            'google_drive_folder_url' => $folderUrl,
        ])->save();

        return ['id' => $folderId, 'url' => $folderUrl];
    }

    public function syncPermissions(Startup $startup): void
    {
        $folder = $this->ensureStartupFolder($startup);
        $permissions = Http::withToken($this->accessToken())
            ->get(self::API_URL.'/files/'.rawurlencode($folder['id']).'/permissions', [
                'fields' => 'permissions(id,emailAddress,role,type)',
                'supportsAllDrives' => 'true',
            ])
            ->throw()
            ->json('permissions', []);
        $drivePermissions = collect($permissions)
            ->filter(static fn (array $permission): bool => ($permission['type'] ?? null) === 'user')
            ->keyBy(static fn (array $permission): string => mb_strtolower($permission['emailAddress'] ?? ''));

        $allowedUsers = $startup->folder_access_restricted
            ? $startup->folderAccessUsers()->get()
            : User::query()->get();
        if ($startup->folder_access_manager_id) {
            $manager = User::query()->find($startup->folder_access_manager_id);
            if ($manager && ! $allowedUsers->contains('id', $manager->id)) {
                $allowedUsers->push($manager);
            }
        }
        $allowedUsers = $allowedUsers
            ->reject(fn (User $user): bool => strcasecmp($user->email, $this->connection()->google_email) === 0)
            ->keyBy('id');

        $managedPermissions = DB::table('startup_folder_drive_permissions')
            ->where('startup_id', $startup->id)
            ->get()
            ->keyBy('user_id');

        foreach ($managedPermissions as $userId => $managedPermission) {
            if ($allowedUsers->has($userId)) {
                continue;
            }

            Http::withToken($this->accessToken())
                ->delete(self::API_URL.'/files/'.rawurlencode($folder['id']).'/permissions/'.rawurlencode($managedPermission->google_permission_id).'?supportsAllDrives=true')
                ->throw();
            DB::table('startup_folder_drive_permissions')
                ->where('startup_id', $startup->id)
                ->where('user_id', $userId)
                ->delete();
        }

        foreach ($allowedUsers as $userId => $user) {
            $existingPermission = $drivePermissions->get(mb_strtolower($user->email));
            $managedPermission = $managedPermissions->get($userId);
            if ($managedPermission && ($existingPermission['id'] ?? null) === $managedPermission->google_permission_id) {
                continue;
            }

            if ($managedPermission) {
                DB::table('startup_folder_drive_permissions')
                    ->where('startup_id', $startup->id)
                    ->where('user_id', $userId)
                    ->delete();
            }

            $permissionId = $existingPermission['id'] ?? null;
            if (! is_string($permissionId) || $permissionId === '') {
                $created = Http::withToken($this->accessToken())
                    ->post(self::API_URL.'/files/'.rawurlencode($folder['id']).'/permissions?sendNotificationEmail=false&fields=id', [
                        'type' => 'user',
                        'role' => 'reader',
                        'emailAddress' => $user->email,
                    ])
                    ->throw()
                    ->json();
                $permissionId = $created['id'] ?? null;
            }

            if (! is_string($permissionId) || $permissionId === '') {
                throw new RuntimeException('Google Drive did not return an ID for a folder permission.');
            }

            DB::table('startup_folder_drive_permissions')->updateOrInsert(
                ['startup_id' => $startup->id, 'user_id' => $userId],
                [
                    'google_permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function syncSubmission(FormSubmission $submission): void
    {
        $startup = Startup::withTrashed()->where('name', $submission->startup_name)->first();
        if (! $startup) {
            throw new RuntimeException('The incubatee folder for this submitted form could not be found.');
        }

        $folder = $this->ensureStartupFolder($startup);
        $this->syncPermissions($startup);
        $this->syncSubmissionFiles($submission, $folder['id']);
    }

    public function syncAll(): array
    {
        $startups = Startup::query()->orderBy('id')->get();
        foreach ($startups as $startup) {
            $this->ensureStartupFolder($startup);
            $this->syncPermissions($startup);
        }

        $syncedSubmissions = 0;
        foreach (FormSubmission::query()->latest('id')->get() as $submission) {
            $startup = $startups->firstWhere('name', $submission->startup_name);
            if (! $startup) {
                continue;
            }
            $this->syncSubmissionFiles($submission, $startup->google_drive_folder_id);
            $syncedSubmissions++;
        }

        return ['folders' => $startups->count(), 'forms' => $syncedSubmissions];
    }

    private function syncSubmissionFiles(FormSubmission $submission, string $folderId): void
    {
        $responseData = json_encode([
            'form' => $submission->form_name,
            'incubatee' => $submission->startup_name,
            'track' => $submission->track,
            'submitted_at' => $submission->created_at?->toIso8601String(),
            'review_status' => $submission->approval_status->value,
            'response' => $submission->data,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $response = $this->uploadContent(
            'form-'.$submission->getKey().'-response.json',
            'application/json',
            $responseData,
            $folderId,
            $submission->google_drive_response_file_id
        );

        $updates = [
            'google_drive_response_file_id' => $response['id'],
            'google_drive_response_url' => $response['webViewLink'],
            'drive_synced_at' => now(),
        ];

        if ($submission->prerequisite_path) {
            $disk = Storage::disk('local');
            if (! $disk->exists($submission->prerequisite_path)) {
                throw new RuntimeException('A submitted form attachment is missing from local storage.');
            }

            $name = $submission->prerequisite_original_name
                ? basename($submission->prerequisite_original_name)
                : 'form-'.$submission->getKey().'-attachment';
            $attachment = $this->uploadContent(
                $name,
                $submission->prerequisite_mime_type ?? 'application/octet-stream',
                $disk->get($submission->prerequisite_path),
                $folderId,
                $submission->google_drive_attachment_file_id
            );
            $updates['google_drive_attachment_file_id'] = $attachment['id'];
            $updates['google_drive_attachment_url'] = $attachment['webViewLink'];
        }

        $submission->forceFill($updates)->save();
    }

    private function uploadContent(
        string $name,
        string $mimeType,
        string $contents,
        string $folderId,
        ?string $existingFileId
    ): array {
        $metadata = ['name' => $name];
        if (! $existingFileId) {
            $metadata['parents'] = [$folderId];
        }
        $url = self::UPLOAD_URL.($existingFileId
            ? '/'.rawurlencode($existingFileId).'?uploadType=multipart&fields=id,name,webViewLink&supportsAllDrives=true'
            : '?uploadType=multipart&fields=id,name,webViewLink&supportsAllDrives=true');

        $request = Http::withToken($this->accessToken())
            ->attach('metadata', json_encode($metadata, JSON_THROW_ON_ERROR), 'metadata.json', ['Content-Type' => 'application/json'])
            ->attach('file', $contents, $name, ['Content-Type' => $mimeType]);
        $result = ($existingFileId ? $request->patch($url) : $request->post($url))
            ->throw()
            ->json();

        $fileId = $result['id'] ?? null;
        if (! is_string($fileId) || $fileId === '') {
            throw new RuntimeException('Google Drive did not return an ID for a synchronized file.');
        }

        return [
            'id' => $fileId,
            'webViewLink' => $result['webViewLink'] ?? 'https://drive.google.com/open?id='.$fileId,
        ];
    }

    private function ensureRootFolder(): array
    {
        $connection = $this->connection();
        if (! $connection) {
            throw new RuntimeException('Google Drive has not been connected.');
        }
        if ($connection->root_folder_id && $connection->root_folder_url) {
            return ['id' => $connection->root_folder_id, 'url' => $connection->root_folder_url];
        }

        $result = Http::withToken($this->accessToken())
            ->post(self::API_URL.'/files?fields=id,name,webViewLink', [
                'name' => 'Marian TBI Connect',
                'mimeType' => 'application/vnd.google-apps.folder',
            ])
            ->throw()
            ->json();
        $folderId = $result['id'] ?? null;
        if (! is_string($folderId) || $folderId === '') {
            throw new RuntimeException('Google Drive did not return an ID for the Marian TBI root folder.');
        }

        $folderUrl = $result['webViewLink'] ?? 'https://drive.google.com/drive/folders/'.$folderId;
        $connection->forceFill([
            'root_folder_id' => $folderId,
            'root_folder_url' => $folderUrl,
        ])->save();

        return ['id' => $folderId, 'url' => $folderUrl];
    }

    private function accessToken(): string
    {
        $connection = $this->connection();
        if (! $connection) {
            throw new RuntimeException('Google Drive has not been connected.');
        }

        if ($connection->expires_at && $connection->expires_at->lessThanOrEqualTo(now()->addMinute())) {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
            ])->throw()->json();
            $token = $response['access_token'] ?? null;
            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Google did not return a refreshed Drive access token.');
            }

            $connection->forceFill([
                'access_token' => $token,
                'expires_at' => now()->addSeconds((int) ($response['expires_in'] ?? 3600)),
            ])->save();
        }

        return $connection->access_token;
    }
}
