<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajoute des colonnes scalaires sans supprimer le JSON existant (`preferences`)
        Schema::table('user_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('user_settings', 'tone')) {
                $table->string('tone', 50)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('user_settings', 'style')) {
                $table->string('style', 50)->nullable()->after('tone');
            }
            if (!Schema::hasColumn('user_settings', 'context')) {
                $table->text('context')->nullable()->after('style');
            }
            if (!Schema::hasColumn('user_settings', 'custom_system')) {
                $table->longText('custom_system')->nullable()->after('context');
            }
        });
    }

    public function down(): void
    {
        // Supprime proprement (ordre inverse). Sur SQLite, Laravel reconstruit la table si nécessaire.
        Schema::table('user_settings', function (Blueprint $table) {
            if (Schema::hasColumn('user_settings', 'custom_system')) {
                $table->dropColumn('custom_system');
            }
            if (Schema::hasColumn('user_settings', 'context')) {
                $table->dropColumn('context');
            }
            if (Schema::hasColumn('user_settings', 'style')) {
                $table->dropColumn('style');
            }
            if (Schema::hasColumn('user_settings', 'tone')) {
                $table->dropColumn('tone');
            }
        });
    }
};
