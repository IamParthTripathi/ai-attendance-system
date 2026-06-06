# Use a base image that already has dlib pre-compiled
# This saves ~8GB of RAM during build — dlib is already baked in
FROM animcogn/face_recognition:cpu

# Set working directory inside the container
WORKDIR /app

# Copy only what we need (not venv, not encodings)
COPY requirements_docker.txt .
COPY app.py .
COPY face_engine.py .
COPY encode_students.py .
COPY student_photos/ ./student_photos/
COPY encodings/ ./encodings/

# Install only the remaining deps that aren't in the base image
RUN pip install fastapi==0.111.0 uvicorn==0.29.0 python-multipart==0.0.9

# Run the encoding script so face vectors are ready
RUN python encode_students.py

# Start the server (Render injects $PORT automatically)
CMD ["sh", "-c", "uvicorn app:app --host 0.0.0.0 --port ${PORT:-5000}"]