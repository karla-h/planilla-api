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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('termination_reason')->nullable();
            $table->decimal('accounting_salary', 10, 2);
            $table->decimal('real_salary', 10, 2);
            $table->enum('payment_type', ['quincenal', 'mensual'])->default('quincenal');
            $table->enum('status_code', ['active', 'terminated', 'suspended'])->default('active');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
