import React, { useState, useEffect } from 'react';
import {
  Box,
  Grid,
  Card,
  CardContent,
  Typography,
  Paper,
  List,
  ListItem,
  ListItemText,
  ListItemIcon,
  Chip,
  CircularProgress,
  Alert,
    Button,
} from '@mui/material';
import {
  Description as DocumentIcon,
  Upload as UploadIcon,
  CheckCircle as ReceivedIcon,
  Pending as PendingIcon,
  TrendingUp as TrendingIcon,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import API_BASE_URL from '../config/api';

const Dashboard = () => {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [stats, setStats] = useState({
    totalDocuments: 0,
    outgoingDocuments: 0,
    pendingDocuments: 0,
    receivedDocuments: 0,
  });
  const [recentDocuments, setRecentDocuments] = useState([]);
  const [userDepartment, setUserDepartment] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    try {
      const token = localStorage.getItem('token');
      
      // Get user info with department
      const userResponse = await axios.get(`${API_BASE_URL}/auth/verify.php`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      
      if (userResponse.data.success) {
        setUserDepartment(userResponse.data.user.department_name);
      }
      
      // Get dashboard stats
      const statsResponse = await axios.get(`${API_BASE_URL}/dashboard/stats.php`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      
      if (statsResponse.data.success) {
        setStats(statsResponse.data.stats);
        setRecentDocuments(statsResponse.data.recentDocuments || []);
      } else {
        setError('Failed to load dashboard data');
      }
    } catch (error) {
      console.error('Dashboard error:', error);
      setError('Failed to load dashboard data');
    } finally {
      setLoading(false);
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'outgoing': return 'primary';
      case 'pending': return 'warning';
      case 'received': return 'success';
      default: return 'default';
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'outgoing': return <UploadIcon />;
      case 'pending': return <PendingIcon />;
      case 'received': return <ReceivedIcon />;
      default: return <DocumentIcon />;
    }
  };

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="400px">
        <CircularProgress />
      </Box>
    );
  }

  if (error) {
    return (
      <Alert severity="error" sx={{ mb: 2 }}>
        {error}
      </Alert>
    );
  }

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        Dashboard
      </Typography>
      <Typography variant="body1" color="text.secondary" sx={{ mb: 3 }}>
        Welcome back, {user?.name}! 
        {userDepartment && (
          <span> You're viewing documents for the <strong>{userDepartment}</strong> department.</span>
        )}
        {!userDepartment && user?.role === 'staff' && (
          <span> You're not assigned to any department yet.</span>
        )}
      </Typography>

      <Grid container spacing={3}>
        {/* Stats Cards */}
        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Box display="flex" alignItems="center">
                <Box
                  sx={{
                    p: 2,
                    borderRadius: 2,
                    backgroundColor: 'primary.main',
                    color: 'white',
                    mr: 2,
                  }}
                >
                  <DocumentIcon />
                </Box>
                <Box>
                  <Typography variant="h4" component="div">
                    {stats.totalDocuments}
                  </Typography>
                  <Typography color="text.secondary">
                    Total Documents
                  </Typography>
                </Box>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Box display="flex" alignItems="center">
                <Box
                  sx={{
                    p: 2,
                    borderRadius: 2,
                    backgroundColor: 'primary.light',
                    color: 'white',
                    mr: 2,
                  }}
                >
                  <UploadIcon />
                </Box>
                <Box>
                  <Typography variant="h4" component="div">
                    {stats.outgoingDocuments}
                  </Typography>
                  <Typography color="text.secondary">
                    Outgoing
                  </Typography>
                </Box>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Box display="flex" alignItems="center">
                <Box
                  sx={{
                    p: 2,
                    borderRadius: 2,
                    backgroundColor: 'warning.main',
                    color: 'white',
                    mr: 2,
                  }}
                >
                  <PendingIcon />
                </Box>
                <Box>
                  <Typography variant="h4" component="div">
                    {stats.pendingDocuments}
                  </Typography>
                  <Typography color="text.secondary">
                    Pending
                  </Typography>
                </Box>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Box display="flex" alignItems="center">
                <Box
                  sx={{
                    p: 2,
                    borderRadius: 2,
                    backgroundColor: 'success.main',
                    color: 'white',
                    mr: 2,
                  }}
                >
                  <ReceivedIcon />
                </Box>
                <Box>
                  <Typography variant="h4" component="div">
                    {stats.receivedDocuments}
                  </Typography>
                  <Typography color="text.secondary">
                    Received
                  </Typography>
                </Box>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        {/* Recent Documents */}
        <Grid item xs={12} md={8}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Recent Documents
              </Typography>
              {recentDocuments.length > 0 ? (
                <List>
                  {recentDocuments.map((doc, index) => (
                    <ListItem key={index} divider={index < recentDocuments.length - 1}>
                      <ListItemIcon>
                        {getStatusIcon(doc.status)}
                      </ListItemIcon>
                      <ListItemText
                        primary={doc.title}
                        secondary={
                          <Box>
                            <Typography variant="body2" color="text.secondary">
                              Uploaded: {new Date(doc.uploaded_at).toLocaleDateString()}
                            </Typography>
                            {doc.department_name && (
                              <Typography variant="body2" color="primary">
                                Department: {doc.department_name}
                              </Typography>
                            )}
                            {doc.received_by_name && (
                              <Typography variant="body2" color="success.main">
                                Received by: {doc.received_by_name}
                              </Typography>
                            )}
                          </Box>
                        }
                      />
                      <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 1 }}>
                        <Chip
                          label={doc.status}
                          color={getStatusColor(doc.status)}
                          size="small"
                          sx={{ textTransform: 'capitalize' }}
                        />
                        {doc.department_name && (
                          <Chip
                            label={doc.department_name}
                            color="secondary"
                            variant="outlined"
                            size="small"
                          />
                        )}
                      </Box>
                    </ListItem>
                  ))}
                </List>
              ) : (
                <Typography color="text.secondary" sx={{ textAlign: 'center', py: 2 }}>
                  No recent documents
                </Typography>
              )}
            </CardContent>
          </Card>
        </Grid>

        {/* Quick Actions */}
        <Grid item xs={12} md={4}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Quick Actions
              </Typography>
              <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                <Button
                  variant="contained"
                  startIcon={<UploadIcon />}
                  fullWidth
                  onClick={() => navigate('/documents')}
                >
                  Upload Document
                </Button>
                <Button
                  variant="outlined"
                  startIcon={<DocumentIcon />}
                  fullWidth
                  onClick={() => navigate('/documents')}
                >
                  View All Documents
                </Button>
                {user?.role === 'admin' && (
                  <>
                    <Button
                      variant="outlined"
                      startIcon={<TrendingIcon />}
                      fullWidth
                      onClick={() => navigate('/reports')}
                    >
                      Generate Report
                    </Button>
                  </>
                )}
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
};

export default Dashboard;
