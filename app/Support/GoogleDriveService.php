<?php

namespace App\Support;

use App\Models\FormSubmission;
use App\Models\GoogleDriveConnection;
use App\Models\Setting;
use App\Models\Startup;
use App\Models\User;
use Google\Client;
use Google\Service\Drive;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GoogleDriveService
{
    private const API_URL = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';

    private const SYSTEM_TOKEN_CACHE_KEY = 'google_drive_system_access_token';

    public function isConnected(?User $user = null): bool
    {
        if ($user && (filled($user->google_access_token) || filled($user->google_refresh_token))) {
            return true;
        }

        if (filled(config('services.google.refresh_token')) || filled(config('services.google.client_id'))) {
            return true;
        }

        return GoogleDriveConnection::query()->where('singleton_key', 'marian-tbi')->exists();
    }

    /**
     * Extract a Google Drive folder ID from a user-provided folder URL.
     */
    public static function parseFolderId(string $url): ?string
    {
        if (preg_match('~drive\.google\.com/drive/folders/([A-Za-z0-9_-]+)~', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public function connection(): ?GoogleDriveConnection
    {
        return GoogleDriveConnection::query()->where('singleton_key', 'marian-tbi')->first();
    }

    public function ensureStartupFolder($startup): array|string
    {
        if (is_string($startup)) {
            $startupName = $startup;
            $folderName = 'Marian TBI - '.$startupName;
            $escapedName = str_replace("'", "\\'", $folderName);

            try {
                // Try to use API client if any user connected
                $user = User::query()->whereNotNull('google_refresh_token')->first();
                if ($user) {
                    $rootId = $this->ensureRootFolder($user);
                    $service = new \Google\Service\Drive($this->getClient($user));
                    $query = "mimeType='application/vnd.google-apps.folder' and name='{$escapedName}' and '{$rootId}' in parents and trashed=false";
                    $results = $service->files->listFiles(['q' => $query, 'fields' => 'files(id,name)']);
                    if (count($results->getFiles()) > 0) {
                        return $results->getFiles()[0]->getId();
                    }
                    $fileMetadata = new \Google\Service\Drive\DriveFile([
                        'name' => $folderName,
                        'mimeType' => 'application/vnd.google-apps.folder',
                        'parents' => [$rootId],
                    ]);
                    $folder = $service->files->create($fileMetadata, ['fields' => 'id']);
                    return $folder->getId();
                }
            } catch (\Throwable $e) {
                Log::warning('Google Drive startup folder lookup via SDK failed; falling back to HTTP.', [
                    'startup' => $startupName,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $rootId = $this->ensureRootFolderLegacy()['id'];
            } catch (\Throwable $e) {
                $rootId = null;
            }

            $query = "mimeType='application/vnd.google-apps.folder' and name='{$escapedName}' and trashed=false";
            if ($rootId) {
                $query .= " and '{$rootId}' in parents";
            }

            $existing = $this->send(fn ($http) => $http->get(self::API_URL.'/files', ['q' => $query, 'fields' => 'files(id,name)']))
                ->throw()
                ->json('files', []);
            if (is_string($existing[0]['id'] ?? null)) {
                return $existing[0]['id'];
            }

            $metadata = [
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
            ];
            if ($rootId) {
                $metadata['parents'] = [$rootId];
            }

            $result = $this->send(fn ($http) => $http->post(self::API_URL.'/files?fields=id,name,webViewLink', $metadata))
                ->throw()
                ->json();
            $folderId = $result['id'] ?? null;
            if (! is_string($folderId) || $folderId === '') {
                throw new RuntimeException('Google Drive did not return an ID for the startup folder.');
            }
            return $folderId;
        }

        if ($startup instanceof Startup) {
            if ($startup->google_drive_folder_id && $startup->google_drive_folder_url) {
                return [
                    'id' => $startup->google_drive_folder_id,
                    'url' => $startup->google_drive_folder_url,
                ];
            }

            try {
                $user = \App\Models\User::query()->whereNotNull('google_refresh_token')->first();
                if ($user) {
                    $rootId = $this->ensureRootFolder($user);
                    $serviceClient = $this->getClient($user);
                    $service = new \Google\Service\Drive($serviceClient);
                    $escapedName = str_replace("'", "\\'", 'Marian TBI - '.$startup->name);
                    $query = "mimeType='application/vnd.google-apps.folder' and name='{$escapedName}' and trashed=false";
                    $results = $service->files->listFiles(['q' => $query, 'fields' => 'files(id,name)']);
                    if (count($results->getFiles()) > 0) {
                        $folderId = $results->getFiles()[0]->getId();
                        $folderUrl = 'https://drive.google.com/drive/folders/'.$folderId;
                        $startup->forceFill([
                            'google_drive_folder_id' => $folderId,
                            'google_drive_folder_url' => $folderUrl,
                        ])->save();
                        return ['id' => $folderId, 'url' => $folderUrl];
                    }
                    $fileMetadata = new \Google\Service\Drive\DriveFile([
                        'name' => 'Marian TBI - '.$startup->name,
                        'mimeType' => 'application/vnd.google-apps.folder',
                        'parents' => [$rootId],
                    ]);
                    $folder = $service->files->create($fileMetadata, ['fields' => 'id']);
                    $folderId = $folder->getId();
                    $folderUrl = 'https://drive.google.com/drive/folders/'.$folderId;
                    $startup->forceFill([
                        'google_drive_folder_id' => $folderId,
                        'google_drive_folder_url' => $folderUrl,
                    ])->save();
                    return ['id' => $folderId, 'url' => $folderUrl];
                }
            } catch (\Throwable $e) {
                // fall through
            }

            $metadata = [
                'name' => 'Marian TBI - '.$startup->name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$this->ensureRootFolderLegacy()['id']],
            ];

            $result = $this->send(fn ($http) => $http->post(self::API_URL.'/files?fields=id,name,webViewLink', $metadata))
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

        throw new \InvalidArgumentException('Invalid argument passed to ensureStartupFolder');
    }

    public function syncPermissions(Startup $startup): void
    {
        $folder = $this->ensureStartupFolder($startup);
        $permissions = $this->send(fn ($http) => $http->get(self::API_URL.'/files/'.rawurlencode($folder['id']).'/permissions', [
            'fields' => 'permissions(id,emailAddress,role,type)',
            'supportsAllDrives' => 'true',
        ]))
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

            $this->send(fn ($http) => $http->delete(self::API_URL.'/files/'.rawurlencode($folder['id']).'/permissions/'.rawurlencode($managedPermission->google_permission_id).'?supportsAllDrives=true'))
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
                $created = $this->send(fn ($http) => $http->post(self::API_URL.'/files/'.rawurlencode($folder['id']).'/permissions?sendNotificationEmail=false&fields=id', [
                    'type' => 'user',
                    'role' => 'reader',
                    'emailAddress' => $user->email,
                ]))
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

    public function syncSubmissionFiles(FormSubmission $submission, string $folderId): void
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

    public function getClient(User $user): \Google\Client
    {
        if (! $user->google_refresh_token && ! $user->google_access_token) {
            throw new \Exception("Google Drive has not been connected for user {$user->email}.");
        }

        $client = new \Google\Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->setScopes([
            \Google\Service\Drive::DRIVE_FILE,
        ]);

        if ($user->google_access_token) {
            $client->setAccessToken($user->google_access_token);
        }

        if ($client->isAccessTokenExpired()) {
            if ($user->google_refresh_token) {
                $newToken = $client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
                if (isset($newToken['access_token'])) {
                    $user->forceFill([
                        'google_access_token' => $newToken['access_token'],
                        'google_token_expires_at' => now()->addSeconds((int) ($newToken['expires_in'] ?? 3600)),
                    ])->save();
                    $client->setAccessToken($newToken);
                } else {
                    throw new \Exception('Failed to refresh Google access token.');
                }
            } else {
                throw new \Exception('Google Drive access token expired and no refresh token available.');
            }
        }

        return $client;
    }

    public function ensureRootFolder(User $user = null)
    {
        if ($user instanceof User) {
            $client = $this->getClient($user);
            $service = new \Google\Service\Drive($client);

            $folderName = 'Marian TBI Documents';
            $query = "mimeType='application/vnd.google-apps.folder' and name='{$folderName}' and trashed=false";
            $results = $service->files->listFiles(['q' => $query, 'fields' => 'files(id,name)']);

            if (count($results->getFiles()) > 0) {
                return $results->getFiles()[0]->getId();
            }

            $fileMetadata = new \Google\Service\Drive\DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
            ]);

            $folder = $service->files->create($fileMetadata, ['fields' => 'id']);
            return $folder->getId();
        }

        // Legacy method behavior
        $connection = $this->connection();
        if (! $connection) {
            throw new RuntimeException('Google Drive has not been connected.');
        }
        if ($connection->root_folder_id && $connection->root_folder_url) {
            return ['id' => $connection->root_folder_id, 'url' => $connection->root_folder_url];
        }

        if ($configuredFolderId = $this->configuredRootFolderId()) {
            $folderUrl = 'https://drive.google.com/drive/folders/'.$configuredFolderId;
            $connection->forceFill([
                'root_folder_id' => $configuredFolderId,
                'root_folder_url' => $folderUrl,
            ])->save();

            return ['id' => $configuredFolderId, 'url' => $folderUrl];
        }

        $result = $this->send(fn ($http) => $http->post(self::API_URL.'/files?fields=id,name,webViewLink', [
            'name' => 'Marian TBI Connect',
            'mimeType' => 'application/vnd.google-apps.folder',
        ]))
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

    public function ensureSubFolder(User $user, string $folderName): string
    {
        $rootId = $this->ensureRootFolder($user);
        $client = $this->getClient($user);
        $service = new \Google\Service\Drive($client);

        $escapedName = str_replace("'", "\\'", $folderName);
        $query = "mimeType='application/vnd.google-apps.folder' and name='{$escapedName}' and '{$rootId}' in parents and trashed=false";
        $results = $service->files->listFiles(['q' => $query, 'fields' => 'files(id,name)']);

        if (count($results->getFiles()) > 0) {
            return $results->getFiles()[0]->getId();
        }

        $fileMetadata = new \Google\Service\Drive\DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$rootId],
        ]);

        $folder = $service->files->create($fileMetadata, ['fields' => 'id']);
        return $folder->getId();
    }

    public function deleteFile(string $fileId): void
    {
        $this->send(fn ($http) => $http->delete(self::API_URL.'/files/'.rawurlencode($fileId).'?'.http_build_query(['supportsAllDrives' => 'true'])))
            ->throw();
    }

    public function moveFile(string $fileId, string $newFolderId, ?string $oldFolderId = null): void
    {
        if (! is_string($oldFolderId) || $oldFolderId === '') {
            try {
                $metadata = $this->send(fn ($http) => $http->get(self::API_URL.'/files/'.rawurlencode($fileId), [
                    'fields' => 'parents',
                    'supportsAllDrives' => 'true',
                ]))
                    ->throw()
                    ->json();
                $oldFolderId = collect($metadata['parents'] ?? [])->first();
            } catch (\Throwable $e) {
                $oldFolderId = null;
            }
        }

        $query = [
            'addParents' => $newFolderId,
            'supportsAllDrives' => 'true',
        ];
        if (is_string($oldFolderId) && $oldFolderId !== '') {
            $query['removeParents'] = $oldFolderId;
        }

        $this->send(fn ($http) => $http->patch(self::API_URL.'/files/'.rawurlencode($fileId).'?'.http_build_query($query), (object) []))
            ->throw();
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

        $result = $this->send(function ($http) use ($metadata, $contents, $name, $mimeType, $url, $existingFileId) {
            $request = $http
                ->attach('metadata', json_encode($metadata, JSON_THROW_ON_ERROR), 'metadata.json', ['Content-Type' => 'application/json'])
                ->attach('file', $contents, $name, ['Content-Type' => $mimeType]);

            return $existingFileId ? $request->patch($url) : $request->post($url);
        })
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

    private function ensureRootFolderLegacy(): array
    {
        $connection = $this->connection();
        if (! $connection) {
            throw new RuntimeException('Google Drive has not been connected.');
        }
        if ($connection->root_folder_id && $connection->root_folder_url) {
            return ['id' => $connection->root_folder_id, 'url' => $connection->root_folder_url];
        }

        if ($configuredFolderId = $this->configuredRootFolderId()) {
            $folderUrl = 'https://drive.google.com/drive/folders/'.$configuredFolderId;
            $connection->forceFill([
                'root_folder_id' => $configuredFolderId,
                'root_folder_url' => $folderUrl,
            ])->save();

            return ['id' => $configuredFolderId, 'url' => $folderUrl];
        }

        $result = $this->send(fn ($http) => $http->post(self::API_URL.'/files?fields=id,name,webViewLink', [
            'name' => 'Marian TBI Connect',
            'mimeType' => 'application/vnd.google-apps.folder',
        ]))
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

    private function configuredRootFolderId(): ?string
    {
        $folderId = Setting::query()->where('key', 'google.drive_folder_id')->value('value')
            ?: config('services.google.folder_id');

        return is_string($folderId) && $folderId !== '' ? $folderId : null;
    }

    public function accessToken(): string
    {
        $user = auth()->user();
        if ($user instanceof User && $this->hasFreshToken($user->google_access_token, $user->google_token_expires_at)) {
            return $user->google_access_token;
        }

        if ($user instanceof User && filled($user->google_refresh_token)) {
            try {
                return $this->refreshUserAccessToken($user);
            } catch (\Throwable $e) {
                Log::warning('User Google token refresh failed; falling back to system credentials.', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $user->update([
                    'google_access_token' => null,
                    'google_refresh_token' => null,
                    'google_token_expires_at' => null,
                ]);

                return $this->systemAccessToken();
            }
        }

        $connection = $this->connection();
        if ($connection) {
            if ($this->hasFreshToken($connection->access_token, $connection->expires_at)) {
                return $connection->access_token;
            }

            if (filled($connection->refresh_token)) {
                return $this->refreshConnectionAccessToken($connection);
            }
        }

        return $this->systemAccessToken();
    }

    private function systemAccessToken(): string
    {
        $refreshToken = config('services.google.refresh_token');
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        if (empty($refreshToken) || empty($clientId) || empty($clientSecret)) {
            throw new RuntimeException('Google Drive system credentials (CLIENT_ID, CLIENT_SECRET, REFRESH_TOKEN) are missing in .env or config.');
        }

        return Cache::remember(
            self::SYSTEM_TOKEN_CACHE_KEY,
            3300,
            fn (): string => $this->exchangeRefreshToken((string) $refreshToken)['access_token']
        );
    }

    public function forgetAccessToken(): void
    {
        Cache::forget(self::SYSTEM_TOKEN_CACHE_KEY);

        $user = auth()->user();
        if ($user instanceof User && filled($user->google_access_token)) {
            $user->forceFill(['google_token_expires_at' => now()->subMinute()])->save();
        }

        $connection = $this->connection();
        if ($connection) {
            $connection->forceFill(['expires_at' => now()->subMinute()])->save();
        }
    }

    private function hasFreshToken(?string $accessToken, $expiresAt): bool
    {
        return filled($accessToken)
            && (! $expiresAt || $expiresAt->isFuture());
    }

    private function refreshUserAccessToken(User $user): string
    {
        $tokens = $this->exchangeRefreshToken((string) $user->google_refresh_token);
        $user->forceFill([
            'google_access_token' => $tokens['access_token'],
            'google_token_expires_at' => now()->addSeconds($tokens['expires_in']),
        ])->save();

        return $tokens['access_token'];
    }

    private function refreshConnectionAccessToken(GoogleDriveConnection $connection): string
    {
        $tokens = $this->exchangeRefreshToken((string) $connection->refresh_token);
        $connection->forceFill([
            'access_token' => $tokens['access_token'],
            'expires_at' => now()->addSeconds($tokens['expires_in']),
        ])->save();

        return $tokens['access_token'];
    }

    /**
     * @return array{access_token: string, expires_in: int}
     */
    private function exchangeRefreshToken(string $refreshToken): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            Log::error('Google OAuth Token Exchange Failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Failed to obtain Google Drive access token: '.($response->json('error_description') ?? $response->body()));
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Google did not return a refreshed Drive access token.');
        }

        return [
            'access_token' => $token,
            'expires_in' => (int) ($response->json('expires_in') ?? 3600),
        ];
    }

    private function send(\Closure $build): Response
    {
        $response = $build(Http::withToken($this->accessToken()));

        if ($response->status() === 401) {
            $this->forgetAccessToken();
            $response = $build(Http::withToken($this->accessToken()));
        }

        return $response;
    }
}
