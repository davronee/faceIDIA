import os
import cv2
import numpy as np
import asyncpg
from fastapi import FastAPI, File, Form, UploadFile, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from insightface.app import FaceAnalysis

app = FastAPI(title="Lightweight Face Recognition API")

# Setup CORS for frontend communication
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Adjust for production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize InsightFace
# Using 'buffalo_sc' model - very lightweight and suitable for edge/server environments.
# InsightFace performs both detection and recognition. Since the face is already cropped 
# by MediaPipe on the frontend, InsightFace will primarily just extract the 512-d embedding.
try:
    # Set to prioritize GPU (CUDA). Automatically falls back to CPU if GPU is not available.
    face_app = FaceAnalysis(name='buffalo_sc', providers=['CUDAExecutionProvider', 'CPUExecutionProvider'])
    face_app.prepare(ctx_id=0, det_size=(640, 640))
except Exception as e:
    print(f"Error initializing InsightFace: {e}")

DB_DSN = os.getenv("DATABASE_URL", "postgres://user:password@localhost:5432/face_recognition")
db_pool = None

@app.on_event("startup")
async def startup():
    global db_pool
    try:
        # Create a connection pool for efficient DB access
        db_pool = await asyncpg.create_pool(dsn=DB_DSN)
    except Exception as e:
        print(f"Error connecting to database: {e}")

@app.on_event("shutdown")
async def shutdown():
    if db_pool:
        await db_pool.close()

def get_embedding(image_bytes: bytes):
    """
    Extract 512-d embedding from cropped face image bytes using InsightFace.
    """
    # Convert bytes to numpy array for OpenCV
    nparr = np.frombuffer(image_bytes, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    
    if img is None:
        raise HTTPException(status_code=400, detail="Invalid image file.")
        
    # Get faces from InsightFace
    faces = face_app.get(img)
    
    if not faces:
        raise HTTPException(status_code=400, detail="No face detected by InsightFace in the provided crop.")
        
    # Assuming the largest/only face in the crop is the target
    # The 'buffalo_sc' model recognition module generates a 512-d vector
    embedding = faces[0].embedding
    return embedding

@app.post("/api/register")
async def register_face(full_name: str = Form(...), file: UploadFile = File(...)):
    """
    Registers a new person with their face embedding, preventing duplicate registrations.
    """
    image_bytes = await file.read()
    embedding = get_embedding(image_bytes)
    
    # Convert numpy array to string format expected by pgvector: '[val1, val2, ...]'
    embedding_str = '[' + ','.join(map(str, embedding.tolist())) + ']'
    
    threshold = 0.4
    
    # First, check if the face already exists in the database
    check_query = """
        SELECT full_name 
        FROM persons 
        WHERE (face_embedding <=> $1::vector) < $2 
        ORDER BY face_embedding <=> $1::vector 
        LIMIT 1
    """
    
    insert_query = "INSERT INTO persons (full_name, face_embedding) VALUES ($1, $2::vector) RETURNING id"
    
    async with db_pool.acquire() as conn:
        try:
            # Check for duplicates
            existing_record = await conn.fetchrow(check_query, embedding_str, threshold)
            if existing_record:
                raise HTTPException(
                    status_code=400, 
                    detail=f"Xatolik: Ushbu shaxs tizimda allaqachon '{existing_record['full_name']}' ismi ostida ro'yxatdan o'tgan."
                )
            
            # If not duplicate, insert
            row_id = await conn.fetchval(insert_query, full_name, embedding_str)
            return {"status": "success", "message": "User registered successfully", "id": row_id}
        except HTTPException:
            raise
        except Exception as e:
            raise HTTPException(status_code=500, detail=str(e))

@app.post("/api/recognize")
async def recognize_face(file: UploadFile = File(...)):
    """
    Recognizes a face by performing an HNSW vector search in PostgreSQL.
    """
    image_bytes = await file.read()
    embedding = get_embedding(image_bytes)
    embedding_str = '[' + ','.join(map(str, embedding.tolist())) + ']'
    
    # Cosine distance threshold (e.g., 0.4 implies a cosine similarity of 0.6)
    # The `<=>` operator computes cosine distance in pgvector
    threshold = 0.4 
    
    # Query using pgvector cosine distance operator <=>
    # We order by distance and limit to 1 (approximate nearest neighbor)
    query = """
        SELECT id, full_name, (face_embedding <=> $1::vector) as distance
        FROM persons
        WHERE (face_embedding <=> $1::vector) < $2
        ORDER BY face_embedding <=> $1::vector
        LIMIT 1
    """
    
    async with db_pool.acquire() as conn:
        try:
            record = await conn.fetchrow(query, embedding_str, threshold)
            
            if record:
                return {
                    "status": "success", 
                    "match": True, 
                    "person": {
                        "id": record['id'],
                        "full_name": record['full_name'],
                        "distance": record['distance']
                    }
                }
            else:
                return {"status": "success", "match": False, "message": "No matching face found."}
                
        except Exception as e:
            raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=8000, reload=True)
