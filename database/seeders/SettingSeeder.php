<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Branding
            'company_name' => 'Household OS',
            'support_email' => 'support@householdosapp.com',
            'timezone' => 'Europe/London',
            'currency' => 'GBP',
            'company_address' => 'Milton Keynes, United Kingdom',

            // Contact
            'phone_1' => '',
            'phone_2' => '',
            'website_url' => 'https://householdosapp.com',

            // App Store URLs
            'app_store_url' => 'https://apps.apple.com/app/household-os/id000000000',
            'play_store_url' => 'https://play.google.com/store/apps/details?id=com.mentosoftware.householdos',

            // API URLs
            'sandbox_api_url' => '',
            'live_api_url' => '',

            // Social
            'social_twitter' => '',
            'social_facebook' => '',
            'social_instagram' => '',

            // Email / Footer
            'mail_from_address' => 'app@householdosapp.com',
            'mail_from_name' => 'Household OS',
            'footer_tagline' => 'The operating system for modern family life.',
            'footer_disclaimer' => 'This is an automated service email. Please do not share verification or reset codes with anyone.',
        ];

        Setting::setMany($defaults);
    }
}
