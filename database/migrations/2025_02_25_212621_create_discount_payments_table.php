<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_payments', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 8, 2);
            $table->integer('quantity')->default(1);
            $table->smallInteger('biweek')->nullable();
            $table->smallInteger('pay_card')->default(1);
            $table->foreignId('discount_type_id')->constrained();
            $table->foreignId('pay_roll_id')->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_payments');
    }
};