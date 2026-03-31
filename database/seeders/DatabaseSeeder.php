<?php
// database/seeders/DatabaseSeeder.php

use App\Models\User;
use App\Models\Department;
use App\Models\Channel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        $superAdmin = User::create([
            'name' => 'Super Admin UNEXE',
            'email' => 'universityexcellenceelite.unexe@gmail.com',
            'password' => Hash::make('Unexe@2025!'),
            'role' => 'super_admin',
            'is_profile_complete' => true,
            'email_verified_at' => now(),
        ]);

        // 5 Départements INSTI Lokossa
        $departments = [
            ['name' => 'Génie Civil', 'slug' => 'GC'],
            ['name' => 'Génie Électrique et Informatique', 'slug' => 'GEI'],
            ['name' => 'Génie Énergétique', 'slug' => 'GE'],
            ['name' => 'Maintenance de Systèmes', 'slug' => 'MS'],
            ['name' => 'Génie Mécanique et Production', 'slug' => 'GMP'],
        ];

        foreach ($departments as $dept) {
            Department::create($dept);
        }

        // Channels par défaut
        // 1. Canal comité interne (chat temps réel)
        Channel::create([
            'name' => 'Comité Interne',
            'type' => 'comite_only',
            'description' => 'Espace privé des membres du comité',
        ]);

        // 2. Canal comité ↔ candidats (forum/annonces)
        Channel::create([
            'name' => 'Annonces & Échanges',
            'type' => 'comite_candidats',
            'description' => 'Annonces officielles et Q&A entre comité et candidats',
        ]);

        // 3. Un canal chat par département
        $departments_list = Department::all();
        foreach ($departments_list as $dept) {
            Channel::create([
                'name' => 'Chat ' . $dept->name,
                'type' => 'department',
                'department_id' => $dept->id,
                'description' => 'Espace d\'échange des candidats du département ' . $dept->name,
            ]);
        }
    }
    
}