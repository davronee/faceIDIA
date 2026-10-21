<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector;');

        DB::statement("
            CREATE TABLE IF NOT EXISTS persons (
                id SERIAL PRIMARY KEY,
                full_name VARCHAR(255) NOT NULL,
                phone_number VARCHAR(50),
                department VARCHAR(100),
                status VARCHAR(20) DEFAULT 'pending',
                photo_url TEXT,
                rejection_reason TEXT,
                face_embedding vector(512) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        DB::statement('CREATE INDEX IF NOT EXISTS idx_persons_status ON persons(status);');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_persons_embedding_hnsw ON persons USING hnsw (face_embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS persons CASCADE;');
    }
};
