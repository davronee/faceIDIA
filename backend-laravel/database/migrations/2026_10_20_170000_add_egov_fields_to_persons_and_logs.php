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
        // 1. persons jadvaliga eGov davlat pasport maydonlarini qo'shish
        Schema::table('persons', function (Blueprint $table) {
            $table->string('pinfl', 14)->nullable()->after('full_name')->index();
            $table->string('doc_seria', 10)->nullable()->after('pinfl');
            $table->string('doc_number', 20)->nullable()->after('doc_seria');
            $table->string('surname_latin', 100)->nullable()->after('doc_number');
            $table->string('name_latin', 100)->nullable()->after('surname_latin');
            $table->string('patronym_latin', 100)->nullable()->after('name_latin');
            $table->date('birth_date')->nullable()->after('patronym_latin');
            $table->string('birth_place', 255)->nullable()->after('birth_date');
            $table->string('birth_country', 100)->nullable()->after('birth_place');
            $table->string('nationality', 100)->nullable()->after('birth_country');
            $table->string('citizenship', 100)->nullable()->after('nationality');
            $table->smallInteger('gender')->nullable()->after('citizenship'); // 1: Erkak, 2: Ayol
            $table->string('doc_give_place', 255)->nullable()->after('gender');
            $table->date('doc_date_begin')->nullable()->after('doc_give_place');
            $table->date('doc_date_end')->nullable()->after('doc_date_begin');
            $table->text('passport_photo_url')->nullable()->after('photo_url');
            $table->jsonb('egov_data')->nullable()->after('passport_photo_url');
        });

        // 2. eGov shlyuzidan kelgan har bir so'rov va to'liq javobni audit uchun saqlash jadvali
        Schema::create('egov_passport_logs', function (Blueprint $table) {
            $table->id();
            $table->string('document', 20)->index();
            $table->date('birth_date')->index();
            $table->string('pinfl', 14)->nullable()->index();
            $table->string('full_name', 255)->nullable();
            $table->string('status', 20)->default('success'); // 'success', 'failed'
            $table->text('error_message')->nullable();
            $table->jsonb('raw_response')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('egov_passport_logs');

        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn([
                'pinfl',
                'doc_seria',
                'doc_number',
                'surname_latin',
                'name_latin',
                'patronym_latin',
                'birth_date',
                'birth_place',
                'birth_country',
                'nationality',
                'citizenship',
                'gender',
                'doc_give_place',
                'doc_date_begin',
                'doc_date_end',
                'passport_photo_url',
                'egov_data',
            ]);
        });
    }
};
