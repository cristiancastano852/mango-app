#!/bin/bash
while IFS= read -r line; do
  if [ "$line" != "" ]; then
    if [[ "$line" != *"="* ]]; then
      VAR=$(printenv "$line")
      if [[ ! -n "${VAR}" ]]; then
        echo "$line"
      else
        ESCAPED=$(printf '%s' "$VAR" | sed 's/\\/\\\\/g; s/"/\\"/g')
        echo "$line=\"$ESCAPED\""
      fi
    else
      echo "$line"
    fi
  fi
done < ./.env.deployment
