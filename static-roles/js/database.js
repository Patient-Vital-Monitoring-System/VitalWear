// VitalWear Static Database Simulation
// This file provides a complete JSON-based database simulation using localStorage

class VitalWearDatabase {
    constructor() {
        this.initializeDatabase();
    }

    // Initialize database with sample data if empty
    initializeDatabase() {
        if (!localStorage.getItem('vitalwear_data')) {
            const initialData = {
                users: {
                    management: [
                        { id: 1, name: 'John Manager', email: 'john@vitalwear.com', role: 'management', created_at: new Date().toISOString() },
                        { id: 2, name: 'Sarah Admin', email: 'sarah@vitalwear.com', role: 'management', created_at: new Date().toISOString() }
                    ],
                    rescuer: [
                        { id: 3, name: 'Mike Rescuer', email: 'mike@vitalwear.com', role: 'rescuer', created_at: new Date().toISOString() },
                        { id: 4, name: 'Emma Paramedic', email: 'emma@vitalwear.com', role: 'rescuer', created_at: new Date().toISOString() }
                    ],
                    responder: [
                        { id: 5, name: 'Alex Responder', email: 'alex@vitalwear.com', role: 'responder', created_at: new Date().toISOString() },
                        { id: 6, name: 'Lisa FirstAid', email: 'lisa@vitalwear.com', role: 'responder', created_at: new Date().toISOString() }
                    ]
                },
                devices: [
                    { id: 1, serial: 'VW001', status: 'available', assigned_to: null, created_at: new Date().toISOString() },
                    { id: 2, serial: 'VW002', status: 'assigned', assigned_to: 3, created_at: new Date().toISOString() },
                    { id: 3, serial: 'VW003', status: 'assigned', assigned_to: 5, created_at: new Date().toISOString() },
                    { id: 4, serial: 'VW004', status: 'maintenance', assigned_to: null, created_at: new Date().toISOString() },
                    { id: 5, serial: 'VW005', status: 'available', assigned_to: null, created_at: new Date().toISOString() },
                    { id: 6, serial: 'VW006', status: 'assigned', assigned_to: 6, created_at: new Date().toISOString() }
                ],
                incidents: [
                    { 
                        id: 1, 
                        patient_name: 'John Doe', 
                        patient_age: 45,
                        patient_condition: 'Chest pain',
                        status: 'ongoing', 
                        responder_id: 5, 
                        rescuer_id: null, 
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString()
                    },
                    { 
                        id: 2, 
                        patient_name: 'Jane Smith', 
                        patient_age: 32,
                        patient_condition: 'Difficulty breathing',
                        status: 'transferred', 
                        responder_id: 6, 
                        rescuer_id: 3, 
                        created_at: new Date(Date.now() - 3600000).toISOString(),
                        updated_at: new Date(Date.now() - 1800000).toISOString()
                    },
                    { 
                        id: 3, 
                        patient_name: 'Bob Johnson', 
                        patient_age: 67,
                        patient_condition: 'Fall injury',
                        status: 'completed', 
                        responder_id: 5, 
                        rescuer_id: 4, 
                        created_at: new Date(Date.now() - 7200000).toISOString(),
                        updated_at: new Date(Date.now() - 3600000).toISOString()
                    }
                ],
                vitals: [
                    { 
                        id: 1, 
                        incident_id: 1, 
                        heart_rate: 72, 
                        bp_systolic: 120, 
                        bp_diastolic: 80, 
                        oxygen_level: 98, 
                        temperature: 98.6,
                        recorded_at: new Date().toISOString() 
                    },
                    { 
                        id: 2, 
                        incident_id: 2, 
                        heart_rate: 85, 
                        bp_systolic: 135, 
                        bp_diastolic: 88, 
                        oxygen_level: 96,
                        temperature: 99.2,
                        recorded_at: new Date(Date.now() - 1800000).toISOString() 
                    },
                    { 
                        id: 3, 
                        incident_id: 1, 
                        heart_rate: 75, 
                        bp_systolic: 118, 
                        bp_diastolic: 78, 
                        oxygen_level: 97,
                        temperature: 98.4,
                        recorded_at: new Date(Date.now() - 900000).toISOString() 
                    }
                ],
                device_logs: [
                    {
                        id: 1,
                        device_id: 2,
                        user_id: 3,
                        user_role: 'rescuer',
                        date_assigned: new Date(Date.now() - 86400000).toISOString(),
                        date_returned: null
                    },
                    {
                        id: 2,
                        device_id: 3,
                        user_id: 5,
                        user_role: 'responder',
                        date_assigned: new Date(Date.now() - 43200000).toISOString(),
                        date_returned: null
                    },
                    {
                        id: 3,
                        device_id: 6,
                        user_id: 6,
                        user_role: 'responder',
                        date_assigned: new Date(Date.now() - 21600000).toISOString(),
                        date_returned: null
                    }
                ],
                activity_log: [
                    {
                        id: 1,
                        user_name: 'System',
                        user_role: 'system',
                        description: 'Database initialized',
                        created_at: new Date().toISOString()
                    },
                    {
                        id: 2,
                        user_name: 'Mike Rescuer',
                        user_role: 'rescuer',
                        description: 'Device VW002 assigned',
                        created_at: new Date(Date.now() - 86400000).toISOString()
                    },
                    {
                        id: 3,
                        user_name: 'Alex Responder',
                        user_role: 'responder',
                        description: 'Incident #1 created',
                        created_at: new Date().toISOString()
                    }
                ]
            };
            
            localStorage.setItem('vitalwear_data', JSON.stringify(initialData));
        }
    }

