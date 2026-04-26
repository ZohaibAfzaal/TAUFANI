<?php

// Bootstrap Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Create or update user
$email = 'test@example.com';
$password = 'password123';

$user = \App\Models\User::where('email', $email)->first();

if ($user) {
    $user->password = \Illuminate\Support\Facades\Hash::make($password);
    $user->email_verified_at = now();
    $user->save();
    echo "User updated: {$email}\n";
} else {
    \App\Models\User::create([
        'name'              => 'Test User',
        'email'             => $email,
        'password'          => \Illuminate\Support\Facades\Hash::make($password),
        'email_verified_at' => now(),
    ]);
    echo "User created: {$email}\n";
}

echo "Password: {$password}\n";
