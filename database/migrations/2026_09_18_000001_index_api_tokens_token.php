<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tokens are now stored as SHA-256 digests and looked up by index.
        // Older bcrypt-hashed tokens can never match again, so clear them;
        // mobile users simply sign in once more.
        DB::table('api_tokens')->delete();

        Schema::table('api_tokens', fn (Blueprint $table) => $table->index('token'));
    }

    public function down(): void
    {
        Schema::table('api_tokens', fn (Blueprint $table) => $table->dropIndex(['token']));
    }
};