    // Get all data
    getData() {
        return JSON.parse(localStorage.getItem('vitalwear_data') || '{}');
    }

    // Save all data
    saveData(data) {
        localStorage.setItem('vitalwear_data', JSON.stringify(data));
    }

    // User management
    getUsers(role = null) {
        const data = this.getData();
        if (role) {
            return data.users?.[role] || [];
        }
        return {
            management: data.users?.management || [],
            rescuer: data.users?.rescuer || [],
            responder: data.users?.responder || []
        };
    }

    getUserById(id, role) {
        const users = this.getUsers(role);
        return users.find(user => user.id === parseInt(id));
    }

    addUser(userData) {
        const data = this.getData();
        const newId = Math.max(...this.getAllUserIds()) + 1;
        const newUser = {
            id: newId,
            ...userData,
            created_at: new Date().toISOString()
        };
        
        if (!data.users[userData.role]) {
            data.users[userData.role] = [];
        }
        
        data.users[userData.role].push(newUser);
        this.saveData(data);
        this.logActivity(userData.name, userData.role, 'User account created');
        return newUser;
    }

    updateUser(id, role, updateData) {
        const data = this.getData();
        const users = data.users[role];
        const userIndex = users.findIndex(user => user.id === parseInt(id));
        
        if (userIndex !== -1) {
            data.users[role][userIndex] = { ...users[userIndex], ...updateData };
            this.saveData(data);
            this.logActivity(updateData.name || users[userIndex].name, role, 'User account updated');
            return data.users[role][userIndex];
        }
        return null;
    }

    deleteUser(id, role) {
        const data = this.getData();
        const users = data.users[role];
        const userIndex = users.findIndex(user => user.id === parseInt(id));
        
        if (userIndex !== -1) {
            const deletedUser = users[userIndex];
            data.users[role].splice(userIndex, 1);
            this.saveData(data);
            this.logActivity(deletedUser.name, role, 'User account deleted');
            return true;
        }
        return false;
    }

    getAllUserIds() {
        const allUsers = this.getUsers();
        return [
            ...allUsers.management.map(u => u.id),
            ...allUsers.rescuer.map(u => u.id),
            ...allUsers.responder.map(u => u.id)
        ];
    }

    // Device management
    getDevices() {
        const data = this.getData();
        return data.devices || [];
    }

    getDeviceById(id) {
        const devices = this.getDevices();
        return devices.find(device => device.id === parseInt(id));
    }

    addDevice(deviceData) {
        const data = this.getData();
        const newId = Math.max(...this.getDevices().map(d => d.id), 0) + 1;
        const newDevice = {
            id: newId,
            ...deviceData,
            created_at: new Date().toISOString()
        };
        
        data.devices.push(newDevice);
        this.saveData(data);
        this.logActivity('System', 'system', `Device ${newDevice.serial} registered`);
        return newDevice;
    }

