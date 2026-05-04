"""
app.py
======
FastAPI microservice for face recognition attendance.

Start with:
    (venv) uvicorn app:app --host 0.0.0.0 --port 5000 --reload

Health check:
    http://localhost:5000/health

Process endpoint (called by PHP):
    POST http://localhost:5000/process
    Body: multipart/form-data
        class_id  (str)
        images[]  (file, up to 3)
"""

import os
import shutil
import uuid

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware

from face_engine import process_images

# ── App setup ────────────────────────────────────────────────
app = FastAPI(
    title="AttendAI — Face Recognition Service",
    version="1.0.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],          # restrict to your server IP in production
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)

# Temporary folder for uploaded images (auto-cleaned after processing)
TEMP_DIR = "temp_uploads"
os.makedirs(TEMP_DIR, exist_ok=True)


# ── Health check ─────────────────────────────────────────────
@app.get("/health")
def health():
    """Quick check that the AI service is running."""
    return {"status": "ok", "service": "AttendAI Face Recognition"}


# ── Process endpoint ─────────────────────────────────────────
@app.post("/process")
async def process_attendance(
    images: list[UploadFile] = File(..., description="1–3 classroom photos"),
    class_id: str = Form(..., description="Class ID from the database"),
):
    """
    Receive classroom images, run face recognition,
    return list of recognised student roll numbers.
    """
    # Validate file count
    if not images:
        raise HTTPException(status_code=400, detail="No images provided")
    if len(images) > 3:
        raise HTTPException(status_code=400, detail="Maximum 3 images allowed per session")

    # Validate MIME types
    allowed_types = {"image/jpeg", "image/jpg", "image/png"}
    for img in images:
        if img.content_type not in allowed_types:
            raise HTTPException(
                status_code=400,
                detail=f"Unsupported file type '{img.content_type}'. Use JPEG or PNG.",
            )

    session_id  = str(uuid.uuid4())[:10]
    saved_paths = []

    try:
        # Save uploaded files to temp directory
        for img in images:
            safe_name = img.filename.replace("..", "").replace("/", "").replace("\\", "")
            dest      = os.path.join(TEMP_DIR, f"{session_id}_{safe_name}")
            with open(dest, "wb") as f:
                shutil.copyfileobj(img.file, f)
            saved_paths.append(dest)

        # Run face recognition
        result = process_images(saved_paths)

        if result.get("error"):
            raise HTTPException(status_code=500, detail=result["error"])

        return {
            "class_id":      class_id,
            "present_rolls": result["present"],
            "face_count":    result["face_count"],
            "unknown_count": result["unknown_count"],
            "images_processed": len(saved_paths),
        }

    finally:
        # Always clean up temp files
        for path in saved_paths:
            try:
                if os.path.exists(path):
                    os.remove(path)
            except OSError:
                pass


# ── Run directly (dev only) ──────────────────────────────────
if __name__ == "__main__":
    import uvicorn
    uvicorn.run("app:app", host="0.0.0.0", port=5000, reload=True)
