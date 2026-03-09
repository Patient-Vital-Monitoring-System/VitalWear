# Debugging Files

This folder contains essential debugging tools for the VitalWear system, organized by role for efficient troubleshooting.

## 🚀 Quick Access

**Main Debugging Dashboard:**
- **[`index.php`](index.php)** - Complete debugging dashboard with system overview and quick access to all tools

## 📋 Role-Specific Debugging Tools

### 👔 Admin Role Debugging
**File:** [`admin_debug.php`](admin_debug.php)
- Table structure verification
- Data integrity checks
- API testing (create/update admin)
- Expected vs actual column comparison
- Interactive forms for testing admin operations

### 💼 Management Role Debugging  
**File:** [`management_debug.php`](management_debug.php)
- Table structure verification
- Data integrity checks
- API testing (create/update management)
- Expected vs actual column comparison
- Interactive forms for testing management operations

### 🚑 Responder Role Debugging
**File:** [`responder_debug.php`](responder_debug.php)
- Table structure verification
- Related tables check (incident, vitalstat)
- API testing (create responder, create incident)
- Incident creation testing
- Vital statistics verification

### 🆘 Rescuer Role Debugging
**File:** [`rescuer_debug.php`](rescuer_debug.php)
- Table structure verification
- Device assignment checks
- API testing (create rescuer, assign device)
- Equipment management testing
- Device assignment verification

## 🎯 Usage by Role

### For Admin Issues:
1. Visit [`index.php`](index.php) for system overview
2. Click "Open Admin Debug" for admin-specific issues
3. Use the interactive forms to test admin operations
4. Check table structure and data integrity

### For Management Issues:
1. Visit [`index.php`](index.php) for system overview  
2. Click "Open Management Debug" for management-specific issues
3. Test management account creation and updates
4. Verify table structure matches expectations

### For Responder Issues:
1. Visit [`index.php`](index.php) for system overview
2. Click "Open Responder Debug" for responder-specific issues
3. Test responder accounts and incident creation
4. Check related tables (incident, vitalstat)

### For Rescuer Issues:
1. Visit [`index.php`](index.php) for system overview
2. Click "Open Rescuer Debug" for rescuer-specific issues
3. Test rescuer accounts and device assignments
4. Verify device management functionality

## 🔧 Features

### Interactive Testing
- ✅ Live API testing for CRUD operations
- ✅ Form-based testing interfaces
- ✅ Real-time feedback on operations
- ✅ Error handling and validation

### Comprehensive Checks
- ✅ Table structure verification
- ✅ Data integrity validation
- ✅ Expected vs actual column comparison
- ✅ Related tables dependency checking

### System Health Monitoring
- ✅ Database connection status
- ✅ Table existence verification
- ✅ Record count statistics
- ✅ Recent activity tracking

## 📊 System Overview Dashboard

The main [`index.php`](index.php) provides:
- **System Statistics** - User counts by role
- **Recent Activity** - Today's incidents and patients
- **Health Checks** - All system components status
- **Quick Actions** - Common testing operations
- **Role Access** - Direct links to role-specific debugging

## 🗂️ Clean Organization

### Essential Files Only:
- ✅ **4 role-specific debugging files** with comprehensive functionality
- ✅ **1 main dashboard** for system overview
- ✅ **2 documentation files** for guidance and instructions

### Removed Redundant Files:
- ❌ Individual table checkers (functionality merged into role files)
- ❌ Separate connection testers (functionality in dashboard)
- ❌ Duplicate debugging utilities (consolidated)
- ❌ Legacy test files (replaced by interactive forms)

## 🚀 Getting Started

1. **Start with the Dashboard:** Visit [`index.php`](index.php)
2. **Identify the Role:** Choose the role you're troubleshooting
3. **Use Role-Specific Tools:** Click the appropriate debug link
4. **Run Tests:** Use the interactive forms and checks
5. **Review Results:** Check status messages and table data

## 📝 Notes:
- These files are for development/debugging purposes only
- They contain detailed logging and debugging information
- They can be safely removed in production
- All functionality has been consolidated into role-specific files

## 🔗 Quick Links
- **Main Dashboard:** [`index.php`](index.php)
- **Admin Debug:** [`admin_debug.php`](admin_debug.php)
- **Management Debug:** [`management_debug.php`](management_debug.php)
- **Responder Debug:** [`responder_debug.php`](responder_debug.php)
- **Rescuer Debug:** [`rescuer_debug.php`](rescuer_debug.php)
- **Fix Instructions:** [`FIX_INSTRUCTIONS.md`](FIX_INSTRUCTIONS.md)
