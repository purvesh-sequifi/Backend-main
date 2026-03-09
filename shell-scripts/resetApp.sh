#!/bin/bash

# echo $1

# Access the first argument passed to the script
#PHP_EXE_PATH="$1"
PHP_EXE_PATH="/usr/bin/php"

# Access the second argument passed to the script
ARTISAN_TOOL_PATH="$2"

"$PHP_EXE_PATH" "$ARTISAN_TOOL_PATH" migrate:fresh --seed


# "/Users/mac/Library/Application Support/Herd/bin/php82" /Users/mac/Herd/sequifi/artisan migrate:fresh --seed
