#!/bin/bash

# Run the curl command and capture the HTTP status code
http_code=$(curl -s -o /dev/null -w "%{http_code}" https://ifca.kurakurabali.com/approval_live/api/autosend)

# Get the current date and time
timestamp=$(date)

# Append a new line, HTTP status code, and timestamp to the success log
{
    echo ""
    echo "$timestamp: $http_code"
} >> /var/www/html/approval_live/storage/autosendlog/autosend_success.log 2>> /var/www/html/approval_live/storage/autosendlog/autosend_error.log || {
    echo ""
    echo "$timestamp: Failed"
} >> /var/www/html/approval_live/storage/autosendlog/autosend_failed.log

