<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_avatars', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('mime_type', 50);
            $table->timestamps();
        });

        DB::statement(DB::getDriverName() === 'sqlite'
            ? 'ALTER TABLE user_avatars ADD COLUMN content BLOB NOT NULL'
            : 'ALTER TABLE user_avatars ADD COLUMN content MEDIUMBLOB NOT NULL');
    }

    public function down(): void
    {
        DB::table('users')
            ->where('avatar_path', 'like', 'database/%')
            ->update(['avatar_path' => null]);

        Schema::dropIfExists('user_avatars');
    }
};
