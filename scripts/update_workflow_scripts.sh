#!/bin/bash

# Script to update all yml workflow files with the correct deployment script format
# This script should be run from the root directory of the project

# Use the flexpest.yml file as the template
TEMPLATE_CONTENT=$(cat /Users/silvergrey/Desktop/Backend/flexpest.yml | sed -n '/      - name: Pre-deployment Setup/,/      - name: Setup Application Environment/p' | grep -v 'name: Setup Application Environment' | grep -v 'name: Pre-deployment Setup' | grep -v 'uses: appleboy/ssh-action@master' | grep -v 'with:' | grep -v 'host:' | grep -v 'username:' | grep -v 'key:')

# Extract the worker process content from flexpest.yml
WORKER_PROCESS_CONTENT=$(cat /Users/silvergrey/Desktop/Backend/flexpest.yml | sed -n '/      # Configure worker processes/,/🚀 DEPLOYMENT COMPLETED SUCCESSFULLY/p')

update_file() {
    local file=$1
    echo "Processing file: $file"
    
    # Create a temporary file
    local tmp_file=$(mktemp)
    
    # Extract the content before the script section
    awk 'BEGIN{p=1} /script: \|/{p=0; print; exit} p==1{print}' "$file" > "$tmp_file"
    
    # Add the template script content
    echo "$TEMPLATE_CONTENT" >> "$tmp_file"
    
    # Find where the deployment script ends and extract content after it
    # We'll look for the next section after the deployment script which usually starts with a step name
    sed -n '/script: |/,/      - name:/p' "$file" | tail -n +2 | sed '1,/      - name:/!d;/      - name:/d' > /tmp/script_section.txt
    
    if grep -q "Configure Worker Processes" "$file"; then
        # If the file already has a Configure Worker Processes section, replace it
        sed -n '/      # Configure worker processes/,/All workers restarted successfully/p' "$file" | head -n -1 > /dev/null
        # Add the new worker process content
        echo "$WORKER_PROCESS_CONTENT" >> "$tmp_file"
        
        # Extract the content after the worker process section
        sed -n '/All workers restarted successfully/,$p' "$file" | tail -n +2 >> "$tmp_file"
    else
        # If no worker process section exists, find where to add it
        if grep -q "Setup Application Environment" "$file"; then
            # Replace Setup Application Environment with our Worker Process
            sed -n '/      - name: Setup Application Environment/,$p' "$file" > /tmp/after_script.txt
            echo "$WORKER_PROCESS_CONTENT" >> "$tmp_file"
            cat /tmp/after_script.txt | tail -n +7 >> "$tmp_file"
        else
            # Just append the worker process after the script section
            awk 'BEGIN{p=0} /script: \|/{p=1} /      - name:/{if(p==1){p=2; print; exit}} p==2{print}' "$file" >> "$tmp_file"
            echo "$WORKER_PROCESS_CONTENT" >> "$tmp_file"
            awk 'BEGIN{p=0} /      - name: Execute Database Monitoring/{p=1; print; exit} p==1{print}' "$file" >> "$tmp_file"
        fi
    fi
    
    # Replace the original file
    mv "$tmp_file" "$file"
    echo "Updated file: $file"
}

# Find all YAML files in .github/workflows and update them
find ./.github/workflows -name "*.yml" -o -name "*.yaml" | while read file; do
    echo "Processing file: $file"
    update_file "$file"
done

echo "All workflow files have been updated!"
