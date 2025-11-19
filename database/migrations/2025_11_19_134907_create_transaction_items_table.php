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
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade'); // Cascade delete
            $table->foreignId('menu_id')->constrained('menus');
            $table->integer('quantity');
            $table->integer('subtotal');
            $table->timestamps(); // Opsional, di Prisma tidak ada created_at untuk item, tapi bagus ada di Laravel
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
