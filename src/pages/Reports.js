import React, { useState, useEffect } from 'react';
import {
  Box,
  Typography,
  Card,
  CardContent,
  Grid,
  Button,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  Chip,
  CircularProgress,
  Alert,
  TextField,
} from '@mui/material';
import {
  Assessment as ReportIcon,
  Download as DownloadIcon,
  Refresh as RefreshIcon,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import axios from 'axios';
import API_BASE_URL from '../config/api';

const Reports = () => {
  const { user } = useAuth();
  const [reports, setReports] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [filters, setFilters] = useState({
    dateFrom: '',
    dateTo: '',
    status: 'all',
    user: 'all',
  });
  const [stats, setStats] = useState({
    totalDocuments: 0,
    outgoingCount: 0,
    pendingCount: 0,
    receivedCount: 0,
    completionRate: 0,
  });

  useEffect(() => {
    if (user?.role === 'admin') {
      fetchReports();
      fetchStats();
    }
  }, [user]);

  const fetchReports = async () => {
    try {
      const token = localStorage.getItem('token');
      const params = new URLSearchParams();
      
      if (filters.dateFrom) params.append('date_from', filters.dateFrom);
      if (filters.dateTo) params.append('date_to', filters.dateTo);
      if (filters.status !== 'all') params.append('status', filters.status);
      if (filters.user !== 'all') params.append('user_id', filters.user);

      const response = await axios.get(`${API_BASE_URL}/reports/documents.php?${params}`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      
      if (response.data.success) {
        setReports(response.data.reports);
      } else {
        setError('Failed to load reports');
      }
    } catch (error) {
      console.error('Reports error:', error);
      setError('Failed to load reports');
    } finally {
      setLoading(false);
    }
  };

  const fetchStats = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get(`${API_BASE_URL}/reports/stats.php`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      
      if (response.data.success) {
        setStats(response.data.stats);
      }
    } catch (error) {
      console.error('Stats error:', error);
    }
  };

  const handleFilterChange = (field, value) => {
    setFilters({ ...filters, [field]: value });
  };

  const handleGenerateReport = () => {
    fetchReports();
  };

  const handleExportReport = async () => {
    try {
      const token = localStorage.getItem('token');
      const params = new URLSearchParams();
      
      if (filters.dateFrom) params.append('date_from', filters.dateFrom);
      if (filters.dateTo) params.append('date_to', filters.dateTo);
      if (filters.status !== 'all') params.append('status', filters.status);
      if (filters.user !== 'all') params.append('user_id', filters.user);

      const response = await axios.get(`${API_BASE_URL}/reports/export.php?${params}`, {
        headers: { Authorization: `Bearer ${token}` },
        responseType: 'blob'
      });

      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `document-report-${new Date().toISOString().split('T')[0]}.csv`);
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (error) {
      console.error('Export error:', error);
      setError('Export failed. Please try again.');
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
      <Typography variant="h4" gutterBottom>
        Reports & Analytics
      </Typography>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError('')}>
          {error}
        </Alert>
      )}

      {/* Stats Cards */}
      <Grid container spacing={3} sx={{ mb: 3 }}>
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
                  <ReportIcon />
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
                  <ReportIcon />
                </Box>
                <Box>
                  <Typography variant="h4" component="div">
                    {stats.outgoingCount}
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
                  <ReportIcon />
                </Box>
                <Box>
                  <Typography variant="h4" component="div">
                    {stats.pendingCount}
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
                  <ReportIcon />
                </Box>
                <Box>
                  <Typography variant="h4" component="div">
                    {stats.receivedCount}
                  </Typography>
                  <Typography color="text.secondary">
                    Received
                  </Typography>
                </Box>
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Filters */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            Report Filters
          </Typography>
          <Grid container spacing={2} alignItems="center">
            <Grid item xs={12} sm={6} md={3}>
              <TextField
                fullWidth
                label="Date From"
                type="date"
                value={filters.dateFrom}
                onChange={(e) => handleFilterChange('dateFrom', e.target.value)}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <TextField
                fullWidth
                label="Date To"
                type="date"
                value={filters.dateTo}
                onChange={(e) => handleFilterChange('dateTo', e.target.value)}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <FormControl fullWidth>
                <InputLabel>Status</InputLabel>
                <Select
                  value={filters.status}
                  onChange={(e) => handleFilterChange('status', e.target.value)}
                  label="Status"
                >
                  <MenuItem value="all">All Statuses</MenuItem>
                  <MenuItem value="outgoing">Outgoing</MenuItem>
                  <MenuItem value="pending">Pending</MenuItem>
                  <MenuItem value="received">Received</MenuItem>
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <Box display="flex" gap={1}>
                <Button
                  variant="contained"
                  startIcon={<RefreshIcon />}
                  onClick={handleGenerateReport}
                >
                  Generate
                </Button>
                <Button
                  variant="outlined"
                  startIcon={<DownloadIcon />}
                  onClick={handleExportReport}
                >
                  Export
                </Button>
              </Box>
            </Grid>
          </Grid>
        </CardContent>
      </Card>

      {/* Reports Table */}
      <Card>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            Document Reports
          </Typography>
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>Title</TableCell>
                  <TableCell>Sended To</TableCell>
                  <TableCell>Status</TableCell>
                  <TableCell>Created By</TableCell>
                  <TableCell>Received By</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {reports.map((report) => (
                  <TableRow key={report.id}>
                    <TableCell>
                      <Typography variant="body2" fontWeight="medium">
                        {report.title}
                      </Typography>
                      {report.description && (
                        <Typography variant="caption" color="text.secondary">
                          {report.description}
                        </Typography>
                      )}
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2" fontWeight="medium">
                        {report.department_name || 'No Department'}
                      </Typography>
                      <Typography variant="caption" color="text.secondary">
                        Department
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Chip
                        label={report.status}
                        color={getStatusColor(report.status)}
                        size="small"
                        sx={{ textTransform: 'capitalize' }}
                      />
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">
                        {report.uploaded_by_name || 'Unknown'}
                      </Typography>
                      <Typography variant="caption" color="text.secondary">
                        {new Date(report.uploaded_at).toLocaleDateString()} {new Date(report.uploaded_at).toLocaleTimeString()}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      {report.received_by_name ? (
                        <Box>
                          <Typography variant="body2">
                            {report.received_by_name}
                          </Typography>
                          <Typography variant="caption" color="text.secondary">
                            {report.received_at ? `${new Date(report.received_at).toLocaleDateString()} ${new Date(report.received_at).toLocaleTimeString()}` : 'Not received'}
                          </Typography>
                        </Box>
                      ) : (
                        <Typography variant="body2" color="text.secondary">
                          Not received yet
                        </Typography>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </CardContent>
      </Card>
    </Box>
  );
};

export default Reports;
