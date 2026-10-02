<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription packages (الباقات) defined by each center: a named multi-month offer, optionally
 * tied to one course, priced either at a fixed total or as a % discount on the monthly price.
 * An enrollment taken with a package points to it (enrollments.package_id); monthly enrollments
 * have no package and duration_months = 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('duration_months');
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('price')->nullable();            // fixed total price (MAD), or
            $table->unsignedTinyInteger('discount_percent')->default(0); // % off monthly price × months
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('duration_months')->constrained('packages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
        });
        Schema::dropIfExists('packages');
    }
};
