#!/bin/bash

if [ -n "$BASE_URL" ] && [ ! -d "$BASE_URL" ]; then 
  ln -s . "public/$BASE_URL"
fi

if [ -n "$TZ" ]; then
  ln -snf /usr/share/zoneinfo/$TZ /etc/localtime
  echo $TZ > /etc/timezon
  echo "date.timezone=$TZ" > /usr/local/etc/php/conf.d/timezone.ini
fi