"""
encode_students.py
==================
Run this ONCE after placing student photos in student_photos/ folder.
Run again whenever you add new students.

Usage:
    (venv) python encode_students.py

Photo naming rule:
    student_photos/<roll_number>.jpg   e.g.  CS001.jpg

Output:
    encodings/<roll_number>.pkl  (one file per student)
"""

import os
import pickle
import face_recognition
from pathlib import Path

PHOTOS_DIR    = Path("student_photos")
ENCODINGS_DIR = Path("encodings")
ENCODINGS_DIR.mkdir(exist_ok=True)

SUPPORTED_EXTS = {".jpg", ".jpeg", ".png"}


def encode_all():
    photos = [p for p in PHOTOS_DIR.glob("*") if p.suffix.lower() in SUPPORTED_EXTS]

    if not photos:
        print(f"[ERROR] No photos found in '{PHOTOS_DIR}/'")
        print("  Place student photos named as their roll numbers, e.g.  CS001.jpg")
        return

    success, failed = 0, []

    for photo in sorted(photos):
        roll          = photo.stem          # filename without extension = roll number
        encoding_path = ENCODINGS_DIR / f"{roll}.pkl"

        print(f"Processing {photo.name} ...", end=" ", flush=True)

        try:
            image     = face_recognition.load_image_file(str(photo))
            encodings = face_recognition.face_encodings(image)

            if not encodings:
                print("WARN — no face detected. Use a clear, front-facing photo.")
                failed.append(roll)
                continue

            if len(encodings) > 1:
                print(f"WARN — {len(encodings)} faces found, using the first one.")

            with open(encoding_path, "wb") as f:
                pickle.dump(encodings[0], f)

            print("OK")
            success += 1

        except Exception as exc:
            print(f"ERROR — {exc}")
            failed.append(roll)

    print(f"\n{'='*40}")
    print(f"Done: {success} encoded, {len(failed)} failed")
    if failed:
        print(f"Failed roll numbers: {', '.join(failed)}")
    print(f"Encoding files saved to: {ENCODINGS_DIR.resolve()}")


if __name__ == "__main__":
    encode_all()
