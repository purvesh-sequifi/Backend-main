import fitz  # PyMuPDF
import base64
from datetime import datetime
import tempfile
import os
import sys
#pip3 install requests
import requests
import json
from PIL import Image, ImageFilter
from io import BytesIO

arguments = sys.argv[1:]
# print(arguments)
# file_output_path    = arguments[0]
pdf_path                 = arguments[0]
rect_x              = float(arguments[1])
rect_y              = float(arguments[2])
base64_image        = arguments[3]
page_number         = int(arguments[4])
guest_name          = arguments[5]
file_output_path          = arguments[6]
signatureType          = arguments[7]
isPdf          = arguments[8]
isHD          = arguments[9]

# print(arguments[1])
# print(arguments[2])
# print(arguments[3])
# print(arguments[4])


def download_pdf(url):
    response = requests.get(url)
    if response.status_code == 200:
        _, temp_filename = tempfile.mkstemp(suffix=".pdf", prefix="temp_pdf_")
        with open(temp_filename, "wb") as temp_file:
            temp_file.write(response.content)
        return temp_filename
    else:
        return ''



def insert_image_and_ts(pdf_path, rect_x, rect_y, base64_image, page_number, guest_name, signatureType, isPdf, isHD):
    doc = fitz.open(pdf_path)

    if 0 < page_number <= doc.page_count:

        page = doc[page_number - 1]  # Adjust to 0-based index
        img_data = base64.b64decode(base64_image)
        pixmap = fitz.Pixmap(img_data)

        img_width, img_height = pixmap.width, pixmap.height

        # Calculate the position to center the image on the page
        img_x = rect_x
        img_y = rect_y

        increasedWidth = 0
        increasedHeight = 0
        fonSizeVal = 8

        if isPdf == '1' and isHD == 'yes':
            increasedWidth = 200
            increasedHeight = 30
            fonSizeVal = 18
        

        # Create a black rectangle around the image
        if signatureType == 'sign_by_keyboard':
            # x0=img_x
            # x1=img_x + img_width
            # y0=img_y
            # y1=img_y + img_height
            x0 = img_x
            x1 = img_x + img_width + increasedWidth
            y0 = img_y
            y1 = img_y + img_height + increasedHeight
        else:
            # x0=img_x
            # y0=img_y
            # x1=img_x + 100
            # y1=img_y + 40
            x0 = img_x
            y0 = img_y
            x1 = img_x + 100 + increasedWidth
            y1 = img_y + 40 + increasedHeight

        stamp_x0 = x1 + 10
        stamp_y0 = y0
        stamp_x1 = x1 + 10
        stamp_y1 = y0 + 10   

        # text_rect = fitz.Rect(rect_x + img_width + 10, rect_y, rect_x + img_width, rect_y + 10)

        
        

        rect = fitz.Rect(x0, y0, x1, y1)
        # rect = fitz.Rect(img_x, img_y, img_x + 350, img_y + 40)
        # logging.debug(x0, y0, x1, y1)

        

        page.draw_rect(rect, fill=(1, 1, 1))  # Fill with black
        # page.draw_rect(rect)  # Fill with black

        # Insert the image into the page at its original size and the calculated position
        # image_rect = page.insert_image(fitz.Rect(img_x, img_y, img_x + img_width, img_y + img_height), stream=img_data)
        image_rect = page.insert_image(rect, stream=img_data)

        # Rect() - all zeros 
        # Rect(x0, y0, x1, y1) - 4 coordinates 
        # Rect(top-left, x1, y1) - point and 2 coordinates 
        # Rect(x0, y0, bottom-right) - 2 coordinates and point 
        # Rect(top-left, bottom-right) - 2 points 
        # Rect(sequ) - new from sequence or rect-like

        # Add text adjacent to the rectangle
        current_time = datetime.now().strftime("%m/%d/%y %I:%M:%S %p %Z")
        # current_time = datetime.now().strftime("%m/%d/%y %I:%M:%S.%f %p %Z")
        guest_text = f"Signed On {guest_name} \n Date: {current_time}"

        # if signatureType == 'sign_by_keyboard':
        #     text_rect = fitz.Rect(rect_x + img_width + 10, rect_y + img_width + 10, rect_x + img_width + 100, rect_y + img_width + 100)
        # else:
        #     text_rect = fitz.Rect(rect_x + 100 + 10, rect_y + 40, rect_x + img_width, rect_y + 10)

        text_rect = fitz.Rect(stamp_x0, stamp_y0, stamp_x1, stamp_y1)
        # text_rect = fitz.Rect(rect_x + img_width + 10, rect_y, rect_x + img_width, rect_y + 10)


        page.insert_text(text_rect.bottom_left, guest_text, fontname="helv", fontsize=fonSizeVal, color=(0, 0, 0))

        # Save the modified PDF
        doc.save(file_output_path)
        # doc.save(pdf_path, incremental=True)
        doc.close()
        return file_output_path
        # return pdf_path
        # return True
        # print(f"Image and timestamp inserted on Page {page_number}")
    else:
        return ''
        # return False
        # print("Invalid page number.")

