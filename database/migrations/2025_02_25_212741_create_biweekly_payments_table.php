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
        Schema::create('biweekly_payments', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('biweekly');
            $table->date('biweekly_date');
            $table->decimal('accounting_amount', 8, 2);
            $table->decimal('real_amount', 8, 2);
            $table->decimal('discounts', 8,2)->default(0);
            $table->decimal('additionals', 8,2)->default(0);
            $table->foreignId('pay_roll_id')->constrained();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biweekly_payments');
    }
};
