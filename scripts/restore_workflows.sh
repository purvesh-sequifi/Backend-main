#!/bin/bash

# Script to restore all workflow files from the flexpest.yml template
# This script will copy the flexpest.yml content to all workflow files
# with appropriate modifications for each file

echo "Starting recovery of workflow files..."

# Get the template content from flexpest.yml
TEMPLATE_CONTENT=$(cat /Users/silvergrey/Desktop/Backend/flexpest.yml)

restore_workflow_file() {
    local file=$1
    local branch_name=$(basename "$file" .yml)
    
    # Special case for yaml extension
    if [[ "$file" == *.yaml ]]; then
        branch_name=$(basename "$file" .yaml)
    fi
    
    # Handle non-standard filenames (files without extension or with multiple dots)
    if [[ "$branch_name" == "capecod" ]] || [[ "$branch_name" == "maine" ]] || [[ "$branch_name" == "momentumv2" ]] || [[ "$branch_name" == "tiers" ]] || [[ "$branch_name" == "uatdemo_rds" ]]; then
        # For files without extension, use the filename as branch name
        :
    fi
    
    echo "Restoring file: $file with branch: $branch_name-backend"
    
    # Create the workflow content with the correct branch name
    local workflow_content=$(echo "$TEMPLATE_CONTENT" | sed "s/flexpest-backend/$branch_name-backend/g" | sed "s/flexpest\.sequifi\.com/$branch_name.sequifi.com/g" | sed "s/\/backend\/flexpest/\/backend\/$branch_name/g")
    
    # Write the content to the file
    echo "$workflow_content" > "$file"
    
    echo "Restored: $file"
}

# Process all workflow files
find ./.github/workflows -name "*.yml" -o -name "*.yaml" | while read file; do
    restore_workflow_file "$file"
done

echo "All workflow files have been restored!"
