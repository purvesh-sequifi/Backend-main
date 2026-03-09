import fitz  # PyMuPDF
from PIL import Image

import tempfile
import os
import sys
#pip3 install requests
import requests
import uuid


# if len(sys.argv) > 1:
#     # Check if the second argument exists and is not empty
#     arguments = sys.argv[1:]
#     if arguments[0] and arguments[1]:

#         pdf_path    = arguments[0]
#         if not os.path.exists(pdf_path):
#             print("", end="")

#         base_path   = arguments[1]

#     else:

#         print("", end="")

# else:

#     print("", end="")
          

# pdf_path = arguments[0]

img_folder_name = str(uuid.uuid4())
arguments = sys.argv[1:]
pdf_path    = arguments[0]
base_path = arguments[1]
# image_folder = os.path.join(base_path, img_folder_name)

image_folder = os.path.join(base_path, img_folder_name)

os.makedirs(image_folder)

# print(arguments, end="")

# if not os.path.exists(base_path):

# print(arguments)

# 889, 1151 - need little bit more downword
# 796, 1030

def convert_pdf_to_images(pdf_path, image_folder, resolution=300, target_size=(796, 1030)):
    # Open the PDF file
    pdf_document = fitz.open(pdf_path)

    # Iterate through each page in the PDF
    for page_number in range(pdf_document.page_count):
        # Get the page
        page = pdf_document[page_number]

        # Set the resolution for the image (adjust as needed)
        matrix = fitz.Matrix(resolution / 72.0, resolution / 72.0)

        # Convert the page to an image with improved quality
        image = page.get_pixmap(matrix=matrix)

        # Convert the image to a PIL Image
        pil_image = Image.frombytes("RGB", [image.width, image.height], image.samples)
        # pil_image = Image.frombytes("RGB", ['1591', '2059'], image.samples)
        # font size; sig: 18 px;
        # 
        # pil_image = pil_image.resize(target_size)

        # Save the image to a file
        image_filename = f"{image_folder}/{page_number + 1}.png"
        pil_image.save(image_filename, "PNG", quality=95)  # You can adjust the quality (0-100)
        os.chmod(image_filename, 0o644)

        print(f"{image_filename}###",end="")

    # Close the PDF file
    pdf_document.close()

# Usage
convert_pdf_to_images(pdf_path, image_folder, resolution=300)
