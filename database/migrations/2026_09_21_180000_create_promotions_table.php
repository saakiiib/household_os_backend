<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->nullable()->unique();
            $t->string('apple_offer_identifier')->nullable();
            $t->json('eligible_plans')->nullable();
            $t->string('benefit')->nullable();
            $t->string('eligibility')->default('all');
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('redemptions_count')->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(false);
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
