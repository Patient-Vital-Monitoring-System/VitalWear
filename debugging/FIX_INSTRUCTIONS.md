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

## User Management Role Fixes

The user management system has been updated to work with the actual database structure. Some tables may not have all expected columns.

### Admin Table Structure
Expected columns: `admin_id`, `admin_name`, `admin_email`, `admin_password`

**Missing columns that were removed from code:**
- `admin_contact` - Not present in database, removed from forms and APIs
- `status` - Not present in database, hardcoded as 'active'
- `created_at` - Not present in database, uses CURRENT_TIMESTAMP

### Management Table Structure  
Expected columns: `mgmt_id`, `mgmt_name`, `mgmt_email`, `mgmt_password`

**Missing columns that were removed from code:**
- `mgmt_contact` - Not present in database, removed from forms and APIs
- `status` - Not present in database, hardcoded as 'active'
- `created_at` - Not present in database, uses CURRENT_TIMESTAMP

### Responder Table Structure
Expected columns: `responder_id`, `responder_name`, `responder_email`, `responder_contact`, `responder_password`, `status`, `created_at`

### Rescuer Table Structure
Expected columns: `rescuer_id`, `rescuer_name`, `rescuer_email`, `rescuer_contact`, `rescuer_password`, `status`, `created_at`

## Database Verification

### Check Table Structures
Run these SQL commands to verify your table structures:

```sql
-- Check admin table
DESCRIBE admin;

-- Check management table  
DESCRIBE management;

-- Check responder table
DESCRIBE responder;

-- Check rescuer table
DESCRIBE rescuer;
```

### Add Missing Columns (If Needed)
If you want to add the missing columns for full functionality:

```sql
-- For admin table (optional)
ALTER TABLE admin 
ADD COLUMN admin_contact VARCHAR(255) AFTER admin_email,
ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER admin_contact,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status;

-- For management table (optional)
ALTER TABLE management 
ADD COLUMN mgmt_contact VARCHAR(255) AFTER mgmt_email,
ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER mgmt_contact,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status;
```

## What This Fixes

### Incident Creation
- Allows incident creation without requiring device assignment
- Maintains device tracking when devices are assigned
- Resolves the "Failed to create incident" error

### User Management
- Fixes AJAX update functionality for all user roles
- Removes references to non-existent database columns
- Provides clean CRUD operations for admin, management, responder, and rescuer roles
- Ensures proper database compatibility with actual table structures

## Verification Steps

1. **Incident Creation**: Test that responders can create incidents without device assignment
2. **User Management**: Test CRUD operations for all user roles (admin, management, responder, rescuer)
3. **AJAX Updates**: Verify that update modals work without page reload
4. **Database Compatibility**: Confirm no SQL errors related to missing columns

## Debugging Tools

All debugging and test files have been moved to the `/debugging/` folder for future troubleshooting:
- Database structure checkers
- Connection testers  
- API debugging files
- Test utilities
