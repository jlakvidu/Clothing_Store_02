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
        Schema::create('products', function (Blueprint $table) {
            $table->id('id');
            // $table->foreignId('inventory_id')->constrained('inventories')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('admin_id')->constrained('admins');
            $table->decimal('profit')->onDelete('cascade')->default(0);
            $table->string('size')->nullable();
            $table->string('color')->nullable();
            $table->decimal('seller_price', 10, 2)->default(0);
            $table->decimal('discount', 5, 2);
            $table->text('description');
            $table->string('category');
            $table->integer('quantity')->default(0);
            $table->string('location')->nullable();
            $table->string('status');
            $table->integer('added_stock_amount')->default(0);
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('brand_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};