    updateDevice(id, updateData) {
        const data = this.getData();
        const devices = data.devices;
        const deviceIndex = devices.findIndex(device => device.id === parseInt(id));
        
        if (deviceIndex !== -1) {
            data.devices[deviceIndex] = { ...devices[deviceIndex], ...updateData };
            this.saveData(data);
            
            const user = getCurrentUser();
            this.logActivity(user?.name || 'Unknown', user?.role || 'system', `Device ${devices[deviceIndex].serial} updated`);
            return data.devices[deviceIndex];
        }
        return null;
    }

    // Delete device
    deleteDevice(id) {
        const data = this.getData();
        const devices = data.devices;
        const deviceIndex = devices.findIndex(device => device.id === parseInt(id));
        
        if (deviceIndex !== -1) {
            const deletedDevice = devices[deviceIndex];
            data.devices.splice(deviceIndex, 1);
            this.saveData(data);
            
            const user = getCurrentUser();
            this.logActivity(user?.name || 'Unknown', user?.role || 'system', `Device ${deletedDevice.serial} deleted`);
            return true;
        }
        return false;
    }

    assignDevice(deviceId, userId, userRole) {
        const data = this.getData();
        const device = this.getDeviceById(deviceId);
        const user = this.getUserById(userId, userRole);
        
        if (device && user) {
            // Update device status
            this.updateDevice(deviceId, { 
                status: 'assigned', 
                assigned_to: parseInt(userId) 
            });
            
            // Add to device logs
            const deviceLog = {
                id: Math.max(...(data.device_logs?.map(l => l.id) || [0]), 0) + 1,
                device_id: parseInt(deviceId),
                user_id: parseInt(userId),
                user_role: userRole,
                date_assigned: new Date().toISOString(),
                date_returned: null
            };
            
            if (!data.device_logs) data.device_logs = [];
            data.device_logs.push(deviceLog);
            this.saveData(data);
            
            this.logActivity(user.name, userRole, `Device ${device.serial} assigned`);
            return true;
        }
        return false;
    }

    returnDevice(deviceId) {
        const data = this.getData();
        const device = this.getDeviceById(deviceId);
        
        if (device && device.assigned_to) {
            // Find the device log
            const logIndex = data.device_logs?.findIndex(log => 
                log.device_id === parseInt(deviceId) && log.date_returned === null
            );
            
            if (logIndex !== -1) {
                // Update device log
                data.device_logs[logIndex].date_returned = new Date().toISOString();
                
                // Update device status
                this.updateDevice(deviceId, { 
                    status: 'available', 
                    assigned_to: null 
                });
                
                this.saveData(data);
                this.logActivity('System', 'system', `Device ${device.serial} returned`);
                return true;
            }
        }
        return false;
    }

    // Incident management
    getIncidents() {
        const data = this.getData();
        return data.incidents || [];
    }

    getIncidentsByUser(userId, role) {
        const incidents = this.getIncidents();
        return incidents.filter(incident => {
            if (role === 'responder') return incident.responder_id === parseInt(userId);
            if (role === 'rescuer') return incident.rescuer_id === parseInt(userId);
            return false;
        });
    }

    getIncidentById(id) {
        const incidents = this.getIncidents();
        return incidents.find(incident => incident.id === parseInt(id));
    }

    createIncident(incidentData) {
        const data = this.getData();
        const newId = Math.max(...this.getIncidents().map(i => i.id), 0) + 1;
        const newIncident = {
            id: newId,
            ...incidentData,
            status: 'ongoing',
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
        };
        
        data.incidents.push(newIncident);
        this.saveData(data);
        
        const user = this.getUserById(incidentData.responder_id, 'responder');
        this.logActivity(user?.name || 'Unknown', 'responder', `Incident #${newId} created`);
        return newIncident;
    }

    updateIncident(id, updateData) {
        const data = this.getData();
        const incidents = data.incidents;
        const incidentIndex = incidents.findIndex(incident => incident.id === parseInt(id));
        
        if (incidentIndex !== -1) {
            data.incidents[incidentIndex] = { 
                ...incidents[incidentIndex], 
                ...updateData,
                updated_at: new Date().toISOString()
            };
            this.saveData(data);
            
            // Log activity with user info
            const user = getCurrentUser();
            this.logActivity(user?.name || 'Unknown', user?.role || 'system', `Incident #${id} updated`);
            
            return data.incidents[incidentIndex];
        }
        return null;
    }

