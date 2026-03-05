<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index('company_id');
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index('company_id');
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();

            $table->index('company_id');
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('break_duration')->default(60);
            $table->jsonb('days_of_week')->default('[1,2,3,4,5]');
            $table->timestamps();

            $table->index('company_id');
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code')->nullable();
            $table->date('hire_date')->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->string('salary_type')->default('hourly');
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('company_id');
            $table->index(['company_id', 'user_id']);
        });

        Schema::create('break_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->integer('max_duration_minutes')->nullable();
            $table->integer('max_per_day')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('company_id');
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('clock_in')->nullable();
            $table->timestamp('clock_out')->nullable();
            $table->decimal('gross_hours', 5, 2)->default(0);
            $table->decimal('break_hours', 5, 2)->default(0);
            $table->decimal('net_hours', 5, 2)->default(0);
            $table->decimal('regular_hours', 5, 2)->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('night_hours', 5, 2)->default(0);
            $table->decimal('sunday_holiday_hours', 5, 2)->default(0);
            $table->string('status')->default('pending');
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('edit_reason')->nullable();
            $table->boolean('pin_verified')->default(false);
            $table->timestamps();

            $table->index('company_id');
            $table->index(['company_id', 'date']);
            $table->index(['company_id', 'employee_id']);
            $table->unique(['employee_id', 'date']);
        });

        Schema::create('breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('break_type_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index(['company_id', 'employee_id']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('date');
            $table->boolean('is_recurring')->default(false);
            $table->string('country')->default('CO');
            $table->timestamps();

            $table->index('company_id');
        });

        Schema::create('surcharge_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->decimal('night_surcharge', 5, 2)->default(35);
            $table->decimal('overtime_day', 5, 2)->default(25);
            $table->decimal('overtime_night', 5, 2)->default(75);
            $table->decimal('sunday_holiday', 5, 2)->default(75);
            $table->decimal('overtime_day_sunday', 5, 2)->default(100);
            $table->decimal('overtime_night_sunday', 5, 2)->default(150);
            $table->decimal('night_sunday', 5, 2)->default(110);
            $table->integer('max_weekly_hours')->default(42);
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surcharge_rules');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('breaks');
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('break_types');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
