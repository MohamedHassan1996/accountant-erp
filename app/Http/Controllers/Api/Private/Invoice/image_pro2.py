import sys
import os
import traceback
from PIL import Image
import pytesseract
import openpyxl

# Ensure that Tesseract's executable path is correctly set
pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

# Set the environment variable for Tesseract data directory
os.environ['TESSDATA_PREFIX'] = r'C:\Program Files\Tesseract-OCR\tessdata'

def extract_table_data(image_path):
    """
    Extract text from the image using OCR (Arabic).
    """
    try:
        image = Image.open(image_path)
        print(f"Processing image: {image_path}")
        extracted_text = pytesseract.image_to_string(image, config='--psm 6', lang='ara')  # Use Arabic language
        return extracted_text.splitlines()
    except pytesseract.TesseractError as e:
        print(f"Error during OCR extraction: {e}")
        return []

def save_to_excel(data, output_file):
    """
    Save extracted text to an Excel file.
    Each line goes into a new row.
    """
    try:
        wb = openpyxl.Workbook()
        ws = wb.active
        ws.title = "Extracted Data"

        for i, line in enumerate(data, start=1):
            ws.cell(row=i, column=1, value=line)

        # Ensure the directory exists, then save the Excel file
        os.makedirs(os.path.dirname(output_file), exist_ok=True)
        wb.save(output_file)
        print(f"Excel file saved to: {output_file}")
    except Exception as e:
        print("Error during saving to Excel:", str(e))
        traceback.print_exc()

if __name__ == "__main__":
    try:
        if len(sys.argv) < 2:
            print("Usage: python image_pro2.py <image_path>")
            sys.exit(1)

        image_path = sys.argv[1]
        data = extract_table_data(image_path)

        if not data:
            print("No data extracted from the image.")
            sys.exit(1)

        # Define the output file path for saving extracted data
        output_file = os.path.join('storage', 'app', 'public', 'output.xlsx')

        # Save the extracted data to an Excel file
        save_to_excel(data, output_file)

    except Exception as e:
        print("Error:", str(e))
        traceback.print_exc()
        sys.exit(1)