    transferIncident(incidentId, rescuerId) {
        const incident = this.getIncidentById(incidentId);
        const rescuer = this.getUserById(rescuerId, 'rescuer');
        
        if (incident && rescuer) {
            const success = this.updateIncident(incidentId, {
                status: 'transferred',
                rescuer_id: parseInt(rescuerId)
            });
            
            if (success) {
                this.logActivity(rescuer.name, 'rescuer', `Incident #${incidentId} transferred`);
                return true;
            }
        }
        return false;
    }

    completeIncident(id) {
        const success = this.updateIncident(id, { status: 'completed' });
        if (success) {
            const user = getCurrentUser();
            this.logActivity(user?.name || 'Unknown', user?.role || 'system', `Incident #${id} completed`);
        }
        return success;
    }

    // Delete incident
    deleteIncident(id) {
        const data = this.getData();
        const incidents = data.incidents;
        const incidentIndex = incidents.findIndex(incident => incident.id === parseInt(id));
        
        if (incidentIndex !== -1) {
            const deletedIncident = incidents[incidentIndex];
            data.incidents.splice(incidentIndex, 1);
            this.saveData(data);
            
            const user = getCurrentUser();
            this.logActivity(user?.name || 'Unknown', user?.role || 'system', `Incident #${id} deleted`);
            return true;
        }
        return false;
    }

    // Vitals management
    getVitals() {
        const data = this.getData();
        return data.vitals || [];
    }

    getVitalsByIncident(incidentId) {
        const vitals = this.getVitals();
        return vitals.filter(vital => vital.incident_id === parseInt(incidentId));
    }

    addVital(vitalData) {
        const data = this.getData();
        const newId = Math.max(...this.getVitals().map(v => v.id), 0) + 1;
        const newVital = {
            id: newId,
            ...vitalData,
            recorded_at: new Date().toISOString()
        };
        
        data.vitals.push(newVital);
        this.saveData(data);
        this.logActivity('System', 'system', `Vitals recorded for incident #${vitalData.incident_id}`);
        return newVital;
    }

    // Activity logging
    logActivity(userName, userRole, description) {
        const data = this.getData();
        const newActivity = {
            id: Math.max(...(data.activity_log?.map(a => a.id) || [0]), 0) + 1,
            user_name: userName,
            user_role: userRole,
            description: description,
            created_at: new Date().toISOString()
        };
        
        if (!data.activity_log) data.activity_log = [];
        data.activity_log.unshift(newActivity); // Add to beginning
        
        // Keep only last 100 activities
        if (data.activity_log.length > 100) {
            data.activity_log = data.activity_log.slice(0, 100);
        }
        
        this.saveData(data);
    }

    getRecentActivities(limit = 10) {
        const data = this.getData();
        return (data.activity_log || []).slice(0, limit);
    }

    // Statistics and reports
    getStats() {
        const data = this.getData();
        const devices = data.devices || [];
        const incidents = data.incidents || [];
        const users = this.getUsers();
        
        return {
            totalDevices: devices.length,
            availableDevices: devices.filter(d => d.status === 'available').length,
            assignedDevices: devices.filter(d => d.status === 'assigned').length,
            maintenanceDevices: devices.filter(d => d.status === 'maintenance').length,
            totalUsers: users.management.length + users.rescuer.length + users.responder.length,
            activeIncidents: incidents.filter(i => i.status === 'ongoing' || i.status === 'transferred').length,
            completedIncidents: incidents.filter(i => i.status === 'completed').length,
            transferredIncidents: incidents.filter(i => i.status === 'transferred').length,
            totalVitals: (data.vitals || []).length
        };
    }

    // Export/Import functionality
    exportData() {
        return JSON.stringify(this.getData(), null, 2);
    }

    importData(jsonData) {
        try {
            const data = JSON.parse(jsonData);
            this.saveData(data);
            return true;
        } catch (error) {
            console.error('Import error:', error);
            return false;
        }
    }

    // Clear all data (for testing/reset)
    clearAllData() {
        localStorage.removeItem('vitalwear_data');
        this.initializeDatabase();
    }
}

// Create global database instance
window.vitalwearDB = new VitalWearDatabase();

// Helper functions for backward compatibility
function getDatabase() {
    return window.vitalwearDB.getData();
}

function saveDatabase(data) {
    return window.vitalwearDB.saveData(data);
}

function getCurrentUser() {
    const role = sessionStorage.getItem('current_role');
    return window.vitalwearDB.getUsers(role)[0] || { name: 'User', role: role };
}
