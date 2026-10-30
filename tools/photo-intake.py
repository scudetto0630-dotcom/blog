#!/usr/bin/env python3
"""Prepare an uploaded photo ZIP locally; never uploads or publishes anything."""
import argparse
import hashlib
import io
import json
from pathlib import Path, PurePosixPath
import stat
import zipfile
from PIL import Image, ImageOps

Image.MAX_IMAGE_PIXELS = 40_000_000


def dhash(image):
    pixels = list(image.convert('L').resize((9, 8)).getdata())
    return sum((pixels[y * 9 + x] > pixels[y * 9 + x + 1]) << (y * 8 + x)
               for y in range(8) for x in range(8))


def prepare(archive, output):
    output = Path(output)
    if output.exists():
        raise ValueError('Use a new output directory; existing files are never overwritten.')
    records, files, hashes = [], [], {}
    with zipfile.ZipFile(archive) as z:
        members = z.infolist()
        if len(members) > 300 or sum(m.file_size for m in members) > 200_000_000:
            raise ValueError('ZIP exceeds 300 entries or 200MB uncompressed.')
        for m in members:
            path = PurePosixPath(m.filename.replace('\\', '/'))
            if path.is_absolute() or '..' in path.parts or stat.S_ISLNK(m.external_attr >> 16):
                raise ValueError('Unsafe ZIP entry.')
            if m.is_dir() or '__MACOSX' in path.parts or path.name.startswith('.'):
                continue
            if path.suffix.lower() not in ('.jpg', '.jpeg', '.png', '.webp'):
                continue
            if m.file_size > 25_000_000 or m.file_size > max(m.compress_size, 1) * 200:
                raise ValueError('Image exceeds size or compression-ratio limit.')
            raw = z.read(m)
            digest = hashlib.sha256(raw).hexdigest()
            if digest in hashes:
                records.append({'original': m.filename, 'duplicate_of': hashes[digest], 'sha256': digest})
                continue
            with Image.open(io.BytesIO(raw)) as source:
                if source.format not in ('JPEG', 'PNG', 'WEBP') or getattr(source, 'n_frames', 1) != 1:
                    raise ValueError('Only still JPEG, PNG and WebP images are supported.')
                if source.width * source.height > 40_000_000:
                    raise ValueError('Image exceeds 40 million pixels.')
                source.load()
                exif = source.getexif()
                gps = 34853 in exif
                oriented = ImageOps.exif_transpose(source)
                image = oriented.convert('RGBA' if 'A' in oriented.getbands() or
                                         'transparency' in oriented.info else 'RGB')
                fingerprint = dhash(image)
                similar = [r['file'] for r in records if 'dhash' in r and
                           (int(r['dhash'], 16) ^ fingerprint).bit_count() <= 5]
                image.thumbnail((1800, 1800))
                buffer = io.BytesIO()
                image.save(buffer, 'WEBP', quality=86, method=6, exif=b'', icc_profile=None)
                name = 'photo-' + digest[:16] + '.webp'
                hashes[digest] = name
                records.append({'original': m.filename, 'file': name, 'sha256': digest,
                                'optimized_sha256': hashlib.sha256(buffer.getvalue()).hexdigest(),
                                'width': image.width, 'height': image.height,
                                'original_bytes': len(raw), 'optimized_bytes': buffer.tell(),
                                'gps_found_in_original': gps, 'metadata_removed': True,
                                'dhash': f'{fingerprint:016x}', 'similar_candidates': similar,
                                'kind': 'unconfirmed', 'rights': 'unknown', 'purpose': 'hold',
                                'visual_privacy_checked': False})
                files.append((name, buffer.getvalue()))
    if not files:
        raise ValueError('No supported photos found.')
    output.mkdir(parents=True, mode=0o700)
    for name, data in files:
        (output / name).write_bytes(data)
    (output / 'intake.json').write_text(json.dumps({'published': False, 'photos': records}, ensure_ascii=False, indent=2))
    return {'prepared': len(files), 'duplicates': len(records) - len(files),
            'output': str(output), 'published': False}


if __name__ == '__main__':
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('zip', type=Path)
    p.add_argument('--output', type=Path, required=True)
    a = p.parse_args()
    try:
        print(json.dumps(prepare(a.zip, a.output), ensure_ascii=False))
    except (ValueError, OSError, zipfile.BadZipFile, Image.DecompressionBombError) as e:
        p.exit(1, str(e) + '\n')
