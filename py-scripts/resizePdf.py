import fitz  # PyMuPDF
import sys
def resize_pdf_to_a4(input_path, output_path):
    a4_width, a4_height = fitz.paper_size("a4")  # Get A4 dimensions in points (1 point = 1/72 inch)
    # Open the input PDF
    document = fitz.open(input_path)
    # Create a new PDF for the output
    output_document = fitz.open()
    for page_num in range(len(document)):
        page = document.load_page(page_num)  # Load each page
        rect = page.rect  # Original page rectangle
        
        # Create a new A4 page
        new_page = output_document.new_page(width=a4_width, height=a4_height)
        
        # Calculate the scaling factor
        scale_x = a4_width / rect.width
        scale_y = a4_height / rect.height
        scale = min(scale_x, scale_y)  # Uniform scaling to fit within A4
        
        # Scale and insert the page
        matrix = fitz.Matrix(scale, scale)
        new_page.show_pdf_page(new_page.rect, document, page_num, matrix=matrix)
    # Save the output PDF
    output_document.save(output_path)
arguments = sys.argv[1:]
input_path  = arguments[0]
output_path  = arguments[1]
resize_pdf_to_a4(input_path, output_path)