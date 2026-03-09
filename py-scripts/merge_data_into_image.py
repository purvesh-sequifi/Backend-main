
from PIL import Image, ImageDraw, ImageFont


import sys

import os

def find_font_path(font_name):
    # Define the possible font directories for macOS and Ubuntu
    font_dirs = [
        os.path.expanduser('~/Library/Fonts'),
        '/Library/Fonts',
        '/System/Library/Fonts',
        os.path.expanduser('~/.fonts'),
        '/usr/share/fonts',
        '/usr/local/share/fonts',
    ]
    
    # Search through the directories for the font file
    for font_dir in font_dirs:
        for root, dirs, files in os.walk(font_dir):
            for file in files:
                if file.lower() == font_name.lower():
                    return os.path.join(root, file)
    
    return None

# Function
def insert_text(isHD, input_type, checkmark_img_path, image_path, output_path, text, position=(100, 100), font_size=20, text_color=(255, 255, 255)):

    if isHD == 'no':
        txtFontSizeOfPDF = 18
    else: 
        txtFontSizeOfPDF = 45

    # print(isHD)

    # Open the image
    img = Image.open(image_path)

    # Create a drawing object
    draw = ImageDraw.Draw(img)

    font_path = find_font_path('DejaVuSansMono.ttf')

    if font_path:
        font = ImageFont.truetype(font_path, txtFontSizeOfPDF)
    else:
        font = ImageFont.load_default()

    # Use a default font available in PIL
    # try:
    #     font = ImageFont.truetype("LiberationSans-Regular.ttf", txtFontSizeOfPDF)
    #     # font = ImageFont.load_default()
    # except IOError:
    #     font = ImageFont.load_default(size=txtFontSizeOfPDF)

    # font = ImageFont.load_default(size=font_size)

    # Set the font size and color
    text_color = tuple(text_color)

    # print(text)
    

    # Check if the text is "1"
    if text.lower() == "1" and input_type == 'checkbox':
        # Load the checkmark image
        # checkmark_img_path = "/Users/mac/Desktop/Sample-Transparent.png"  # Replace with the path to your checkmark image
        checkmark_img = Image.open(checkmark_img_path)

        if isHD == 'no':
            checkmark_img = checkmark_img.resize((10, 10))
        else:
            checkmark_img = checkmark_img.resize((50, 50))

        # Paste the checkmark image onto the original image at the specified position
        img.paste(checkmark_img, position, checkmark_img)


    else:
        # Insert text at the specified position
        draw.text(position, text, font=font, fill=text_color)

    # Insert text at the specified position
    # draw.text(position, text, font=font, fill=text_color)

    # Save the modified image
    img.save(output_path)

arguments = sys.argv[1:]
imagePath = arguments[0]
# x = int(arguments[1]) + 160
x = int(arguments[1])
# y = int(arguments[2]) + 300
y = int(arguments[2])
text = arguments[3]
checkmark_img_path = arguments[4]
input_type = arguments[5]
isHD = arguments[6]
# text.encode('utf-8')

# if arguments[3].lower() == "1":
    # text = "✓"   # Unicode character for a check mark
    # text.encode('utf-8')

# if arguments[3].lower() == 1:
    # text = "✓"    # Unicode character for a check mark
    # text.encode('utf-8')
    

outputPath = imagePath
# Usage
insert_text(isHD, input_type, checkmark_img_path, imagePath, outputPath, text, position=(x, y), font_size=45, text_color=(0, 0, 0))   

print(outputPath, end="")
