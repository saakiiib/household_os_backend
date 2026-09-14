<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_suggestion_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('suggestion_key', 190);
            $table->string('status', 20); // dismissed | accepted
            $table->timestamps();

            $table->unique(['household_id', 'user_id', 'suggestion_key'], 'smart_suggestion_user_key_unique');
            $table->index(['household_id', 'user_id', 'status'], 'smart_suggestion_state_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_suggestion_states');
    }
};
