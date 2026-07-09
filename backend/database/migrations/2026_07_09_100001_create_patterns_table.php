<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A "pattern" is a short, teachable rule behind Finnish's hard bits
        // (pronoun reductions, the question clitic, partitive, gradation...).
        Schema::create('patterns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('summary', 500);
            $table->json('examples'); // [{ fi, en, note }]
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patterns');
    }
};
