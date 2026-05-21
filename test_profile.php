<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Profile;

try {
    echo "1. Testing user creation...\n";
    $user = User::create([
        'name' => 'Test User ' . uniqid(),
        'email' => 'test_' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ]);
    
    echo "Created user ID: " . $user->id . "\n";
    
    echo "2. Checking auto-created profile...\n";
    $profile = $user->profile;
    if ($profile) {
        echo "Profile exists! Image URL: " . $profile->image_url . "\n";
    } else {
        echo "FAIL: Profile does not exist.\n";
    }
    
    echo "3. Updating profile...\n";
    $profile->update(['bio' => 'A software developer']);
    echo "Updated Bio: " . $user->fresh()->profile->bio . "\n";
    
    echo "4. Deleting test user...\n";
    $user->delete();
    
    echo "Checking profile cascade delete...\n";
    $profileCheck = Profile::find($profile->id);
    if (!$profileCheck) {
        echo "Success: Profile was cascade deleted!\n";
    } else {
        echo "FAIL: Profile still exists after user deletion.\n";
    }
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
