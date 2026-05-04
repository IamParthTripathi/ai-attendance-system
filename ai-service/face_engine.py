"""
face_engine.py
==============
Core face recognition logic.
Called by app.py — do not run directly.
"""

import os
import pickle
import numpy as np
import face_recognition
from pathlib import Path
from PIL import Image, UnidentifiedImageError

ENCODINGS_DIR = Path("encodings")

# Matching tolerance: lower = stricter
# 0.4 → very strict (fewer false positives, may miss some)
# 0.5 → balanced  (recommended)
# 0.6 → lenient   (more matches, may have false positives)
TOLERANCE = 0.5


def load_all_encodings() -> dict:
    """
    Load all student face encodings from disk.
    Returns: { "roll_number": numpy_128d_vector }
    """
    db = {}
    for pkl_file in ENCODINGS_DIR.glob("*.pkl"):
        try:
            with open(pkl_file, "rb") as f:
                encoding = pickle.load(f)
            if isinstance(encoding, np.ndarray) and encoding.shape == (128,):
                db[pkl_file.stem] = encoding
            else:
                print(f"[WARN] Skipping {pkl_file.name}: unexpected encoding shape")
        except Exception as exc:
            print(f"[WARN] Could not load {pkl_file.name}: {exc}")
    return db


def process_images(image_paths: list) -> dict:
    """
    Given a list of absolute image paths from a classroom photo session:
      1. Detect all faces in each image
      2. Match each face against the known student database
      3. Return present roll numbers (deduplicated across all images)

    Returns:
        {
            "present":       ["CS001", "CS003", ...],
            "face_count":    7,
            "unknown_count": 2,
            "error":         None
        }
    """
    # Load student database
    known_db = load_all_encodings()

    if not known_db:
        return {
            "present":       [],
            "face_count":    0,
            "unknown_count": 0,
            "error": (
                "No student encodings found in the 'encodings/' folder. "
                "Run:  python encode_students.py"
            ),
        }

    known_rolls     = list(known_db.keys())
    known_encodings = list(known_db.values())

    detected_rolls = set()   # use set to auto-deduplicate across images
    total_faces    = 0
    unknown_count  = 0

    for img_path in image_paths:
        if not os.path.exists(img_path):
            print(f"[WARN] File not found: {img_path}")
            continue

        try:
            # Open with PIL first to validate and resize for speed
            pil_img = Image.open(img_path).convert("RGB")
            w, h    = pil_img.size

            # Resize to half resolution for faster HOG detection
            # (face_recognition works on numpy arrays)
            small   = pil_img.resize((w // 2, h // 2), Image.LANCZOS)
            img_arr = np.array(small)

        except (UnidentifiedImageError, Exception) as exc:
            print(f"[WARN] Cannot open image {img_path}: {exc}")
            continue

        # Detect face bounding boxes
        # model="hog"  → fast, CPU-only  (use for most deployments)
        # model="cnn"  → accurate, needs GPU (comment/uncomment below)
        locations = face_recognition.face_locations(img_arr, model="hog")

        if not locations:
            print(f"[INFO] No faces detected in {os.path.basename(img_path)}")
            continue

        # Compute 128-d encodings for each detected face
        encodings  = face_recognition.face_encodings(img_arr, locations)
        total_faces += len(encodings)

        for face_enc in encodings:
            # Compare this face against all known students
            matches   = face_recognition.compare_faces(
                known_encodings, face_enc, tolerance=TOLERANCE
            )
            distances = face_recognition.face_distance(known_encodings, face_enc)

            if True in matches:
                # Pick the student with the smallest distance (best match)
                best_idx = int(np.argmin(distances))
                if matches[best_idx]:
                    roll = known_rolls[best_idx]
                    detected_rolls.add(roll)
                    print(f"[MATCH] {os.path.basename(img_path)} → {roll}  "
                          f"(distance={distances[best_idx]:.3f})")
            else:
                unknown_count += 1
                print(f"[UNKNOWN] Face in {os.path.basename(img_path)} "
                      f"(closest distance={min(distances):.3f})")

    return {
        "present":       sorted(list(detected_rolls)),
        "face_count":    total_faces,
        "unknown_count": unknown_count,
        "error":         None,
    }
