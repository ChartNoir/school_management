#!/bin/bash

# Script to delete expired student accounts from Linux and update is_active to 0 in the database
# Uses database credentials from config.php

# Database connection details (from config.php)
DB_NAME="school_management"
DB_USER="systemadmin"
DB_PASS="admin12345"
DB_HOST="localhost"

# Log file for debugging (updated to a writable location for apache)
LOG_FILE="/var/www/html/school_management/logs/delete_expired_accounts.log"

# Temporary file to store SQL output
TEMP_FILE="/tmp/expired_accounts_$$.txt"

# Ensure log file exists and is writable (run with sudo initially if needed)
if [ ! -f "$LOG_FILE" ]; then
    sudo touch "$LOG_FILE"  # Use sudo to create the file initially
    sudo chown apache:apache "$LOG_FILE"
    sudo chmod 640 "$LOG_FILE"
fi

# Log the start of the script
echo "$(date '+%Y-%m-%d %H:%M:%S') - Starting expired account cleanup" >> "$LOG_FILE"

# Get expired students (graduation_date + 1 day has passed) and their usernames
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -N << EOF > "$TEMP_FILE"
SELECT u.username, u.id
FROM users u
JOIN student_details sd ON u.id = sd.user_id
WHERE u.role = 'student'
AND u.is_active = TRUE
AND DATE(sd.graduation_date) < DATE_SUB(CURDATE(), INTERVAL 1 DAY);
EOF

# Check if the query was successful
if [ $? -ne 0 ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Failed to query expired accounts: MySQL error" >> "$LOG_FILE"
    rm -f "$TEMP_FILE"
    exit 1
fi

# Check if there are expired accounts
if [ -s "$TEMP_FILE" ]; then
    while IFS=$'\t' read -r username user_id; do
        # Skip empty lines or invalid entries
        if [ -z "$username" ] || [ -z "$user_id" ]; then
            continue
        fi

        # Remove leading/trailing whitespace
        username=$(echo -n "$username" | xargs)

        # Log the user being processed
        echo "$(date '+%Y-%m-%d %H:%M:%S') - Processing user: $username (ID: $user_id)" >> "$LOG_FILE"

        # Delete the Linux user account (if it exists)
        if id "$username" >/dev/null 2>&1; then
            sudo userdel -r "$username" 2>> "$LOG_FILE"
            if [ $? -eq 0 ]; then
                echo "$(date '+%Y-%m-%d %H:%M:%S') - Successfully deleted Linux user: $username" >> "$LOG_FILE"
            else
                echo "$(date '+%Y-%m-%d %H:%M:%S') - Failed to delete Linux user: $username" >> "$LOG_FILE"
            fi
        else
            echo "$(date '+%Y-%m-%d %H:%M:%S') - Linux user $username does not exist, skipping deletion" >> "$LOG_FILE"
        fi

        # Update is_active to 0 in the users table for the expired account
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
            UPDATE users 
            SET is_active = 0 
            WHERE id = $user_id AND role = 'student';
        " 2>> "$LOG_FILE"
        if [ $? -eq 0 ]; then
            echo "$(date '+%Y-%m-%d %H:%M:%S') - Updated is_active to 0 for user ID $user_id (username: $username)" >> "$LOG_FILE"
        else
            echo "$(date '+%Y-%m-%d %H:%M:%S') - Failed to update is_active for user ID $user_id (username: $username)" >> "$LOG_FILE"
        fi
    done < "$TEMP_FILE"
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') - No expired accounts found" >> "$LOG_FILE"
fi

# Clean up temporary file
rm -f "$TEMP_FILE"

# Log the end of the script
echo "$(date '+%Y-%m-%d %H:%M:%S') - Finished expired account cleanup" >> "$LOG_FILE"

exit 0
