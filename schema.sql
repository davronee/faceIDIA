CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE IF NOT EXISTS persons (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    pinfl VARCHAR(14),
    doc_seria VARCHAR(10),
    doc_number VARCHAR(20),
    surname_latin VARCHAR(100),
    name_latin VARCHAR(100),
    patronym_latin VARCHAR(100),
    birth_date DATE,
    birth_place VARCHAR(255),
    birth_country VARCHAR(100),
    nationality VARCHAR(100),
    citizenship VARCHAR(100),
    gender SMALLINT,
    doc_give_place VARCHAR(255),
    doc_date_begin DATE,
    doc_date_end DATE,
    phone_number VARCHAR(50),
    department VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
    photo_url TEXT,
    passport_photo_url TEXT,
    rejection_reason TEXT,
    face_embedding vector(512) NOT NULL,
    egov_data JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index for filtering by status in queries
CREATE INDEX IF NOT EXISTS idx_persons_status ON persons(status);
CREATE INDEX IF NOT EXISTS idx_persons_pinfl ON persons(pinfl);
CREATE INDEX IF NOT EXISTS idx_persons_doc ON persons(doc_seria, doc_number);

-- Create an HNSW index for ultra-fast approximate nearest neighbor search
-- using cosine distance (vector_cosine_ops) which is standard for face embeddings.
CREATE INDEX IF NOT EXISTS idx_persons_embedding_hnsw ON persons USING hnsw (face_embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64);

-- eGov Passport GCP Audit Log Table
CREATE TABLE IF NOT EXISTS egov_passport_logs (
    id BIGSERIAL PRIMARY KEY,
    document VARCHAR(20) NOT NULL,
    birth_date DATE NOT NULL,
    pinfl VARCHAR(14),
    full_name VARCHAR(255),
    status VARCHAR(20) DEFAULT 'success',
    error_message TEXT,
    raw_response JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_egov_logs_doc ON egov_passport_logs(document);
CREATE INDEX IF NOT EXISTS idx_egov_logs_pinfl ON egov_passport_logs(pinfl);
CREATE INDEX IF NOT EXISTS idx_egov_logs_created ON egov_passport_logs(created_at);
