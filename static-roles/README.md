# VitalWear Static Role System

A complete static implementation of the VitalWear patient vital monitoring system with full navigation, local storage persistence, and JSON database simulation.

## Features

### 🎯 Role-Based Access
- **Management**: Device allocation, user management, system analytics
- **Rescuer**: Patient transport, vital monitoring, incident management  
- **Responder**: First response, incident creation, vital recording

### 💾 Data Persistence
- Local storage-based JSON database
- Automatic data initialization with sample data
- Full CRUD operations for all entities
- Activity logging and audit trails

### 📊 Dashboard Features
- Real-time statistics and charts
- Interactive data visualization
- Responsive design for all devices
- Modern UI with smooth animations

## Quick Start

1. Open `login.html` in your web browser (or navigate to the root directory)
2. Use any of the demo accounts or enter credentials manually
3. The system will automatically redirect you to the appropriate role dashboard
4. Navigate through the system using the sidebar navigation
5. All data is automatically saved to browser local storage

### **Demo Credentials:**
- **Management**: `john@vitalwear.com` / `admin123`
- **Management**: `sarah@vitalwear.com` / `admin123`
- **Rescuer**: `mike@vitalwear.com` / `rescuer123`
- **Rescuer**: `emma@vitalwear.com` / `rescuer123`
- **Responder**: `alex@vitalwear.com` / `responder123`
- **Responder**: `lisa@vitalwear.com` / `responder123`

### **Authentication Flow:**
1. **Login Page** (`login.html`) - Entry point with role selection
2. **Role Dashboard** - Automatic redirect based on user role
3. **Session Management** - Persistent login until logout
4. **Logout** - Clear session and return to login page

## File Structure

```
static-roles/
├── login.html                 # Login page (entry point)
├── index.html                 # Role selection page (fallback)
├── .htaccess                  # Apache configuration
├── js/
│   └── database.js           # Database simulation layer
├── management/
│   └── dashboard.html        # Management dashboard
├── rescuer/
│   └── dashboard.html        # Rescuer dashboard
├── responder/
│   └── dashboard.html        # Responder dashboard
└── README.md                 # This file
```

## Database Schema

### Users
```json
{
  "id": 1,
  "name": "John Manager",
  "email": "john@vitalwear.com",
  "role": "management",
  "created_at": "2024-01-01T00:00:00.000Z"
}
```

### Devices
```json
{
  "id": 1,
  "serial": "VW001",
  "status": "available",
  "assigned_to": null,
  "created_at": "2024-01-01T00:00:00.000Z"
}
```

### Incidents
```json
{
  "id": 1,
  "patient_name": "John Doe",
  "patient_age": 45,
  "patient_condition": "Chest pain",
  "status": "ongoing",
  "responder_id": 5,
  "rescuer_id": null,
  "created_at": "2024-01-01T00:00:00.000Z",
  "updated_at": "2024-01-01T00:00:00.000Z"
}
```

### Vitals
```json
{
  "id": 1,
  "incident_id": 1,
  "heart_rate": 72,
  "bp_systolic": 120,
  "bp_diastolic": 80,
  "oxygen_level": 98,
  "temperature": 98.6,
  "recorded_at": "2024-01-01T00:00:00.000Z"
}
```

## API Reference

### Database Operations

```javascript
// Get database instance
const db = window.vitalwearDB;

// Get all users by role
const managementUsers = db.getUsers('management');
const rescuerUsers = db.getUsers('rescuer');
const responderUsers = db.getUsers('responder');

// Device management
const devices = db.getDevices();
const device = db.getDeviceById(1);
db.assignDevice(1, 3, 'rescuer');
db.returnDevice(1);

// Incident management
const incidents = db.getIncidents();
const userIncidents = db.getIncidentsByUser(5, 'responder');
const newIncident = db.createIncident({
  patient_name: 'John Doe',
  patient_age: 45,
  patient_condition: 'Chest pain',
  responder_id: 5
});

// Vitals management
const vitals = db.getVitals();
const incidentVitals = db.getVitalsByIncident(1);
const newVital = db.addVital({
  incident_id: 1,
  heart_rate: 72,
  bp_systolic: 120,
  bp_diastolic: 80,
  oxygen_level: 98,
  temperature: 98.6
});

// Statistics
const stats = db.getStats();
```

### Session Management

```javascript
// Set current role
sessionStorage.setItem('current_role', 'management');

// Get current user
const user = getCurrentUser();

// Logout
function logout() {
  sessionStorage.clear();
  window.location.href = '../index.html';
}
```

## Role Permissions

### Management
- View all system statistics
- Manage user accounts
- Register and assign devices
- View reports and analytics
- Monitor system activity

### Rescuer
- View transferred incidents
- Monitor ongoing cases
- Record patient vitals
- Manage assigned devices
- View completed cases

### Responder
- Create new incidents
- Record vital signs
- Transfer incidents to rescuers
- View incident history
- Manage assigned devices

## Data Management

### Export Data
```javascript
const jsonData = db.exportData();
console.log(jsonData);
```

### Import Data
```javascript
const success = db.importData(jsonData);
if (success) {
  console.log('Data imported successfully');
}
```

### Reset Database
```javascript
db.clearAllData();
```

## Browser Compatibility

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Security Notes

- This is a demonstration system using browser local storage
- Data is stored locally and not transmitted to any server
- In production, use proper backend authentication and database
- Local storage data can be cleared by users or browser settings

## Development

### Adding New Pages

1. Create HTML file in appropriate role directory
2. Include the database script: `<script src="../js/database.js"></script>`
3. Add authentication check: `checkAuth();`
4. Use database API for data operations

### Styling Guidelines

- Use CSS custom properties defined in `:root`
- Follow the existing color scheme and spacing
- Maintain responsive design principles
- Use semantic HTML5 elements

### JavaScript Patterns

- Use the database class for all data operations
- Implement proper error handling
- Use async/await for complex operations
- Maintain consistent naming conventions

## Troubleshooting

### Data Not Persisting
- Check that browser supports local storage
- Ensure no private/incognito mode
- Verify no browser extensions blocking storage

### Navigation Issues
- Check file paths are correct
- Verify session storage is being used
- Ensure authentication checks are in place

### Display Issues
- Check CSS custom properties are loading
- Verify responsive breakpoints
- Test in different browsers

## License

This project is for demonstration purposes only.
