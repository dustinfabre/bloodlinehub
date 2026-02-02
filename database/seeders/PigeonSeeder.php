<?php

namespace Database\Seeders;

use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class PigeonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $user = User::first();

        if (!$user) {
            $this->command->error('No user found. Please create a user first.');
            return;
        }

        $statuses = ['stock', 'racing', 'breeding', 'injured', 'deceased', 'missing', 'flyaway'];
        $genders = ['male', 'female'];
        $colors = [
            'Blue Bar', 'Blue Check', 'Red Check', 'Red Bar', 'Black', 
            'White', 'Grizzle', 'Mealy', 'Dark Check', 'Splash', 
            'Red', 'Yellow', 'Dun', 'Brown', 'Pied'
        ];
        $bloodlines = [
            'Janssen', 'Van Loon', 'Sion', 'Koopman', 'Stichelbaut',
            'Vandenabeele', 'Aarden', 'Delbar', 'Grondelaers', 'Hofkens',
            'Meulemans', 'Bourges', 'Gaby', 'PEC', 'Warehouse'
        ];

        $this->command->info('Creating 100 pigeons with random data...');

        for ($i = 1; $i <= 100; $i++) {
            $gender = $faker->randomElement($genders);
            $status = $faker->randomElement($statuses);
            $color = $faker->randomElement($colors);
            $bloodline = $faker->randomElement($bloodlines);
            
            // Generate ring number with year prefix
            $year = $faker->numberBetween(2018, 2025);
            $ringNumber = 'USA-' . $year . '-' . str_pad($faker->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT);
            
            // Random hatch date in the last 7 years
            $hatchDate = $faker->dateTimeBetween("-{$year}years", 'now');
            
            // Randomly decide if for sale (30% chance)
            $forSale = (rand(1, 100) <= 30);
            $hidePrice = ($forSale && (rand(1, 100) <= 40));
            
            Pigeon::create([
                'user_id' => $user->id,
                'name' => $faker->optional(0.8)->firstName() . ' ' . $faker->optional(0.6)->lastName(),
                'bloodline' => $bloodline,
                'gender' => $gender,
                'hatch_date' => $hatchDate,
                'status' => $status,
                'color' => $color,
                'ring_number' => $ringNumber,
                'personal_number' => $faker->optional(0.6)->numerify('####'),
                'remarks' => $faker->optional(0.4)->sentence(),
                'notes' => $faker->optional(0.5)->paragraph(),
                'for_sale' => $forSale,
                'sale_price' => $forSale ? $faker->randomFloat(2, 100, 5000) : null,
                'hide_price' => $hidePrice,
                'sale_description' => $forSale ? $faker->optional(0.7)->paragraph() : null,
                // Parent info - some pigeons have parents
                'sire_name' => $faker->optional(0.5)->firstName() . ' ' . $faker->optional(0.3)->lastName(),
                'sire_ring_number' => $faker->optional(0.5)->numerify('USA-####-#####'),
                'sire_color' => $faker->optional(0.5)->randomElement($colors),
                'sire_notes' => $faker->optional(0.2)->sentence(),
                'dam_name' => $faker->optional(0.5)->firstName() . ' ' . $faker->optional(0.3)->lastName(),
                'dam_ring_number' => $faker->optional(0.5)->numerify('USA-####-#####'),
                'dam_color' => $faker->optional(0.5)->randomElement($colors),
                'dam_notes' => $faker->optional(0.2)->sentence(),
            ]);

            if ($i % 10 == 0) {
                $this->command->info("Created $i pigeons...");
            }
        }

        $this->command->info('Successfully created 100 pigeons!');
        
        // Show statistics
        $this->command->newLine();
        $this->command->info('Statistics:');
        foreach ($statuses as $status) {
            $count = Pigeon::where('status', $status)->count();
            $this->command->info("  - $status: $count");
        }
        $this->command->newLine();
        foreach ($genders as $gender) {
            $count = Pigeon::where('gender', $gender)->count();
            $this->command->info("  - $gender: $count");
        }
        $this->command->newLine();
        $forSaleCount = Pigeon::where('for_sale', true)->count();
        $this->command->info("  - For sale: $forSaleCount");
    }
}
