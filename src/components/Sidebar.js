import React from 'react';
import {
  Drawer,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  Divider,
  Typography,
  Box,
  useTheme,
  useMediaQuery,
} from '@mui/material';
import {
  Dashboard as DashboardIcon,
  Description as DocumentsIcon,
  People as PeopleIcon,
  Assessment as ReportsIcon,
  Settings as SettingsIcon,
  Logout as LogoutIcon,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useNavigate, useLocation } from 'react-router-dom';

const drawerWidth = 240;

const Sidebar = ({ open, onToggle }) => {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('md'));
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const menuItems = [
    {
      text: 'Dashboard',
      icon: <DashboardIcon />,
      path: '/dashboard',
      roles: ['admin', 'staff']
    },
    {
      text: 'Documents',
      icon: <DocumentsIcon />,
      path: '/documents',
      roles: ['admin', 'staff']
    },
    {
      text: 'Staff Management',
      icon: <PeopleIcon />,
      path: '/staff',
      roles: ['admin']
    },
    {
      text: 'Reports',
      icon: <ReportsIcon />,
      path: '/reports',
      roles: ['admin']
    },
    {
      text: 'System Admin',
      icon: <SettingsIcon />,
      path: '/system-admin',
      roles: ['admin']
    }
  ];

  const filteredMenuItems = menuItems.filter(item => 
    item.roles.includes(user?.role)
  );

  const handleLogout = () => {
    logout();
    navigate('/');
  };

  const drawer = (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <Box sx={{ p: 2, textAlign: 'center' }}>
        <Typography variant="h6" color="primary" fontWeight="bold">
          DocTrack
        </Typography>
        <Typography variant="body2" color="text.secondary">
          Document Tracking System
        </Typography>
      </Box>
      <Divider />
      <List sx={{ flexGrow: 1, pt: 1 }}>
        {filteredMenuItems.map((item) => (
          <ListItem
            key={item.text}
            button
            onClick={() => navigate(item.path)}
            selected={location.pathname === item.path}
            sx={{
              mx: 1,
              borderRadius: 1,
              '&.Mui-selected': {
                backgroundColor: theme.palette.primary.main,
                color: 'white',
                '&:hover': {
                  backgroundColor: theme.palette.primary.dark,
                },
                '& .MuiListItemIcon-root': {
                  color: 'white',
                },
              },
            }}
          >
            <ListItemIcon>
              {item.icon}
            </ListItemIcon>
            <ListItemText primary={item.text} />
          </ListItem>
        ))}
      </List>
      <Divider />
      <List>
        <ListItem
          button
          onClick={handleLogout}
          sx={{
            mx: 1,
            borderRadius: 1,
            color: theme.palette.error.main,
            '&:hover': {
              backgroundColor: theme.palette.error.light,
              color: 'white',
            },
          }}
        >
          <ListItemIcon sx={{ color: 'inherit' }}>
            <LogoutIcon />
          </ListItemIcon>
          <ListItemText primary="Logout" />
        </ListItem>
      </List>
    </Box>
  );

  return (
    <Drawer
      variant={isMobile ? 'temporary' : 'persistent'}
      open={open}
      onClose={onToggle}
      sx={{
        width: drawerWidth,
        flexShrink: 0,
        '& .MuiDrawer-paper': {
          width: drawerWidth,
          boxSizing: 'border-box',
        },
      }}
    >
      {drawer}
    </Drawer>
  );
};

export default Sidebar;
