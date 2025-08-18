<?php

namespace ShakewellAgency\PassKitLaravel\Database\Seeders;

use Illuminate\Database\Seeder;
use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;
use ShakewellAgency\PassKitLaravel\Models\PassKitTier;
use ShakewellAgency\PassKitLaravel\Models\CardTemplate;
use ShakewellAgency\PassKitLaravel\Models\PassKitMember;
use ShakewellAgency\PassKitLaravel\Models\WalletPass;
use ShakewellAgency\PassKitLaravel\Models\PassKitTransaction;

class PassKitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample programs
        $membershipProgram = PassKitProgram::create([
            'passkit_id' => 'sample_membership_program',
            'name' => 'VIP Membership Program',
            'description' => 'Exclusive membership program with rewards and benefits',
            'program_type' => 'membership',
            'status' => 'active',
            'account_id' => 1,
            'metadata' => [
                'created_by' => 'seeder',
                'sample_data' => true,
            ],
        ]);

        $eventProgram = PassKitProgram::create([
            'passkit_id' => 'sample_event_program',
            'name' => 'Conference Event Tickets',
            'description' => 'Digital tickets for conference events',
            'program_type' => 'event_ticket',
            'status' => 'active',
            'account_id' => 1,
            'metadata' => [
                'created_by' => 'seeder',
                'sample_data' => true,
            ],
        ]);

        $couponProgram = PassKitProgram::create([
            'passkit_id' => 'sample_coupon_program',
            'name' => 'Discount Coupons',
            'description' => 'Digital discount coupons and offers',
            'program_type' => 'coupon',
            'status' => 'active',
            'account_id' => 1,
            'metadata' => [
                'created_by' => 'seeder',
                'sample_data' => true,
            ],
        ]);

        // Create sample tiers for membership program
        $bronzeTier = PassKitTier::create([
            'passkit_id' => 'bronze_tier',
            'name' => 'Bronze',
            'description' => 'Entry level membership',
            'program_id' => $membershipProgram->id,
            'metadata' => [
                'benefits' => ['5% discount', 'Birthday bonus'],
                'required_points' => 0,
            ],
        ]);

        $silverTier = PassKitTier::create([
            'passkit_id' => 'silver_tier',
            'name' => 'Silver',
            'description' => 'Mid-level membership with enhanced benefits',
            'program_id' => $membershipProgram->id,
            'metadata' => [
                'benefits' => ['10% discount', 'Priority support', 'Birthday bonus'],
                'required_points' => 1000,
            ],
        ]);

        $goldTier = PassKitTier::create([
            'passkit_id' => 'gold_tier',
            'name' => 'Gold',
            'description' => 'Premium membership with exclusive benefits',
            'program_id' => $membershipProgram->id,
            'metadata' => [
                'benefits' => ['15% discount', 'VIP support', 'Exclusive offers', 'Birthday bonus'],
                'required_points' => 5000,
            ],
        ]);

        // Create sample templates
        $membershipTemplate = CardTemplate::create([
            'passkit_template_id' => 'membership_template_001',
            'name' => 'Standard Membership Card',
            'description' => 'Default template for membership cards',
            'template_type' => 'membership',
            'account_id' => 1,
            'is_active' => true,
            'template_data' => [
                'backgroundColor' => '#1f2937',
                'foregroundColor' => '#ffffff',
                'labelColor' => '#9ca3af',
            ],
            'field_definitions' => [
                'primary_fields' => [
                    ['key' => 'member_name', 'label' => 'Member', 'value' => '{{first_name}} {{last_name}}'],
                    ['key' => 'tier', 'label' => 'Tier', 'value' => '{{tier_name}}'],
                ],
                'secondary_fields' => [
                    ['key' => 'points', 'label' => 'Points', 'value' => '{{points}}'],
                    ['key' => 'member_since', 'label' => 'Member Since', 'value' => '{{enrolled_date}}'],
                ],
                'auxiliary_fields' => [
                    ['key' => 'expires', 'label' => 'Expires', 'value' => '{{expiry_date}}'],
                ],
                'back_fields' => [
                    ['key' => 'terms', 'label' => 'Terms', 'value' => 'Terms and conditions apply'],
                ],
            ],
            'design_settings' => [
                'logo_text' => 'VIP MEMBERSHIP',
                'strip_image' => null,
                'thumbnail_image' => null,
            ],
        ]);

        $eventTemplate = CardTemplate::create([
            'passkit_template_id' => 'event_template_001',
            'name' => 'Event Ticket Template',
            'description' => 'Template for event tickets',
            'template_type' => 'event_ticket',
            'account_id' => 1,
            'is_active' => true,
            'template_data' => [
                'backgroundColor' => '#dc2626',
                'foregroundColor' => '#ffffff',
                'labelColor' => '#fca5a5',
            ],
            'field_definitions' => [
                'primary_fields' => [
                    ['key' => 'event_name', 'label' => 'Event', 'value' => '{{event_name}}'],
                    ['key' => 'date', 'label' => 'Date', 'value' => '{{event_date}}'],
                ],
                'secondary_fields' => [
                    ['key' => 'time', 'label' => 'Time', 'value' => '{{event_time}}'],
                    ['key' => 'venue', 'label' => 'Venue', 'value' => '{{venue_name}}'],
                ],
                'auxiliary_fields' => [
                    ['key' => 'seat', 'label' => 'Seat', 'value' => '{{seat_number}}'],
                    ['key' => 'gate', 'label' => 'Gate', 'value' => '{{gate}}'],
                ],
            ],
        ]);

        // Create sample members
        $sampleMembers = [
            [
                'passkit_id' => 'member_001',
                'external_id' => 'user_12345',
                'user_id' => 1,
                'account_id' => 1,
                'program_id' => $membershipProgram->id,
                'tier_id' => $bronzeTier->passkit_id,
                'email' => 'john.doe@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone' => '+1234567890',
                'points_balance' => 250,
                'lifetime_points' => 750,
                'status' => 'active',
                'enrolled_at' => now()->subMonths(6),
                'enrollment_channel' => 'app',
            ],
            [
                'passkit_id' => 'member_002',
                'external_id' => 'user_12346',
                'user_id' => 2,
                'account_id' => 1,
                'program_id' => $membershipProgram->id,
                'tier_id' => $silverTier->passkit_id,
                'email' => 'jane.smith@example.com',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'phone' => '+1234567891',
                'points_balance' => 1250,
                'lifetime_points' => 3500,
                'status' => 'active',
                'enrolled_at' => now()->subMonths(12),
                'enrollment_channel' => 'web',
            ],
            [
                'passkit_id' => 'member_003',
                'external_id' => 'user_12347',
                'user_id' => 3,
                'account_id' => 1,
                'program_id' => $membershipProgram->id,
                'tier_id' => $goldTier->passkit_id,
                'email' => 'bob.johnson@example.com',
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'phone' => '+1234567892',
                'points_balance' => 5750,
                'lifetime_points' => 12000,
                'status' => 'active',
                'enrolled_at' => now()->subMonths(24),
                'enrollment_channel' => 'pos',
            ],
        ];

        foreach ($sampleMembers as $memberData) {
            $member = PassKitMember::create($memberData);

            // Create wallet pass for each member
            WalletPass::create([
                'passkit_id' => 'pass_' . $member->passkit_id,
                'member_passkit_id' => $member->passkit_id,
                'user_id' => $member->user_id,
                'account_id' => $member->account_id,
                'template_id' => $membershipTemplate->id,
                'program_id' => $member->program_id,
                'pass_data' => [
                    'points' => $member->points_balance,
                    'tier' => $member->tier_id,
                    'member_name' => $member->full_name,
                    'member_since' => $member->enrolled_at->format('M Y'),
                ],
                'field_values' => [
                    'member_name' => $member->full_name,
                    'tier_name' => PassKitTier::where('passkit_id', $member->tier_id)->first()->name,
                    'points' => number_format($member->points_balance),
                    'enrolled_date' => $member->enrolled_at->format('M Y'),
                ],
                'status' => 'active',
                'is_installed' => fake()->boolean(70),
                'issued_at' => $member->enrolled_at,
                'installed_at' => fake()->boolean(70) ? $member->enrolled_at->addDays(rand(1, 30)) : null,
                'device_type' => fake()->randomElement(['ios', 'android']),
                'device_model' => fake()->randomElement(['iPhone 14', 'iPhone 13', 'Samsung Galaxy S23', 'Pixel 7']),
            ]);

            // Create sample transactions for each member
            $this->createSampleTransactions($member);
        }

        $this->command->info('PassKit sample data created successfully!');
        $this->command->info('- Created ' . PassKitProgram::count() . ' programs');
        $this->command->info('- Created ' . PassKitTier::count() . ' tiers');
        $this->command->info('- Created ' . CardTemplate::count() . ' templates');
        $this->command->info('- Created ' . PassKitMember::count() . ' members');
        $this->command->info('- Created ' . WalletPass::count() . ' wallet passes');
        $this->command->info('- Created ' . PassKitTransaction::count() . ' transactions');
    }

    private function createSampleTransactions(PassKitMember $member): void
    {
        $transactionTypes = [
            ['type' => 'earn', 'points' => 50, 'description' => 'Purchase bonus'],
            ['type' => 'earn', 'points' => 100, 'description' => 'Sign-up bonus'],
            ['type' => 'earn', 'points' => 25, 'description' => 'Review bonus'],
            ['type' => 'burn', 'points' => -75, 'description' => 'Redeemed discount'],
            ['type' => 'earn', 'points' => 200, 'description' => 'Birthday bonus'],
        ];

        $currentBalance = 0;

        foreach (fake()->randomElements($transactionTypes, rand(3, 8)) as $transaction) {
            $pointsAmount = $transaction['points'];
            $balanceBefore = $currentBalance;
            $currentBalance += $pointsAmount;

            PassKitTransaction::create([
                'passkit_transaction_id' => 'txn_' . fake()->uuid(),
                'member_passkit_id' => $member->passkit_id,
                'member_id' => $member->id,
                'account_id' => $member->account_id,
                'transaction_type' => $transaction['type'],
                'points_amount' => $pointsAmount,
                'points_balance_before' => $balanceBefore,
                'points_balance_after' => $currentBalance,
                'description' => $transaction['description'],
                'source' => fake()->randomElement(['app', 'web', 'pos', 'api']),
                'category' => fake()->randomElement(['purchase', 'bonus', 'redemption', 'adjustment']),
                'status' => 'completed',
                'processed_at' => fake()->dateTimeBetween($member->enrolled_at, 'now'),
                'created_at' => fake()->dateTimeBetween($member->enrolled_at, 'now'),
            ]);
        }
    }
}