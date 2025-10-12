import React, { useState } from 'react';
import {
  Box,
  Typography,
  Card,
  CardContent,
  Grid,
  Button,
  Alert,
  CircularProgress,
  List,
  ListItem,
  ListItemText,
  ListItemIcon,
  Divider,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
} from '@mui/material';
import {
  Backup as BackupIcon,
  Restore as RestoreIcon,
  Storage as DatabaseIcon,
  Download as DownloadIcon,
  Upload as UploadIcon,
  CheckCircle as SuccessIcon,
  Error as ErrorIcon,
} from '@mui/icons-material';
import axios from 'axios';
import API_BASE_URL from '../config/api';

const DatabaseManagement = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [backupHistory, setBackupHistory] = useState([]);
  const [restoreDialogOpen, setRestoreDialogOpen] = useState(false);
  const [selectedFile, setSelectedFile] = useState(null);

  const handleCreateBackup = async () => {
    setLoading(true);
    setError('');
    setSuccess('');

    try {
      const token = localStorage.getItem('token');
      const response = await axios.post(`${API_BASE_URL}/admin/backup.php`, {}, {
        headers: { Authorization: `Bearer ${token}` }
      });

      if (response.data.success) {
        setSuccess('Database backup created successfully!');
        fetchBackupHistory();
      } else {
        setError(response.data.message || 'Failed to create backup');
      }
    } catch (error) {
      console.error('Backup error:', error);
      setError('Failed to create backup. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const fetchBackupHistory = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get(`${API_BASE_URL}/admin/backups.php`, {
        headers: { Authorization: `Bearer ${token}` }
      });

      if (response.data.success) {
        setBackupHistory(response.data.backups);
      }
    } catch (error) {
      console.error('Backup history error:', error);
    }
  };

  const handleDownloadBackup = async (backupFile) => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get(`${API_BASE_URL}/admin/download-backup.php?file=${backupFile}`, {
        headers: { Authorization: `Bearer ${token}` },
        responseType: 'blob'
      });

      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', backupFile);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Download backup error:', error);
      setError('Failed to download backup file.');
    }
  };

  const handleRestoreBackup = async () => {
    if (!selectedFile) {
      setError('Please select a backup file to restore.');
      return;
    }

    setLoading(true);
    setError('');
    setSuccess('');

    try {
      const formData = new FormData();
      formData.append('backup_file', selectedFile);

      const token = localStorage.getItem('token');
      const response = await axios.post(`${API_BASE_URL}/admin/restore.php`, formData, {
        headers: { 
          Authorization: `Bearer ${token}`,
          'Content-Type': 'multipart/form-data'
        }
      });

      if (response.data.success) {
        setSuccess('Database restored successfully!');
        setRestoreDialogOpen(false);
        setSelectedFile(null);
      } else {
        setError(response.data.message || 'Failed to restore backup');
      }
    } catch (error) {
      console.error('Restore error:', error);
      setError('Failed to restore backup. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const handleFileSelect = (event) => {
    const file = event.target.files[0];
    setSelectedFile(file);
  };

  React.useEffect(() => {
    fetchBackupHistory();
  }, []);

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
        {/* Backup Actions */}
        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Database Backup
              </Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
                Create a backup of your database to prevent data loss.
              </Typography>
              
              <Box sx={{ display: 'flex', gap: 2, mb: 3 }}>
                <Button
                  variant="contained"
                  startIcon={<BackupIcon />}
                  onClick={handleCreateBackup}
                  disabled={loading}
                >
                  {loading ? 'Creating...' : 'Create Backup'}
                </Button>
              </Box>

              <Divider sx={{ my: 2 }} />

              <Typography variant="h6" gutterBottom>
                Restore Database
              </Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                Restore database from a backup file.
              </Typography>
              
              <Button
                variant="outlined"
                startIcon={<RestoreIcon />}
                onClick={() => setRestoreDialogOpen(true)}
                disabled={loading}
              >
                Restore from File
              </Button>
            </CardContent>
          </Card>
        </Grid>

        {/* Backup History */}
        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Backup History
              </Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                Recent database backups
              </Typography>

              {backupHistory.length > 0 ? (
                <List>
                  {backupHistory.map((backup, index) => (
                    <ListItem key={index} divider>
                      <ListItemIcon>
                        <DatabaseIcon />
                      </ListItemIcon>
                      <ListItemText
                        primary={backup.filename}
                        secondary={`Created: ${new Date(backup.created_at).toLocaleString()}`}
                      />
                      <Button
                        size="small"
                        startIcon={<DownloadIcon />}
                        onClick={() => handleDownloadBackup(backup.filename)}
                      >
                        Download
                      </Button>
                    </ListItem>
                  ))}
                </List>
              ) : (
                <Typography variant="body2" color="text.secondary">
                  No backups available
                </Typography>
              )}
            </CardContent>
          </Card>
        </Grid>

        {/* Database Info */}
        <Grid item xs={12}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Database Information
              </Typography>
              <Grid container spacing={2}>
                <Grid item xs={12} sm={6} md={3}>
                  <Box textAlign="center">
                    <Typography variant="h4" color="primary">
                      {backupHistory.length}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      Total Backups
                    </Typography>
                  </Box>
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <Box textAlign="center">
                    <Typography variant="h4" color="success.main">
                      Active
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      Database Status
                    </Typography>
                  </Box>
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <Box textAlign="center">
                    <Typography variant="h4" color="info.main">
                      MySQL
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      Database Type
                    </Typography>
                  </Box>
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <Box textAlign="center">
                    <Typography variant="h4" color="warning.main">
                      Auto
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      Backup Mode
                    </Typography>
                  </Box>
                </Grid>
              </Grid>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Restore Dialog */}
      <Dialog open={restoreDialogOpen} onClose={() => setRestoreDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Restore Database</DialogTitle>
        <DialogContent>
          <Alert severity="warning" sx={{ mb: 2 }}>
            Warning: This will replace all current data with the backup data. This action cannot be undone.
          </Alert>
          
          <TextField
            fullWidth
            type="file"
            label="Select Backup File"
            onChange={handleFileSelect}
            margin="normal"
            InputLabelProps={{ shrink: true }}
            inputProps={{ accept: '.sql' }}
          />
          
          {selectedFile && (
            <Alert severity="info" sx={{ mt: 2 }}>
              Selected file: {selectedFile.name}
            </Alert>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRestoreDialogOpen(false)}>Cancel</Button>
          <Button 
            onClick={handleRestoreBackup} 
            variant="contained" 
            color="error"
            disabled={!selectedFile || loading}
          >
            {loading ? 'Restoring...' : 'Restore Database'}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default DatabaseManagement;
