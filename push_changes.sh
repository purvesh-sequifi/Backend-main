#!/bin/bash
set -e

cd /var/www/backend/current

# Configure git
git config --global --add safe.directory /var/www/backend/current
git config user.email "gorakh@sequifi.com"
git config user.name "gorakhoo7"

# Add all changes
git add .

# Commit changes
git commit -m "URGENT: Remove problematic DEFINER fix from all workflow files

🚨 CRITICAL FIX - ALL DEPLOYMENTS WERE FAILING:

❌ PROBLEM:
- DEFINER fix was running in workflow files BEFORE dependencies installed
- Running before .env setup, before database config  
- Tinker command failing silently on all servers
- All 7 production deployments failing

✅ SOLUTION:
- Removed problematic DEFINER fix from ALL workflow files:
  * momentum.sequifi.com.yml
  * onyx2.sequifi.com.yml
  * multitenant.sequifi.yml
  * new.sequifi.com.yml
  * flexpwr2.sequifi.yml
  * fastfiber.sequifi.com.yml
  * embrase.sequifi.new.yml
  * embrase.sequifi.com.yml (recreated with proper content)

✅ DEFINER FIX NOW RUNS FROM deploy.sh:
- After dependencies installed
- After .env configured
- After database connection available
- With proper error handling and logging

🚀 ALL DEPLOYMENTS SHOULD NOW WORK:
- Clean workflow files without problematic DEFINER code
- DEFINER fix runs at correct time in deploy.sh
- Proper sequence: dependencies → env → database → DEFINER fix → migrate"

# Set remote with token
git remote set-url origin https://gorakhoo7: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@github.com/sequifi/Backend.git

# Push changes
git push origin main_octane

echo "✅ All changes pushed successfully!"
