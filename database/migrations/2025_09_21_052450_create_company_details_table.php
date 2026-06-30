<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_details', function (Blueprint $table) {
            $table->id();

            $table->string('company_name')->nullable();
            $table->string('business_name')->nullable();
            $table->string('company_logo')->nullable();
            $table->string('footer_logo')->nullable();
            $table->string('fav_icon')->nullable();

            $table->longText('about_us')->nullable();
            $table->longText('header_content')->nullable();
            $table->longText('footer_content')->nullable();
            $table->longText('copyright')->nullable();

            $table->string('address1')->nullable();
            $table->string('address2')->nullable();

            $table->string('phone1')->nullable();
            $table->string('phone2')->nullable();
            $table->string('phone3')->nullable();
            $table->string('phone4')->nullable();

            $table->string('whatsapp')->nullable();
            $table->string('email1')->nullable();
            $table->string('email2')->nullable();
            $table->string('website')->nullable();

            $table->string('google_play_link')->nullable();
            $table->string('appstore_link')->nullable();

            $table->string('facebook')->nullable();
            $table->string('twitter')->nullable();
            $table->string('instagram')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('youtube')->nullable();
            $table->string('tiktok')->nullable();

            $table->string('tawkto')->nullable();
            $table->longText('google_map')->nullable();

            $table->longText('privacy_policy')->nullable();
            $table->longText('terms_and_conditions')->nullable();
            $table->longText('refund_policy')->nullable();

            $table->longText('bank_info')->nullable();
            $table->integer('vat_percent')->nullable();
            $table->string('currency')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('company_reg_number')->nullable();
            $table->string('account_number')->nullable();
            $table->string('sort_code')->nullable();
            $table->string('bank')->nullable();

            $table->string('opening_time')->nullable();

            $table->string('google_site_verification')->nullable();
            $table->string('google_analytics')->nullable();
            $table->string('google_tag_manager')->nullable();
            $table->string('facebook_pixel_id')->nullable();

            $table->string('meta_title')->nullable();
            $table->longText('meta_description')->nullable();
            $table->longText('meta_keywords')->nullable();
            $table->string('meta_og_title')->nullable();
            $table->string('meta_og_image')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_details');
    }
};
