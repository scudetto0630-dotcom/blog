import importlib.util
import io
import json
from pathlib import Path
import tempfile
import unittest
import zipfile
from PIL import Image

spec = importlib.util.spec_from_file_location('photo_intake', Path(__file__).parents[1] / 'tools/photo-intake.py')
intake = importlib.util.module_from_spec(spec)
spec.loader.exec_module(intake)


class PhotoIntakeTests(unittest.TestCase):
    def photo(self):
        im = Image.new('RGB', (80, 50), '#aa8866')
        exif = Image.Exif()
        exif[315] = 'Private photographer metadata'
        b = io.BytesIO()
        im.save(b, 'JPEG', exif=exif)
        return b.getvalue()

    def test_deduplicates_strips_metadata_and_holds_publication(self):
        with tempfile.TemporaryDirectory() as d:
            z, out = Path(d) / 'photos.zip', Path(d) / 'prepared'
            raw = self.photo()
            with zipfile.ZipFile(z, 'w') as archive:
                archive.writestr('猫/a.jpg', raw)
                archive.writestr('copy.jpg', raw)
            result = intake.prepare(z, out)
            self.assertEqual(result['prepared'], 1)
            self.assertEqual(result['duplicates'], 1)
            self.assertFalse(result['published'])
            row = json.loads((out / 'intake.json').read_text())['photos'][0]
            self.assertEqual((row['rights'], row['purpose']), ('unknown', 'hold'))
            self.assertFalse(row['visual_privacy_checked'])
            with Image.open(out / row['file']) as image:
                self.assertEqual(dict(image.getexif()), {})
                self.assertEqual(image.size, (80, 50))

    def test_traversal_rejected_before_output(self):
        with tempfile.TemporaryDirectory() as d:
            z, out = Path(d) / 'bad.zip', Path(d) / 'prepared'
            with zipfile.ZipFile(z, 'w') as archive:
                archive.writestr('../escape.jpg', self.photo())
            with self.assertRaises(ValueError):
                intake.prepare(z, out)
            self.assertFalse(out.exists())

    def test_existing_output_never_overwritten(self):
        with tempfile.TemporaryDirectory() as d:
            with self.assertRaises(ValueError):
                intake.prepare(Path(d) / 'missing.zip', Path(d))

    def test_fake_image_fails_before_output(self):
        with tempfile.TemporaryDirectory() as d:
            z, out = Path(d) / 'bad.zip', Path(d) / 'prepared'
            with zipfile.ZipFile(z, 'w') as archive:
                archive.writestr('photo.jpg', b'not an image')
            with self.assertRaises(OSError):
                intake.prepare(z, out)
            self.assertFalse(out.exists())
