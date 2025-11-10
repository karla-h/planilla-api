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
        Schema::create('pay_rolls', function (Blueprint $table) {
            $table->id();
            $table->decimal('accounting_salary', 8, 2);
            $table->decimal('real_salary', 8, 2);
            $table->foreignId('employee_id')->constrained();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->date('period_start');
            $table->date('period_end');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pay_rolls');
    }
};
