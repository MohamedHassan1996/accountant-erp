import re
import pytesseract
import sys
import json
from pdf2image import convert_from_path
from PIL import Image
from docx import Document

# Paths
poppler_path = r'C:\Program Files\poppler-24.08.0\Library\bin'
pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

# Enable Arabic language
OCR_LANG = 'ara'

def extract_arabic_text_from_pdf(pdf_path, output_docx_path):
    try:
        pages = convert_from_path(pdf_path, dpi=300, poppler_path=poppler_path)
    except Exception as e:
        print(json.dumps({"error": f"PDF processing error: {str(e)}"}))
        return

    document = Document()
    all_text = ""

    for i, page in enumerate(pages):
        try:
            # Convert each page to string using Arabic OCR
            text = pytesseract.image_to_string(page, lang=OCR_LANG)
            all_text += text + "\n"
            document.add_paragraph(text)
        except Exception as e:
            print(json.dumps({"error": f"OCR error on page {i+1}: {str(e)}"}))
            return

    try:
        document.save(output_docx_path)
    except Exception as e:
        print(json.dumps({"error": f"Error saving Word file: {str(e)}"}))
        return

    print(json.dumps({"message": "Success", "output": output_docx_path}))


if __name__ == "__main__":
    if len(sys.argv) != 3:
        print(json.dumps({"error": "Usage: script.py <pdf_path> <output_docx_path>"}))
        sys.exit(1)

    pdf_path = sys.argv[1]
    output_path = sys.argv[2]

    extract_arabic_text_from_pdf(pdf_path, output_path)
