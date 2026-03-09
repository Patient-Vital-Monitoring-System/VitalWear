# Database Migration Instructions

## Quick Fix for Incident Creation

The issue is that the `incident` table requires a `log_id` but responders can create incidents without assigned devices.

### Option 1: Run via phpMyAdmin (Recommended)
1. Open phpMyAdmin in your browser
2. Select the `vitalwear` database
3. Go to SQL tab
4. Run this command:
```sql
ALTER TABLE incident MODIFY COLUMN log_id INT DEFAULT NULL;
```

### Option 2: Run via Browser
1. Open this URL in your browser:
   http://localhost/VitalWear-1/database/run_migration.php

### Option 3: Command Line
```bash
cd c:\xampp\mysql\bin
mysql -u root vitalwear -e "ALTER TABLE incident MODIFY COLUMN log_id INT DEFAULT NULL;"
```

## Verification
After running the migration, the responder should be able to create incidents successfully.

## What This Fixes
- Allows incident creation without requiring device assignment
- Maintains device tracking when devices are assigned
- Resolves the "Failed to create incident" error
