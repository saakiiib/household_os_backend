<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->enum('renewal_type', ['standard', 'vehicle'])->default('standard');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('title');
            $table->string('category')->nullable();
            $table->enum('frequency', ['monthly', 'quarterly', 'annual'])->default('annual');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('reminder_before')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'completed'])->default('pending');
            $table->unsignedBigInteger('parent_renewal_id')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'status']);
            $table->index(['household_id', 'due_date']);
            $table->index('parent_renewal_id');
            $table->index('vehicle_id');
        });

        Schema::create('renewal_vehicle_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renewal_id')->constrained()->cascadeOnDelete();
            $table->enum('service_type', ['mot', 'road_tax', 'insurance', 'annual_service']);
            $table->date('service_date');
            $table->decimal('service_amount', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['renewal_id', 'service_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_vehicle_services');
        Schema::dropIfExists('renewals');
    }
};
