# from PIL import Image
# from reportlab.pdfgen import canvas
# # pip3 install Pillow reportlab

from PIL import Image
from reportlab.pdfgen import canvas
import sys



def images_to_pdf(image_paths, output_pdf_path):
    # Create a PDF document
    pdf_canvas = canvas.Canvas(output_pdf_path)

    # Iterate through the image paths and add each image to the PDF
    for i, image_path in enumerate(image_paths):
        # Open the image using Pillow
        image = Image.open(image_path)

        # Get the image dimensions
        width, height = image.size

        # Add a page to the PDF with the same size as the image
        pdf_canvas.setPageSize((width, height))

        # Draw the image onto the PDF canvas
        pdf_canvas.drawInlineImage(image, 0, 0, width, height)

        # Show a new page for all images except the last one
        if i < len(image_paths) - 1:
            pdf_canvas.showPage()

    # Save the PDF
    pdf_canvas.save()

arguments = sys.argv[1:]
outputPath = arguments[0]
image_paths = arguments[1].split(',')
images_to_pdf(image_paths, outputPath)
print(outputPath, end="")
