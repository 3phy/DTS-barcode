import React, { useState } from 'react';
import {
  Box,
  Typography,
  Card,
  CardContent,
  Grid,
  Button,
  Tabs,
  Tab,
  Alert,
} from '@mui/material';
import {
  Settings as SettingsIcon,
  Storage as DatabaseIcon,
  People as UsersIcon,
  Description as LogsIcon,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import SystemSettings from '../components/SystemSettings';
import DatabaseManagement from '../components/DatabaseManagement';
import UserActivity from '../components/UserActivity';
import SystemLogs from '../components/SystemLogs';

const SystemAdmin = () => {
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState(0);

  if (user?.role !== 'admin') {
    return (
      <Alert severity="error">
        Access denied. Admin privileges required.
      </Alert>
    );
  }

  const handleTabChange = (event, newValue) => {
    setActiveTab(newValue);
  };

  const tabs = [
    { label: 'System Settings', icon: <SettingsIcon />, component: <SystemSettings /> },
    { label: 'Database Management', icon: <DatabaseIcon />, component: <DatabaseManagement /> },
    { label: 'User Activity', icon: <UsersIcon />, component: <UserActivity /> },
    { label: 'System Logs', icon: <LogsIcon />, component: <SystemLogs /> },
  ];

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        System Administration
      </Typography>
      <Typography variant="body1" color="text.secondary" sx={{ mb: 3 }}>
        Manage system settings and view activity logs.
      </Typography>

      <Card>
        <Box sx={{ borderBottom: 1, borderColor: 'divider' }}>
          <Tabs value={activeTab} onChange={handleTabChange} variant="scrollable">
            {tabs.map((tab, index) => (
              <Tab
                key={index}
                label={tab.label}
                icon={tab.icon}
                iconPosition="start"
                sx={{ minHeight: 64 }}
              />
            ))}
          </Tabs>
        </Box>
        <CardContent sx={{ p: 3 }}>
          {tabs[activeTab].component}
        </CardContent>
      </Card>
    </Box>
  );
};

export default SystemAdmin;
