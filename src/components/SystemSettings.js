import React, { useState, useEffect } from 'react';
import {
  Box,
  Typography,
  Card,
  CardContent,
  Grid,
  TextField,
  Button,
  Switch,
  FormControlLabel,
  Divider,
  Alert,
  CircularProgress,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  List,
  ListItem,
  ListItemText,
  ListItemSecondaryAction,
  IconButton,
} from '@mui/material';
import {
  Add as AddIcon,
  Edit as EditIcon,
  Delete as DeleteIcon,
  Save as SaveIcon,
} from '@mui/icons-material';
import axios from 'axios';
import API_BASE_URL from '../config/api';

const SystemSettings = () => {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  
  // System settings
  const [settings, setSettings] = useState({
    systemName: 'Document Tracking System',
    maxFileSize: 10, // MB
    allowedFileTypes: 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
    autoBackup: true,
    backupFrequency: 'daily',
    emailNotifications: true,
    sessionTimeout: 30, // minutes
  });

  // Departments management
  const [departments, setDepartments] = useState([]);
  const [newDepartment, setNewDepartment] = useState({ name: '', description: '' });
  const [editingDepartment, setEditingDepartment] = useState(null);
  const [departmentDialogOpen, setDepartmentDialogOpen] = useState(false);

  useEffect(() => {
    fetchSettings();
    fetchDepartments();
  }, []);

  const fetchSettings = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get(`${API_BASE_URL}/admin/settings.php`, {
        headers: { Authorization: `Bearer ${token}` }
      });

      if (response.data.success) {
        setSettings({ ...settings, ...response.data.settings });
      }
    } catch (error) {
      console.error('Settings error:', error);
    } finally {
      setLoading(false);
    }
  };

  const fetchDepartments = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get(`${API_BASE_URL}/departments/list.php`, {
        headers: { Authorization: `Bearer ${token}` }
      });

      if (response.data.success) {
        setDepartments(response.data.departments);
      }
    } catch (error) {
      console.error('Departments error:', error);
    }
  };

  const handleSaveSettings = async () => {
    setSaving(true);
    setError('');
    setSuccess('');

    try {
      const token = localStorage.getItem('token');
      const response = await axios.post(`${API_BASE_URL}/admin/settings.php`, settings, {
        headers: { Authorization: `Bearer ${token}` }
      });

      if (response.data.success) {
        setSuccess('Settings saved successfully!');
      } else {
        setError(response.data.message || 'Failed to save settings');
      }
    } catch (error) {
      console.error('Save settings error:', error);
      setError('Failed to save settings. Please try again.');
    } finally {
      setSaving(false);
    }
  };

  const handleAddDepartment = () => {
    setNewDepartment({ name: '', description: '' });
    setEditingDepartment(null);
    setDepartmentDialogOpen(true);
  };

  const handleEditDepartment = (department) => {
    setNewDepartment({ name: department.name, description: department.description || '' });
    setEditingDepartment(department);
    setDepartmentDialogOpen(true);
  };

  const handleSaveDepartment = async () => {
    if (!newDepartment.name.trim()) {
      setError('Department name is required');
      return;
    }

    try {
      const token = localStorage.getItem('token');
      const url = editingDepartment 
        ? `${API_BASE_URL}/departments/update.php`
        : `${API_BASE_URL}/departments/create.php`;
      
      const data = editingDepartment 
        ? { id: editingDepartment.id, ...newDepartment }
        : newDepartment;

      const response = await axios.post(url, data, {
        headers: { 
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      console.log('Department API Response:', response.data);
      
      if (response.data.success) {
        setDepartmentDialogOpen(false);
        fetchDepartments();
        setSuccess(editingDepartment ? 'Department updated successfully!' : 'Department created successfully!');
      } else {
        setError(response.data.message || 'Failed to save department');
      }
    } catch (error) {
      console.error('Department error:', error);
      console.error('Error response:', error.response?.data);
      if (error.response?.data?.message) {
        setError(error.response.data.message);
      } else {
        setError('Failed to save department. Please try again.');
      }
    }
  };

  const handleDeleteDepartment = async (departmentId) => {
    if (!window.confirm('Are you sure you want to delete this department?')) {
      return;
    }

    try {
      const token = localStorage.getItem('token');
      const response = await axios.post(`${API_BASE_URL}/departments/delete.php`, 
        { id: departmentId },
        { 
          headers: { 
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json'
          } 
        }
      );

      console.log('Delete API Response:', response.data);

      if (response.data.success) {
        fetchDepartments();
        setSuccess('Department deleted successfully!');
      } else {
        setError(response.data.message || 'Failed to delete department');
      }
    } catch (error) {
      console.error('Delete department error:', error);
      console.error('Error response:', error.response?.data);
      if (error.response?.data?.message) {
        setError(error.response.data.message);
      } else {
        setError('Failed to delete department. Please try again.');
      }
    }
  };

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="400px">
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Box>
      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError('')}>
          {error}
        </Alert>
      )}
      {success && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setSuccess('')}>
          {success}
        </Alert>
      )}

      <Grid container spacing={3}>
        {/* System Settings */}
        <Grid item xs={12} md={8}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                System Configuration
              </Typography>
              
              <Grid container spacing={2}>
                <Grid item xs={12} sm={6}>
                  <TextField
                    fullWidth
                    label="System Name"
                    value={settings.systemName}
                    onChange={(e) => setSettings({ ...settings, systemName: e.target.value })}
                    margin="normal"
                  />
                </Grid>
                <Grid item xs={12} sm={6}>
                  <TextField
                    fullWidth
                    label="Max File Size (MB)"
                    type="number"
                    value={settings.maxFileSize}
                    onChange={(e) => setSettings({ ...settings, maxFileSize: parseInt(e.target.value) })}
                    margin="normal"
                  />
                </Grid>
                <Grid item xs={12}>
                  <TextField
                    fullWidth
                    label="Allowed File Types"
                    value={settings.allowedFileTypes}
                    onChange={(e) => setSettings({ ...settings, allowedFileTypes: e.target.value })}
                    margin="normal"
                    helperText="Comma-separated file extensions (e.g., pdf,doc,docx)"
                  />
                </Grid>
                <Grid item xs={12} sm={6}>
                  <TextField
                    fullWidth
                    label="Session Timeout (minutes)"
                    type="number"
                    value={settings.sessionTimeout}
                    onChange={(e) => setSettings({ ...settings, sessionTimeout: parseInt(e.target.value) })}
                    margin="normal"
                  />
                </Grid>
                <Grid item xs={12} sm={6}>
                  <TextField
                    fullWidth
                    select
                    label="Backup Frequency"
                    value={settings.backupFrequency}
                    onChange={(e) => setSettings({ ...settings, backupFrequency: e.target.value })}
                    margin="normal"
                    SelectProps={{ native: true }}
                  >
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                  </TextField>
                </Grid>
                <Grid item xs={12}>
                  <FormControlLabel
                    control={
                      <Switch
                        checked={settings.autoBackup}
                        onChange={(e) => setSettings({ ...settings, autoBackup: e.target.checked })}
                      />
                    }
                    label="Enable Automatic Backup"
                  />
                </Grid>
                <Grid item xs={12}>
                  <FormControlLabel
                    control={
                      <Switch
                        checked={settings.emailNotifications}
                        onChange={(e) => setSettings({ ...settings, emailNotifications: e.target.checked })}
                      />
                    }
                    label="Enable Email Notifications"
                  />
                </Grid>
              </Grid>

              <Box sx={{ mt: 3, display: 'flex', gap: 2 }}>
                <Button
                  variant="contained"
                  startIcon={<SaveIcon />}
                  onClick={handleSaveSettings}
                  disabled={saving}
                >
                  {saving ? 'Saving...' : 'Save Settings'}
                </Button>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        {/* Departments Management */}
        <Grid item xs={12} md={4}>
          <Card>
            <CardContent>
              <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h6">
                  Departments
                </Typography>
                <Button
                  variant="outlined"
                  size="small"
                  startIcon={<AddIcon />}
                  onClick={handleAddDepartment}
                >
                  Add
                </Button>
              </Box>

              <List>
                {departments.map((dept) => (
                  <ListItem key={dept.id} divider>
                    <ListItemText
                      primary={dept.name}
                      secondary={dept.description}
                    />
                    <ListItemSecondaryAction>
                      <IconButton
                        size="small"
                        onClick={() => handleEditDepartment(dept)}
                      >
                        <EditIcon />
                      </IconButton>
                      <IconButton
                        size="small"
                        onClick={() => handleDeleteDepartment(dept.id)}
                        color="error"
                      >
                        <DeleteIcon />
                      </IconButton>
                    </ListItemSecondaryAction>
                  </ListItem>
                ))}
              </List>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Department Dialog */}
      <Dialog open={departmentDialogOpen} onClose={() => setDepartmentDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>
          {editingDepartment ? 'Edit Department' : 'Add New Department'}
        </DialogTitle>
        <DialogContent>
          <TextField
            fullWidth
            label="Department Name"
            value={newDepartment.name}
            onChange={(e) => setNewDepartment({ ...newDepartment, name: e.target.value })}
            margin="normal"
            required
          />
          <TextField
            fullWidth
            label="Description"
            value={newDepartment.description}
            onChange={(e) => setNewDepartment({ ...newDepartment, description: e.target.value })}
            margin="normal"
            multiline
            rows={3}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDepartmentDialogOpen(false)}>Cancel</Button>
          <Button onClick={handleSaveDepartment} variant="contained">
            {editingDepartment ? 'Update' : 'Create'}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default SystemSettings;
