import fitz  # PyMuPDF
import tempfile
import os
import sys
#pip3 install requests
import requests
import json

# print(arguments[0])
# print(arguments[1])

def download_pdf(url):
    response = requests.get(url)
    if response.status_code == 200:
        _, temp_filename = tempfile.mkstemp(suffix=".pdf", prefix="temp_pdf_")
        with open(temp_filename, "wb") as temp_file:
            temp_file.write(response.content)
        return temp_filename
    else:
        return ''
        # raise Exception(f"Failed to download PDF from {url}")


def find_word_location(pdf_path, target_word):
    result = []
    doc = fitz.open(pdf_path)
    for page_number in range(doc.page_count):
        page = doc[page_number]
        text_instances = page.search_for(target_word)
        for inst in text_instances:
            if target_word == '[text_entry]':
                result.append({
                    "page": page_number + 1,
                    "target_word": target_word,
                    # adjustmnt in y coordinnate
                    "location": f"{int(inst.x0 * 1.30)},{int(inst.y0 * 1.295)},{int(inst.x1)},{int(inst.y1)}",
                    "pdf_location": f"{int(inst.x0)},{int(inst.y0)},{int(inst.x1)},{int(inst.y1)}",
                    "pdf_dimensions": {
                        "width": int(page.rect.width),
                        "height": int(page.rect.height),
                    }
                })

            else:
                result.append({
                    "page": page_number + 1,
                    "target_word": target_word,
                    # adjustmnt in y coordinnate
                    "location": f"{int(inst.x0 * 1.30)},{int(inst.y0 * 1.295)},{int(inst.x1)},{int(inst.y1)}",
                    "pdf_location": f"{int(inst.x0)},{int(inst.y0)},{int(inst.x1)},{int(inst.y1)}",
                    "pdf_dimensions": {
                        "width": int(page.rect.width),
                        "height": int(page.rect.height),
                    }
                })

            # print(f"Page {page_number + 1}: {inst}")
    doc.close()
    return json.dumps(result, indent=2)
    # return result

def process_pdf(url, target_word):
    try:
        pdf_path = download_pdf(url)
        if pdf_path:
            return find_word_location(pdf_path, target_word)
        else:
            return []
    finally:
        if pdf_path and os.path.exists(pdf_path):
            os.remove(pdf_path)

arguments = sys.argv[1:]

pdf = arguments[0]
target_word = arguments[1]
wordLocation = process_pdf(pdf, target_word)
print(wordLocation)
