import React, { useState, useEffect } from 'react';
import {
  Box,
  Typography,
  Card,
  CardContent,
  Grid,
  TextField,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Button,
  Alert,
  CircularProgress,
  Paper,
  List,
  ListItem,
  ListItemText,
  ListItemIcon,
  Chip,
  Divider,
  IconButton,
  Tooltip,
} from '@mui/material';
import {
  Search as SearchIcon,
  Refresh as RefreshIcon,
  Download as DownloadIcon,
  Clear as ClearIcon,
  Info as InfoIcon,
  Warning as WarningIcon,
  Error as ErrorIcon,
  BugReport as DebugIcon,
} from '@mui/icons-material';
import axios from 'axios';
import API_BASE_URL from '../config/api';

const SystemLogs = () => {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [logs, setLogs] = useState([]);
  const [filters, setFilters] = useState({
    level: 'all',
    dateFrom: '',
    dateTo: '',
    search: '',
    limit: 100
  });
  const [logStats, setLogStats] = useState({
    totalLogs: 0,
    errorCount: 0,
    warningCount: 0,
    infoCount: 0,
    debugCount: 0
  });

  useEffect(() => {
    fetchLogs();
    fetchLogStats();
  }, []);

  const fetchLogs = async () => {
    setLoading(true);
    setError('');

    try {
      const token = localStorage.getItem('token');
      const params = new URLSearchParams();
      
      if (filters.level !== 'all') params.append('level', filters.level);
      if (filters.dateFrom) params.append('date_from', filters.dateFrom);
      if (filters.dateTo) params.append('date_to', filters.dateTo);
      if (filters.search) params.append('search', filters.search);
      params.append('limit', filters.limit);

      const response = await axios.get(`${API_BASE_URL}/admin/logs.php?${params}`, {
        headers: { Authorization: `Bearer ${token}` }
      });

      if (response.data.success) {
        setLogs(response.data.logs);
      } else {
        setError('Failed to fetch logs');
      }
    } catch (error) {
      console.error('Logs error:', error);
      setError('Failed to fetch logs');
    } finally {
      setLoading(false);
    }
  };

  const fetchLogStats = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get(`${API_BASE_URL}/admin/log-stats.php`, {
        headers: { Authorization: `Bearer ${token}` }
      });

      if (response.data.success) {
        setLogStats(response.data.stats);
      }
    } catch (error) {
      console.error('Log stats error:', error);
    }
  };

  const handleFilterChange = (field, value) => {
    setFilters({ ...filters, [field]: value });
  };

  const handleSearch = () => {
    fetchLogs();
  };

  const handleClearLogs = async () => {
    if (!window.confirm('Are you sure you want to clear all logs? This action cannot be undone.')) {
      return;
    }

    try {
      const token = localStorage.getItem('token');
      const response = await axios.delete(`${API_BASE_URL}/admin/clear-logs.php`, {
        headers: { Authorization: `Bearer ${token}` }
      });

      if (response.data.success) {
        fetchLogs();
        fetchLogStats();
      } else {
        setError('Failed to clear logs');
      }
    } catch (error) {
      console.error('Clear logs error:', error);
      setError('Failed to clear logs');
    }
  };

  const handleDownloadLogs = async () => {
    try {
      const token = localStorage.getItem('token');
      const params = new URLSearchParams();
      
      if (filters.level !== 'all') params.append('level', filters.level);
      if (filters.dateFrom) params.append('date_from', filters.dateFrom);
      if (filters.dateTo) params.append('date_to', filters.dateTo);
      if (filters.search) params.append('search', filters.search);

      const response = await axios.get(`${API_BASE_URL}/admin/download-logs.php?${params}`, {
        headers: { Authorization: `Bearer ${token}` },
        responseType: 'blob'
      });

      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `system-logs-${new Date().toISOString().split('T')[0]}.txt`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Download logs error:', error);
      setError('Failed to download logs');
    }
  };

  const getLogIcon = (level) => {
    switch (level) {
      case 'error': return <ErrorIcon color="error" />;
      case 'warning': return <WarningIcon color="warning" />;
      case 'info': return <InfoIcon color="info" />;
      case 'debug': return <DebugIcon color="action" />;
      default: return <InfoIcon />;
    }
  };

  const getLogColor = (level) => {
    switch (level) {
      case 'error': return 'error';
      case 'warning': return 'warning';
      case 'info': return 'info';
      case 'debug': return 'default';
      default: return 'default';
    }
  };

  if (loading && logs.length === 0) {
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

      {/* Log Stats */}
      <Grid container spacing={3} sx={{ mb: 3 }}>
        <Grid item xs={12} sm={6} md={2.4}>
          <Card>
            <CardContent>
              <Box textAlign="center">
                <Typography variant="h4" color="primary">
                  {logStats.totalLogs}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  Total Logs
                </Typography>
              </Box>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} sm={6} md={2.4}>
          <Card>
            <CardContent>
              <Box textAlign="center">
                <Typography variant="h4" color="error.main">
                  {logStats.errorCount}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  Errors
                </Typography>
              </Box>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} sm={6} md={2.4}>
          <Card>
            <CardContent>
              <Box textAlign="center">
                <Typography variant="h4" color="warning.main">
                  {logStats.warningCount}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  Warnings
                </Typography>
              </Box>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} sm={6} md={2.4}>
          <Card>
            <CardContent>
              <Box textAlign="center">
                <Typography variant="h4" color="info.main">
                  {logStats.infoCount}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  Info
                </Typography>
              </Box>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} sm={6} md={2.4}>
          <Card>
            <CardContent>
              <Box textAlign="center">
                <Typography variant="h4" color="text.secondary">
                  {logStats.debugCount}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  Debug
                </Typography>
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Filters */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            Filter Logs
          </Typography>
          <Grid container spacing={2} alignItems="center">
            <Grid item xs={12} sm={6} md={2}>
              <FormControl fullWidth size="small">
                <InputLabel>Level</InputLabel>
                <Select
                  value={filters.level}
                  onChange={(e) => handleFilterChange('level', e.target.value)}
                  label="Level"
                >
                  <MenuItem value="all">All Levels</MenuItem>
                  <MenuItem value="error">Error</MenuItem>
                  <MenuItem value="warning">Warning</MenuItem>
                  <MenuItem value="info">Info</MenuItem>
                  <MenuItem value="debug">Debug</MenuItem>
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12} sm={6} md={2}>
              <TextField
                fullWidth
                size="small"
                label="Date From"
                type="date"
                value={filters.dateFrom}
                onChange={(e) => handleFilterChange('dateFrom', e.target.value)}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={6} md={2}>
              <TextField
                fullWidth
                size="small"
                label="Date To"
                type="date"
                value={filters.dateTo}
                onChange={(e) => handleFilterChange('dateTo', e.target.value)}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={6} md={2}>
              <TextField
                fullWidth
                size="small"
                label="Limit"
                type="number"
                value={filters.limit}
                onChange={(e) => handleFilterChange('limit', parseInt(e.target.value))}
                inputProps={{ min: 10, max: 1000 }}
              />
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <TextField
                fullWidth
                size="small"
                label="Search"
                value={filters.search}
                onChange={(e) => handleFilterChange('search', e.target.value)}
                placeholder="Search in log messages..."
              />
            </Grid>
            <Grid item xs={12} sm={6} md={1}>
              <Button
                fullWidth
                variant="contained"
                startIcon={<SearchIcon />}
                onClick={handleSearch}
                disabled={loading}
              >
                Search
              </Button>
            </Grid>
          </Grid>
          
          <Box sx={{ mt: 2, display: 'flex', gap: 1 }}>
            <Button
              variant="outlined"
              startIcon={<RefreshIcon />}
              onClick={fetchLogs}
              disabled={loading}
            >
              Refresh
            </Button>
            <Button
              variant="outlined"
              startIcon={<DownloadIcon />}
              onClick={handleDownloadLogs}
            >
              Download
            </Button>
            <Button
              variant="outlined"
              color="error"
              startIcon={<ClearIcon />}
              onClick={handleClearLogs}
            >
              Clear Logs
            </Button>
          </Box>
        </CardContent>
      </Card>

      {/* Logs Display */}
      <Card>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            System Logs
          </Typography>
          
          {logs.length > 0 ? (
            <Paper sx={{ maxHeight: 600, overflow: 'auto' }}>
              <List dense>
                {logs.map((log, index) => (
                  <React.Fragment key={log.id || index}>
                    <ListItem>
                      <ListItemIcon>
                        {getLogIcon(log.level)}
                      </ListItemIcon>
                      <ListItemText
                        primary={
                          <Box display="flex" alignItems="center" gap={1}>
                            <Chip
                              label={log.level}
                              color={getLogColor(log.level)}
                              size="small"
                              sx={{ textTransform: 'uppercase' }}
                            />
                            <Typography variant="body2" fontFamily="monospace">
                              {new Date(log.timestamp).toLocaleString()}
                            </Typography>
                          </Box>
                        }
                        secondary={
                          <Box>
                            <Typography variant="body2" sx={{ mb: 1 }}>
                              {log.message}
                            </Typography>
                            {log.context && (
                              <Typography variant="caption" color="text.secondary" fontFamily="monospace">
                                Context: {JSON.stringify(log.context)}
                              </Typography>
                            )}
                          </Box>
                        }
                      />
                    </ListItem>
                    {index < logs.length - 1 && <Divider />}
                  </React.Fragment>
                ))}
              </List>
            </Paper>
          ) : (
            <Box textAlign="center" py={4}>
              <Typography variant="body2" color="text.secondary">
                No logs found matching your criteria
              </Typography>
            </Box>
          )}
        </CardContent>
      </Card>
    </Box>
  );
};

export default SystemLogs;
