import React, { useState, useEffect } from 'react';
import {
  Box,
  Typography,
  Button,
  Card,
  CardContent,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  Chip,
  IconButton,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  Alert,
  CircularProgress,
  Switch,
  FormControlLabel,
} from '@mui/material';
import {
  Add as AddIcon,
  Edit as EditIcon,
  Delete as DeleteIcon,
  PersonAdd as PersonAddIcon,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import axios from 'axios';
import API_BASE_URL from '../config/api';

const StaffManagement = () => {
  const { user } = useAuth();
  const [staff, setStaff] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingStaff, setEditingStaff] = useState(null);
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    role: 'staff',
    department_id: '',
    is_active: true,
  });
  const [departments, setDepartments] = useState([]);

  useEffect(() => {
    if (user?.role === 'admin') {
      fetchStaff();
      fetchDepartments();
    }
  }, [user]);

  const fetchStaff = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get(`${API_BASE_URL}/staff/list.php`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      
      if (response.data.success) {
        setStaff(response.data.staff);
      } else {
        setError('Failed to load staff');
      }
    } catch (error) {
      console.error('Staff error:', error);
      setError('Failed to load staff');
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

  const handleSubmit = async () => {
    try {
      const token = localStorage.getItem('token');
      const url = editingStaff 
        ? `${API_BASE_URL}/staff/update.php?id=${editingStaff.id}`
        : `${API_BASE_URL}/staff/create.php`;
      
      const method = editingStaff ? 'PUT' : 'POST';
      
      const response = await axios({
        method,
        url,
        data: formData,
        headers: { Authorization: `Bearer ${token}` }
      });

      if (response.data.success) {
        setDialogOpen(false);
        setEditingStaff(null);
        setFormData({ name: '', email: '', password: '', role: 'staff', is_active: true });
        fetchStaff();
        setError('');
      } else {
        setError(response.data.message || 'Operation failed');
      }
    } catch (error) {
      console.error('Staff operation error:', error);
      setError('Operation failed. Please try again.');
    }
  };

  const handleEdit = (staffMember) => {
    setEditingStaff(staffMember);
    setFormData({
      name: staffMember.name,
      email: staffMember.email,
      password: '',
      role: staffMember.role,
      department_id: staffMember.department_id || '',
      is_active: staffMember.is_active,
    });
    setDialogOpen(true);
  };

  const handleDelete = async (staffId) => {
    if (window.confirm('Are you sure you want to delete this staff member?')) {
      try {
        const token = localStorage.getItem('token');
        const response = await axios.delete(`${API_BASE_URL}/staff/delete.php?id=${staffId}`, {
          headers: { Authorization: `Bearer ${token}` }
        });

        if (response.data.success) {
          fetchStaff();
        } else {
          setError(response.data.message || 'Delete failed');
        }
      } catch (error) {
        console.error('Delete error:', error);
        setError('Delete failed. Please try again.');
      }
    }
  };

  const handleToggleStatus = async (staffId, currentStatus) => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.put(`${API_BASE_URL}/staff/toggle.php?id=${staffId}`, {
        is_active: !currentStatus
      }, {
        headers: { Authorization: `Bearer ${token}` }
      });

      if (response.data.success) {
        fetchStaff();
      } else {
        setError(response.data.message || 'Status update failed');
      }
    } catch (error) {
      console.error('Status toggle error:', error);
      setError('Status update failed. Please try again.');
    }
  };

  if (user?.role !== 'admin') {
    return (
      <Alert severity="error">
        Access denied. Admin privileges required.
      </Alert>
    );
  }

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="400px">
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">
          Staff Management
        </Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
        setEditingStaff(null);
        setFormData({ name: '', email: '', password: '', role: 'staff', department_id: '', is_active: true });
        setDialogOpen(true);
          }}
        >
          Add Staff Member
        </Button>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError('')}>
          {error}
        </Alert>
      )}

      <Card>
        <CardContent>
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>Name</TableCell>
                  <TableCell>Email</TableCell>
                  <TableCell>Role</TableCell>
                  <TableCell>Department</TableCell>
                  <TableCell>Status</TableCell>
                  <TableCell>Created</TableCell>
                  <TableCell>Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {staff.map((member) => (
                  <TableRow key={member.id}>
                    <TableCell>{member.name}</TableCell>
                    <TableCell>{member.email}</TableCell>
                    <TableCell>
                      <Chip
                        label={member.role}
                        color={member.role === 'admin' ? 'primary' : 'default'}
                        size="small"
                        sx={{ textTransform: 'capitalize' }}
                      />
                    </TableCell>
                    <TableCell>
                      <Chip
                        label={member.department_name || 'No Department'}
                        color="secondary"
                        variant="outlined"
                        size="small"
                      />
                    </TableCell>
                    <TableCell>
                      <FormControlLabel
                        control={
                          <Switch
                            checked={member.is_active}
                            onChange={() => handleToggleStatus(member.id, member.is_active)}
                            color="primary"
                          />
                        }
                        label={member.is_active ? 'Active' : 'Inactive'}
                      />
                    </TableCell>
                    <TableCell>
                      {new Date(member.created_at).toLocaleDateString()}
                    </TableCell>
                    <TableCell>
                      <IconButton
                        size="small"
                        onClick={() => handleEdit(member)}
                        color="primary"
                      >
                        <EditIcon />
                      </IconButton>
                      <IconButton
                        size="small"
                        onClick={() => handleDelete(member.id)}
                        color="error"
                        disabled={member.id === user.id}
                      >
                        <DeleteIcon />
                      </IconButton>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </CardContent>
      </Card>

      {/* Add/Edit Dialog */}
      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>
          {editingStaff ? 'Edit Staff Member' : 'Add New Staff Member'}
        </DialogTitle>
        <DialogContent>
          <TextField
            fullWidth
            label="Full Name"
            value={formData.name}
            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
            margin="normal"
            required
          />
          <TextField
            fullWidth
            label="Email"
            type="email"
            value={formData.email}
            onChange={(e) => setFormData({ ...formData, email: e.target.value })}
            margin="normal"
            required
          />
          <TextField
            fullWidth
            label="Password"
            type="password"
            value={formData.password}
            onChange={(e) => setFormData({ ...formData, password: e.target.value })}
            margin="normal"
            required={!editingStaff}
            helperText={editingStaff ? "Leave blank to keep current password" : ""}
          />
          <TextField
            fullWidth
            select
            label="Role"
            value={formData.role}
            onChange={(e) => setFormData({ ...formData, role: e.target.value })}
            margin="normal"
            SelectProps={{ native: true }}
          >
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
          </TextField>
          <TextField
            fullWidth
            select
            label="Department"
            value={formData.department_id}
            onChange={(e) => setFormData({ ...formData, department_id: e.target.value })}
            margin="normal"
            SelectProps={{ native: true }}
          >
            <option value="">No Department</option>
            {departments.map((dept) => (
              <option key={dept.id} value={dept.id}>
                {dept.name}
              </option>
            ))}
          </TextField>
          <FormControlLabel
            control={
              <Switch
                checked={formData.is_active}
                onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                color="primary"
              />
            }
            label="Active"
            sx={{ mt: 2 }}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialogOpen(false)}>Cancel</Button>
          <Button onClick={handleSubmit} variant="contained">
            {editingStaff ? 'Update' : 'Create'}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default StaffManagement;
