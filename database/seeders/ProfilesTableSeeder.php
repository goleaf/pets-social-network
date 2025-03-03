<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Profile;

class ProfilesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (DB::table('profiles')->count() === 0) {
            $users = User::all();
            
            foreach ($users as $user) {
                Profile::create([
                    'user_id' => $user->id,
                    'bio' => "Bio for {$user->name}",
                    'avatar' => rand(0, 1) ? "profile_{$user->id}.jpg" : null,
                    'location' => rand(0, 1) ? ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix'][rand(0, 4)] : null,
                ]);
            }
            
            $this->command->info('Profiles seeded successfully.');
        }
    }
}
