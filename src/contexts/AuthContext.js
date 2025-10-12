import React, { createContext, useContext, useState, useEffect } from 'react';
import axios from 'axios';
import API_BASE_URL from '../config/api';

const AuthContext = createContext();

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    checkAuthStatus();
  }, []);

  const checkAuthStatus = async () => {
    try {
      const token = localStorage.getItem('token');
      if (token) {
        const response = await axios.get(`${API_BASE_URL}/auth/verify.php`, {
          headers: { Authorization: `Bearer ${token}` }
        });
        if (response.data.success) {
          setUser(response.data.user);
        } else {
          localStorage.removeItem('token');
        }
      }
    } catch (error) {
      console.error('Auth check failed:', error);
      localStorage.removeItem('token');
    } finally {
      setLoading(false);
    }
  };

  const login = async (email, password) => {
    try {
      const response = await axios.post(`${API_BASE_URL}/auth/login.php`, {
        email,
        password
      });
      
      if (response.data.success) {
        const { token, user } = response.data;
        localStorage.setItem('token', token);
        
        // Get user info with department
        try {
          const userResponse = await axios.get(`${API_BASE_URL}/auth/verify.php`, {
            headers: { Authorization: `Bearer ${token}` }
          });
          
          if (userResponse.data.success) {
            setUser(userResponse.data.user);
          } else {
            setUser(user);
          }
        } catch (error) {
          console.error('Error fetching user details:', error);
          setUser(user);
        }
        
        return { success: true };
      } else {
        return { success: false, message: response.data.message };
      }
    } catch (error) {
      console.error('Login error:', error);
      return { success: false, message: 'Login failed. Please try again.' };
    }
  };

  const logout = () => {
    localStorage.removeItem('token');
    setUser(null);
  };

  const value = {
    user,
    login,
    logout,
    loading
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};