# Example usage
# pdf_path = '/Users/mac/python-projects/pdf-esign-2/sample.pdf'
# rect_x = 50  # Specify X coordinate of the rectangle
# rect_y = 300  # Specify Y coordinate of the rectangle
# target_page_number = 2  # Specify the page number where the image should be inserted
# base64_image_data = 'iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAApgAAAKYB3X3/OAAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAANCSURBVEiJtZZPbBtFFMZ/M7ubXdtdb1xSFyeilBapySVU8h8OoFaooFSqiihIVIpQBKci6KEg9Q6H9kovIHoCIVQJJCKE1ENFjnAgcaSGC6rEnxBwA04Tx43t2FnvDAfjkNibxgHxnWb2e/u992bee7tCa00YFsffekFY+nUzFtjW0LrvjRXrCDIAaPLlW0nHL0SsZtVoaF98mLrx3pdhOqLtYPHChahZcYYO7KvPFxvRl5XPp1sN3adWiD1ZAqD6XYK1b/dvE5IWryTt2udLFedwc1+9kLp+vbbpoDh+6TklxBeAi9TL0taeWpdmZzQDry0AcO+jQ12RyohqqoYoo8RDwJrU+qXkjWtfi8Xxt58BdQuwQs9qC/afLwCw8tnQbqYAPsgxE1S6F3EAIXux2oQFKm0ihMsOF71dHYx+f3NND68ghCu1YIoePPQN1pGRABkJ6Bus96CutRZMydTl+TvuiRW1m3n0eDl0vRPcEysqdXn+jsQPsrHMquGeXEaY4Yk4wxWcY5V/9scqOMOVUFthatyTy8QyqwZ+kDURKoMWxNKr2EeqVKcTNOajqKoBgOE28U4tdQl5p5bwCw7BWquaZSzAPlwjlithJtp3pTImSqQRrb2Z8PHGigD4RZuNX6JYj6wj7O4TFLbCO/Mn/m8R+h6rYSUb3ekokRY6f/YukArN979jcW+V/S8g0eT/N3VN3kTqWbQ428m9/8k0P/1aIhF36PccEl6EhOcAUCrXKZXXWS3XKd2vc/TRBG9O5ELC17MmWubD2nKhUKZa26Ba2+D3P+4/MNCFwg59oWVeYhkzgN/JDR8deKBoD7Y+ljEjGZ0sosXVTvbc6RHirr2reNy1OXd6pJsQ+gqjk8VWFYmHrwBzW/n+uMPFiRwHB2I7ih8ciHFxIkd/3Omk5tCDV1t+2nNu5sxxpDFNx+huNhVT3/zMDz8usXC3ddaHBj1GHj/As08fwTS7Kt1HBTmyN29vdwAw+/wbwLVOJ3uAD1wi/dUH7Qei66PfyuRj4Ik9is+hglfbkbfR3cnZm7chlUWLdwmprtCohX4HUtlOcQjLYCu+fzGJH2QRKvP3UNz8bWk1qMxjGTOMThZ3kvgLI5AzFfo379UAAAAASUVORK5CYII='
# guest_name = "guyswinford@gmail.com"
# insert_image_and_ts(pdf_path, rect_x, rect_y, base64_image_data, target_page_number, guest_name)

def improve_image_quality(base64_img):
    # Decode base64 image
    img_data = base64.b64decode(base64_img)
    
    # Open image using Pillow
    image = Image.open(BytesIO(img_data))
    
    # Perform image processing (e.g., sharpening)
    sharpened_image = image.filter(ImageFilter.SHARPEN)
    
    # Save the processed image to a BytesIO object
    output_buffer = BytesIO()
    sharpened_image.save(output_buffer, format="JPEG")
    
    # Get the base64 representation of the processed image
    improved_base64_img = base64.b64encode(output_buffer.getvalue()).decode("utf-8")
    
    return improved_base64_img
  
def process_pdf(pdf_path, rect_x, rect_y, base64_image, page_number, guest_name, signatureType, isPdf, isHD):

    # base64_image = improve_image_quality(base64_image)
    
    # print(pdf_path)
    try:
        # pdf_path = download_pdf(url)
        if pdf_path:
            # return pdf_path
            return insert_image_and_ts(pdf_path, rect_x, rect_y, base64_image, page_number, guest_name, signatureType, isPdf, isHD)
        else:
            return ''
    finally:
        if pdf_path and os.path.exists(pdf_path):
            os.remove(pdf_path)

result = process_pdf(pdf_path, rect_x, rect_y, base64_image, page_number, guest_name, signatureType, isPdf, isHD)


print(result, end="")