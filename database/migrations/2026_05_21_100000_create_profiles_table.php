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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->text('bio')->nullable();
            $table->string('image_url', 1000)->default('https://www.google.com/url?sa=t&source=web&rct=j&url=https%3A%2F%2Fwww.magnific.com%2Ffree-photos-vectors%2Fplaceholder&ved=0CBcQjRxqFwoTCPDg1uKyyZQDFQAAAAAdAAAAABA8&opi=89978449');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
