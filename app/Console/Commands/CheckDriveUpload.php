<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CheckDriveUpload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:drive {user_id? : The ID of the user to test with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test local storage write and Google Drive upload functionality.';

    /**
     * Execute the console command.
     */
    public function handle(GoogleDriveService $driveService): int
    {
        $this->info('=== Starting Drive Upload Check ===');

        // 1. Check local disk write access to storage/app/public
        $publicStoragePath = storage_path('app/public');
        $this->info("1. Check local disk write access to: {$publicStoragePath}");
        if (is_dir($publicStoragePath) && is_writable($publicStoragePath)) {
            $this->info('   [OK] Directory exists and is writable');
        } elseif (is_dir($publicStoragePath)) {
            $this->error('   [FAIL] Directory exists but is not writable');
        } else {
            $this->error('   [FAIL] Directory does not exist');
            try {
                mkdir($publicStoragePath, 0755, true);
                $this->info('   [OK] Created directory');
            } catch (\Throwable $e) {
                $this->error('   [FAIL] Could not create directory: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }

        // 2. Write a local dummy file test-upload.txt
        $this->info("2. Write a local dummy file (test-upload.txt)");
        try {
            $result = Storage::disk('public')->put('test-upload.txt', 'Test content');
            if ($result) {
                $this->info("   [OK] File written successfully: test-upload.txt");
                $this->info("   [INFO] Full path: " . Storage::disk('public')->path('test-upload.txt'));
            } else {
                $this->error('   [FAIL] put() returned false');
                return Command::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('   [FAIL] Local write failed: ' . $e->getMessage());
            $this->error('   [TRACE] ' . $e->getTraceAsString());
            return Command::FAILURE;
        }

        // 3. Check if ANY user has google_refresh_token. Pick the first authenticated user found.
        $this->info("3. Check for users with google_refresh_token");
        $userId = $this->argument('user_id');
        $user = $userId ? User::find($userId) : User::query()->whereNotNull('google_refresh_token')->first();

        if (! $user) {
            $this->warn('   [WARN] No user with google_refresh_token found. Pass user_id as argument to test upload.');
            $this->info('=== Check Complete ===');
            return Command::SUCCESS;
        }

        $this->info("   [OK] Found user: {$user->email} (ID: {$user->id})");

        // 4. Call GoogleDriveService to attempt a real test file push to Google Drive and output the created Google Drive File ID.
        $this->info("4. Attempt test file push to Google Drive via GoogleDriveService");
        try {
            $filePath = Storage::disk('public')->path('test-upload.txt');
            $uploadedFile = new UploadedFile(
                $filePath,
                'test-upload.txt',
                'text/plain',
                null,
                true
            );

            if (method_exists($driveService, 'uploadFile')) {
                $driveResponse = $driveService->uploadFile($user, null, $uploadedFile);
                $this->info('   [OK] Drive upload successful');
                if (is_array($driveResponse)) {
                    if (isset($driveResponse['id'])) {
                        $this->info('   [INFO] Google Drive File ID: ' . $driveResponse['id']);
                    }
                    if (isset($driveResponse['webViewLink'])) {
                        $this->info('   [INFO] Web View Link: ' . $driveResponse['webViewLink']);
                    }
                    $this->info('   [INFO] Full Response: ' . json_encode($driveResponse));
                } else {
                    $this->info('   [INFO] Response: ' . print_r($driveResponse, true));
                }
            } else {
                $this->error('   [FAIL] uploadFile method not found in GoogleDriveService');
            }
        } catch (\Throwable $e) {
            $this->error('   [FAIL] Drive upload failed: ' . $e->getMessage());
            $this->error('   [TRACE] ' . $e->getTraceAsString());
        }

        $this->info('=== Check Complete ===');
        return Command::SUCCESS;
    }
}
