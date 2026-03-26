#!/bin/bash
cat ./.env.deployment | while read line; do
  if [ "$line" != "" ]; then
    if [[ $line != *"="* ]]; then
      VAR=$(printenv $line)
      if [[ ! -n "${VAR}" ]]; then
        echo $line;
      else
        echo $line=\"$VAR\"
      fi
    else
      echo $line
    fi
  fi
done
