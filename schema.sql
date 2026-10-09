CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE IF NOT EXISTS persons (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    face_embedding vector(512) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create an HNSW index for ultra-fast approximate nearest neighbor search
-- using cosine distance (vector_cosine_ops) which is standard for face embeddings.
CREATE INDEX ON persons USING hnsw (face_embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64);
