import re
import pytesseract
import sys
import json
from pdf2image import convert_from_path

# Set the path to Poppler (Poppler binaries location)
poppler_path = r'C:\Program Files\poppler-24.08.0\Library\bin'

# Set the Tesseract path manually if it's not in PATH
pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

def extract_cf_from_pdf(pdf_path):
    codice_fiscale_pattern = r'((?:[A-Z]\s*){6}(?:\d\s*){2}(?:[A-Z]\s*)(?:\d\s*){2}(?:[A-Z]\s*)(?:\d\s*){3}(?:[A-Z]\s*))'

    try:
        # Specify the Poppler path for PDF to image conversion
        pages = convert_from_path(pdf_path, dpi=300, poppler_path=poppler_path)
    except Exception as e:
        print(json.dumps({"error": f"Poppler error or invalid PDF: {str(e)}"}))
        return

    all_text = ""
    for page in pages:
        try:
            text = pytesseract.image_to_string(page)
            all_text += text + "\n"
        except Exception as e:
            print(json.dumps({"error": f"OCR error: {str(e)}"}))
            return

    matches = re.findall(codice_fiscale_pattern, all_text, re.IGNORECASE)
    cleaned_matches = [re.sub(r'\s+', '', match) for match in matches]

    # Return only first match or empty list
    result = cleaned_matches[0] if cleaned_matches else None
    print(json.dumps(result))


if __name__ == "__main__":
    print("Python script started")

    if len(sys.argv) != 2:
        print(json.dumps({"error": "Usage: script.py <pdf_path>"}))
        sys.exit(1)

    extract_cf_from_pdf(sys.argv[1])
