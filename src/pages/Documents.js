import React, { useState, useEffect } from 'react';
import {
  Box,
  Typography,
  Button,
  Card,
  CardContent,
  Grid,
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
  Fab,
  Tooltip,
  InputAdornment,
  LinearProgress,
  Menu,
  MenuItem,
  ListItemIcon,
  ListItemText,
} from '@mui/material';
import {
  Add as AddIcon,
  Upload as UploadIcon,
  QrCodeScanner as ScannerIcon,
  Download as DownloadIcon,
  Visibility as ViewIcon,
  QrCode as QrCodeIcon,
  Send as SendIcon,
  Route as RouteIcon,
  Forward as ForwardIcon,
  Search as SearchIcon,
  Cancel as CancelIcon,
  MoreVert as MoreVertIcon,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import axios from 'axios';
import API_BASE_URL from '../config/api';
import BarcodeGenerator from '../components/BarcodeGenerator';
import BarcodeScanner from '../components/BarcodeScanner';

const Documents = () => {
  const { user } = useAuth();
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [uploadDialogOpen, setUploadDialogOpen] = useState(false);
  const [scannerDialogOpen, setScannerDialogOpen] = useState(false);
  const [barcodeDialogOpen, setBarcodeDialogOpen] = useState(false);
  const [selectedDocument, setSelectedDocument] = useState(null);
  const [uploadData, setUploadData] = useState({
    title: '',
    description: '',
    department_id: '',
    file: null,
  });
  const [departments, setDepartments] = useState([]);
  const [successMessage, setSuccessMessage] = useState('');
  const [routingInfo, setRoutingInfo] = useState([]);
  const [routingDialogOpen, setRoutingDialogOpen] = useState(false);
  const [selectedDocumentForRouting, setSelectedDocumentForRouting] = useState(null);
  const [errorDialogOpen, setErrorDialogOpen] = useState(false);
  const [dynamicRoutingData, setDynamicRoutingData] = useState(null);
  const [forwardDialogOpen, setForwardDialogOpen] = useState(false);
  const [selectedDocumentForForward, setSelectedDocumentForForward] = useState(null);
  const [forwardSearchTerm, setForwardSearchTerm] = useState('');
  const [forwardingStatus, setForwardingStatus] = useState('');
  const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
  const [selectedDocumentForCancel, setSelectedDocumentForCancel] = useState(null);
  const [cancellingStatus, setCancellingStatus] = useState('');
  const [cancelNote, setCancelNote] = useState('');
  const [actionMenuAnchor, setActionMenuAnchor] = useState(null);
  const [selectedDocumentForAction, setSelectedDocumentForAction] = useState(null);

  useEffect(() => {
    fetchDocuments();
    fetchDepartments();
    fetchRoutingInfo();
    
    // Debug: Log user data
    console.log('User data:', user);
  }, [user]);

  useEffect(() => {
    if (error) setErrorDialogOpen(true);
  }, [error]);

  const fetchDocuments = async () => {
    try {
      const token = localStorage.getItem('token');
      console.log("Fetching documents...");
      const response = await axios.get(`${API_BASE_URL}/documents/list.php`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      console.log("Response:", response.data);
      if (response.data.success) {
        console.log("Documents loaded:", response.data.documents);
        setDocuments(response.data.documents);
      } else {
        setError('Failed to load documents');
      }
    } catch (error) {
      console.error('Documents error:', error);
      setError('Failed to load documents');
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

  const fetchRoutingInfo = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get(`${API_BASE_URL}/documents/routing.php`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      
      if (response.data.success) {
        setRoutingInfo(response.data.routing_info || []);
        // Show warning if user has no department
        if (response.data.message && response.data.message.includes('no department assigned')) {
          console.warn('Routing info:', response.data.message);
        }
      }
    } catch (error) {
      console.error('Routing info error:', error);
      // Set empty routing info on error
      setRoutingInfo([]);
    }
  };

  const handleFileUpload = (event) => {
    const file = event.target.files[0];
    setUploadData({ ...uploadData, file });
  };

  const handleUpload = async () => {
    if (!uploadData.title || !uploadData.file || !uploadData.department_id) {
      setError('Please fill in all required fields');
      return;
    }

    try {
      const formData = new FormData();
      formData.append('title', uploadData.title);
      formData.append('description', uploadData.description);
      formData.append('department_id', uploadData.department_id);
      formData.append('file', uploadData.file);

      const token = localStorage.getItem('token');
      const response = await axios.post(`${API_BASE_URL}/documents/upload.php`, formData, {
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'multipart/form-data',
        }
      });

      if (response.data.success) {
        setUploadDialogOpen(false);
        setUploadData({ title: '', description: '', department_id: '', file: null });
        fetchDocuments();
        setError('');
        setSuccessMessage('Document uploaded successfully!');
        // Clear success message after 3 seconds
        setTimeout(() => setSuccessMessage(''), 3000);
      } else {
        setError(response.data.message || 'Upload failed');
      }
    } catch (error) {
      console.error('Upload error:', error);
      setError('Upload failed. Please try again.');
    }
  };

  const handleDownload = async (document) => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get(`${API_BASE_URL}/documents/download.php?id=${document.id}`, {
        headers: { Authorization: `Bearer ${token}` },
        responseType: 'blob'
      });

      // Create blob link to download
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', document.filename);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Download error:', error);
      setError('Download failed. Please try again.');
    }
  };

  const handleScanResult = async (barcode) => {
    try {
      const token = localStorage.getItem('token');
      
      // Try to receive the document via QR code scan
      const response = await axios.post(`${API_BASE_URL}/documents/receive.php`, {
        barcode: barcode
      }, {
        headers: { 
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.data.success) {
        setScannerDialogOpen(false);
        fetchDocuments();
        setError('');
        setSuccessMessage('Document received successfully!');
        // Clear success message after 3 seconds
        setTimeout(() => setSuccessMessage(''), 3000);
      } else {
        setError(response.data.message || 'Document not found or not available for receiving');
      }
    } catch (error) {
      console.error('Scan error:', error);
      if (error.response?.data?.message) {
        setError(error.response.data.message);
      } else {
        setError('Document not found or not available for receiving. Make sure the document is pending in your department.');
      }
    }
  };



  const handleForwardDocument = (document) => {
    setSelectedDocumentForForward(document);
    setForwardSearchTerm('');
    setForwardingStatus('');
    setForwardDialogOpen(true);
  };

  const handleForwardToDepartment = async (toDepartmentId) => {
    if (!selectedDocumentForForward) return;
    
    setForwardingStatus('forwarding');
    
    try {
      const token = localStorage.getItem('token');
      const response = await axios.post(`${API_BASE_URL}/documents/forward.php`, {
        document_id: selectedDocumentForForward.id,
        to_department_id: toDepartmentId
      }, {
        headers: { 
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.data.success) {
        setForwardingStatus('success');
        fetchDocuments();
        setError('');
        setSuccessMessage(response.data.message);
        setTimeout(() => {
          setForwardDialogOpen(false);
          setSelectedDocumentForForward(null);
          setForwardingStatus('');
        }, 1500);
        // Clear success message after 3 seconds
        setTimeout(() => setSuccessMessage(''), 3000);
      } else {
        setForwardingStatus('error');
        setError(response.data.message || 'Failed to forward document');
      }
    } catch (error) {
      console.error('Forward document error:', error);
      setForwardingStatus('error');
      if (error.response?.data?.message) {
        setError(error.response.data.message);
      } else {
        setError('Failed to forward document. Please try again.');
      }
    }
  };

  const handleCancelDocument = (document) => {
    setSelectedDocumentForCancel(document);
    setCancellingStatus('');
    setCancelDialogOpen(true);
  };

  const handleConfirmCancel = async () => {
    if (!selectedDocumentForCancel) return;
    if (!cancelNote.trim()) {
      setError('Please provide a reason for cancelling the document.');
      return;
    }
    
    console.log('Attempting to cancel document:', selectedDocumentForCancel);
    setCancellingStatus('cancelling');
    
    try {
      const token = localStorage.getItem('token');
      const response = await axios.post(`${API_BASE_URL}/documents/cancel.php`, {
        document_id: selectedDocumentForCancel.id,
        note: cancelNote.trim()
      }, {
        headers: { 
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      console.log('Cancel response:', response.data);

      if (response.data.success) {
        setCancellingStatus('success');
        fetchDocuments();
        setError('');
        setSuccessMessage(response.data.message);
        setTimeout(() => {
          setCancelDialogOpen(false);
          setSelectedDocumentForCancel(null);
          setCancelNote('');
          setCancellingStatus('');
        }, 1500);
        // Clear success message after 3 seconds
        setTimeout(() => setSuccessMessage(''), 3000);
      } else {
        setCancellingStatus('error');
        setError(response.data.message || 'Failed to cancel document');
      }
    } catch (error) {
      console.error('Cancel document error:', error);
      setCancellingStatus('error');
      if (error.response?.data?.message) {
        setError(error.response.data.message);
      } else {
        setError('Failed to cancel document. Please try again.');
      }
    }
  };

  const handleActionMenuOpen = (event, document) => {
    setActionMenuAnchor(event.currentTarget);
    setSelectedDocumentForAction(document);
  };

  const handleActionMenuClose = () => {
    setActionMenuAnchor(null);
    setSelectedDocumentForAction(null);
  };

  const handleActionMenuAction = (action) => {
    if (!selectedDocumentForAction) return;
    
    switch (action) {
      case 'download':
        handleDownload(selectedDocumentForAction);
        break;
      case 'barcode':
        setSelectedDocument(selectedDocumentForAction);
        setBarcodeDialogOpen(true);
        break;
      case 'forward':
        handleForwardDocument(selectedDocumentForAction);
        break;
      case 'cancel':
        handleCancelDocument(selectedDocumentForAction);
        break;
      case 'routing':
        handleShowRouting(selectedDocumentForAction);
        break;
      default:
        break;
    }
    
    handleActionMenuClose();
  };

  const handleShowRouting = async (document) => {
    setSelectedDocumentForRouting(document);
    setDynamicRoutingData(null);
    setRoutingDialogOpen(true);
    
    // Fetch dynamic routing data
    try {
      const token = localStorage.getItem('token');
      const response = await axios.get(`${API_BASE_URL}/documents/routing-history.php?document_id=${document.id}`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      
      if (response.data.success) {
        setDynamicRoutingData(response.data);
      } else {
        console.error('Failed to fetch routing history:', response.data.message);
      }
    } catch (error) {
      console.error('Error fetching routing history:', error);
    }
  };

  const getAvailableDepartmentsForForward = (document) => {
    if (!document || !routingInfo.length) return departments;

    // Get routing path record for destination department
    const routing = routingInfo.find(r => r.to_department_id == document.department_id);
    if (!routing || !routing.routing_path?.length) return departments;

    // Determine the next department in the path after the current department
    const path = routing.routing_path; // array of department names
    const currentDeptName = document.current_department_name || document.department_name;
    const currentIndex = path.findIndex(name => name === currentDeptName);

    // If current not found, allow only the first in path
    const nextDeptName = currentIndex >= 0 ? path[currentIndex + 1] : path[0];
    if (!nextDeptName) {
      return [];
    }

    // Map name to id
    const nextDept = departments.find(d => d.name === nextDeptName);
    return nextDept ? [nextDept] : [];
  };

  const getFilteredDepartments = (document) => {
    const availableDepts = getAvailableDepartmentsForForward(document);
    if (!forwardSearchTerm) return availableDepts;
    
    return availableDepts.filter(dept => 
      dept.name.toLowerCase().includes(forwardSearchTerm.toLowerCase())
    );
  };

  const getRoutingPathForDocument = (document) => {
    if (!document.department_id) return null;
    
    const routing = routingInfo.find(r => r.to_department_id == document.department_id);
    return routing ? routing.routing_path : null;
  };

  const getCreatedDepartmentForDocument = (document) => {
    const path = getRoutingPathForDocument(document);
    if (document.created_department_name) return document.created_department_name;
    if (document.source_department_name) return document.source_department_name;
    if (document.uploaded_by_department_name) return document.uploaded_by_department_name;
    if (path && path.length > 0) return path[0];
    // Avoid using current/receiving department as a fallback so it never changes after scan
    return 'No Department';
  };

  const getSourceDepartmentForDocument = (document) => {
    const path = getRoutingPathForDocument(document);
    if (path && path.length > 0) return path[0];
    // Fallbacks if routing info is unavailable
    return document.source_department_name || document.current_department_name || document.department_name || 'No Department';
  };

  const canForwardDocument = (document) => {
    // Debug logging
    console.log('Checking if document can be forwarded:', {
      documentId: document.id,
      documentStatus: document.status,
      documentCurrentDept: document.current_department_id,
      documentDept: document.department_id,
      userDept: user?.department_id,
      uploadedBy: document.uploaded_by,
      userId: user?.id
    });
    
    // Can forward: only if the status is 'received' and not cancelled/rejected
    if (document.status !== 'received' || getDisplayStatus(document) === 'rejected') {
      console.log('Cannot forward: status is not received');
      return false;
    }
    
    // Check if user is not the sender (cannot forward own documents)
    if (document.uploaded_by === user?.id) {
      console.log('Cannot forward: user is the sender');
      return false;
    }
    
    // Additional check: make sure user has a department
    if (!user?.department_id) {
      console.log('Cannot forward: user has no department');
      return false;
    }
    
    // Check if document is in user's department (either current_department_id or department_id)
    const isInUserDept = document.current_department_id === user?.department_id || 
                        document.department_id === user?.department_id;
    
    if (!isInUserDept) {
      console.log('Cannot forward: document not in user department');
      return false;
    }
    
    console.log('Document can be forwarded!');
    // After receiving, can forward to any department (but only once)
    return true;
  };

  const canCancelDocument = (document) => {
    // Debug logging
    console.log('Checking if document can be cancelled:', {
      documentId: document.id,
      documentStatus: document.status,
      documentCurrentDept: document.current_department_id,
      documentDept: document.department_id,
      userDept: user?.department_id,
      uploadedBy: document.uploaded_by,
      userId: user?.id
    });
    
    // Check if user is not the sender
    if (document.uploaded_by === user?.id) {
      console.log('Cannot cancel: user is the sender');
      return false;
    }
    
    // Check if user has a department
    if (!user?.department_id) {
      console.log('Cannot cancel: user has no department');
      return false;
    }
    
    // Check if document is in user's department (either current_department_id or department_id)
    const isInUserDept = document.current_department_id === user?.department_id || 
                        document.department_id === user?.department_id;
    
    if (!isInUserDept) {
      console.log('Cannot cancel: document not in user department');
      return false;
    }
    
    // Check if document is in a cancellable state
    const canCancel = document.status === 'pending' || document.status === 'outgoing' || document.status === 'received';
    console.log('Can cancel result:', canCancel);
    return canCancel;
  };


  const getStatusColor = (status) => {
    switch (status) {
      case 'outgoing': return 'primary';
      case 'pending': return 'warning';
      case 'received': return 'success';
      case 'rejected': return 'error';
      default: return 'default';
    }
  };


  const getDisplayStatus = (doc) => {
    const raw = doc.display_status || doc.status;
    // Normalize cancelled to rejected for display consistency
    if (raw === 'cancelled' || raw === 'canceled') return 'rejected';
    return raw;
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
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
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Box>
          <Typography variant="h4">
            Documents
          </Typography>
          {user?.current_department_name && (
            <Typography variant="body2" color="text.secondary">
              Showing documents for {user.current_department_name} department
            </Typography>
          )}
        </Box>
        <Box>
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={() => setUploadDialogOpen(true)}
            sx={{ mr: 2 }}
          >
            Upload Document
          </Button>
          <Button
            variant="outlined"
            startIcon={<ScannerIcon />}
            onClick={() => setScannerDialogOpen(true)}
          >
            Scan QR to Receive
          </Button>
        </Box>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError('')}>
          {error}
        </Alert>
      )}

      {successMessage && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setSuccessMessage('')}>
          {successMessage}
        </Alert>
      )}

      <Card>
        <CardContent>
          <TableContainer>
            <Table>
              <TableHead>
                  <TableRow>
                    <TableCell>Title</TableCell>
                    <TableCell>Current Department</TableCell>
                    <TableCell>Receiving Department</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell>Created By</TableCell>
                    <TableCell>Actions</TableCell>
                  </TableRow>
              </TableHead>
              <TableBody>
                {documents.map((doc) => (
                  <TableRow key={doc.id}>
                    <TableCell>
                      <Typography variant="body2" fontWeight="medium">
                        {doc.title}
                      </Typography>
                      {doc.description && (
                        <Typography variant="caption" color="text.secondary">
                          {doc.description}
                        </Typography>
                      )}
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2" fontWeight="medium">
                        {getSourceDepartmentForDocument(doc)}
                      </Typography>
                      <Typography variant="caption" color="text.secondary">
                        Source Department
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2" fontWeight="medium">
                        {doc.department_name || 'No Department'}
                        <Tooltip title="Show Routing Path">
                          <IconButton size="small" onClick={() => handleShowRouting(doc)}>
                            <RouteIcon fontSize="small" color="info" />
                          </IconButton>
                        </Tooltip>
                      </Typography>
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mt: 0.5 }}>
                        
                      </Box>
                      {/* Received info removed to avoid duplicate routing path display; keep routing button only */}
                    </TableCell>
                    <TableCell>
                      <Chip
                        label={getDisplayStatus(doc)}
                        color={getStatusColor(getDisplayStatus(doc))}
                        size="small"
                        sx={{ textTransform: 'capitalize' }}
                      />
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">
                        {doc.uploaded_by_name || 'Unknown'}
                      </Typography>
                      <Typography variant="caption" color="text.secondary">
                        {new Date(doc.uploaded_at).toLocaleDateString()} {new Date(doc.uploaded_at).toLocaleTimeString()}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Box sx={{ display: 'flex', gap: 1 }}>
                        {/* Cancel Button - show directly in actions column */}
                        {canCancelDocument(doc) && (
                          <Tooltip title="Cancel Document">
                            <IconButton 
                              size="small"
                              onClick={() => handleCancelDocument(doc)}
                              color="error"
                            >
                              <CancelIcon />
                            </IconButton>
                          </Tooltip>
                        )}
                        
                        {/* Forward Button - show directly in actions column */}
                        {canForwardDocument(doc) && (
                          <Tooltip title="Forward Document">
                            <IconButton 
                              size="small"
                              onClick={() => handleForwardDocument(doc)}
                              color="secondary"
                            >
                              <ForwardIcon />
                            </IconButton>
                          </Tooltip>
                        )}
                        
                        {/* Action Menu Button */}
                        <Tooltip title="Document Actions">
                          <IconButton 
                            size="small"
                            onClick={(e) => handleActionMenuOpen(e, doc)}
                          >
                            <MoreVertIcon />
                          </IconButton>
                        </Tooltip>
                      </Box>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </CardContent>
      </Card>

      {/* Upload Dialog */}
      <Dialog open={uploadDialogOpen} onClose={() => setUploadDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Upload New Document</DialogTitle>
        <DialogContent>
          <TextField
            fullWidth
            label="Document Title"
            value={uploadData.title}
            onChange={(e) => setUploadData({ ...uploadData, title: e.target.value })}
            margin="normal"
            required
          />
          <TextField
            fullWidth
            label="Description"
            value={uploadData.description}
            onChange={(e) => setUploadData({ ...uploadData, description: e.target.value })}
            margin="normal"
            multiline
            rows={3}
          />
          <TextField
            fullWidth
            select
            label="Destination Department"
            value={uploadData.department_id}
            onChange={(e) => setUploadData({ ...uploadData, department_id: e.target.value })}
            margin="normal"
            required
            SelectProps={{ native: true }}
          >
            <option value="">Select Department</option>
            {departments.map((dept) => (
              <option key={dept.id} value={dept.id}>
                {dept.name}
              </option>
            ))}
          </TextField>
          {uploadData.department_id && (
            <Box sx={{ mt: 2, p: 2, bgcolor: 'info.light', borderRadius: 1 }}>
              <Typography variant="body2" color="info.contrastText">
                <strong>Document Routing:</strong>
              </Typography>
              {(() => {
                const routing = routingInfo.find(r => r.to_department_id == uploadData.department_id);
                if (routing) {
                  return (
                    <Typography variant="body2" color="info.contrastText" sx={{ mt: 1 }}>
                      Your document will be sent through: {routing.routing_path.join(' → ')}
                      {routing.has_intermediate && (
                        <Typography variant="caption" display="block" sx={{ mt: 0.5 }}>
                          Note: Document will first go to {routing.intermediate_department_name} before reaching the final destination.
                        </Typography>
                      )}
                    </Typography>
                  );
                } else if (routingInfo.length === 0) {
                  return (
                    <Typography variant="body2" color="info.contrastText" sx={{ mt: 1 }}>
                      Routing information not available. Document will be sent directly to selected department.
                    </Typography>
                  );
                } else {
                  return (
                    <Typography variant="body2" color="info.contrastText" sx={{ mt: 1 }}>
                      Direct routing to selected department.
                    </Typography>
                  );
                }
              })()}
            </Box>
          )}
          <Box sx={{ mt: 2 }}>
            <input
              accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png"
              style={{ display: 'none' }}
              id="file-upload"
              type="file"
              onChange={handleFileUpload}
            />
            <label htmlFor="file-upload">
              <Button variant="outlined" component="span" startIcon={<UploadIcon />}>
                Choose File
              </Button>
            </label>
            {uploadData.file && (
              <Typography variant="body2" sx={{ mt: 1 }}>
                Selected: {uploadData.file.name}
              </Typography>
            )}
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setUploadDialogOpen(false)}>Cancel</Button>
          <Button onClick={handleUpload} variant="contained">
            Upload
          </Button>
        </DialogActions>
      </Dialog>

      {/* Scanner Dialog */}
      <BarcodeScanner
        open={scannerDialogOpen}
        onClose={() => setScannerDialogOpen(false)}
        onScan={handleScanResult}
        title="Scan QR Code to Receive Document"
      />

      {/* Barcode Dialog */}
      <Dialog open={barcodeDialogOpen} onClose={() => setBarcodeDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Document Barcode</DialogTitle>
        <DialogContent>
          {selectedDocument && (
            <BarcodeGenerator
              barcode={selectedDocument.barcode}
              title={selectedDocument.title}
            />
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setBarcodeDialogOpen(false)}>Close</Button>
        </DialogActions>
      </Dialog>

      {/* Error Dialog */}
      <Dialog open={errorDialogOpen} onClose={() => { setErrorDialogOpen(false); setError(''); }} maxWidth="sm" fullWidth>
        <DialogTitle>Error</DialogTitle>
        <DialogContent>
          <Typography variant="body2" color="error">
            {error}
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => { setErrorDialogOpen(false); setError(''); }}>Close</Button>
        </DialogActions>
      </Dialog>



      {/* Routing Dialog */}
      <Dialog open={routingDialogOpen} onClose={() => setRoutingDialogOpen(false)} maxWidth="md" fullWidth>
        <DialogTitle>Document Routing Path</DialogTitle>
        <DialogContent>
          {selectedDocumentForRouting && (
            <Box>
              <Typography variant="body1" gutterBottom>
                <strong>Document:</strong> {selectedDocumentForRouting.title}
              </Typography>
              <Typography variant="body2" color="text.secondary" gutterBottom>
                {selectedDocumentForRouting.description}
              </Typography>
              
              {dynamicRoutingData ? (
                <Box sx={{ mt: 2 }}>
                  {/* Document meta */}
                  <Typography variant="body2" gutterBottom>
                    <strong>Created By:</strong> {selectedDocumentForRouting.uploaded_by_name || 'Unknown'}
                  </Typography>
                  <Typography variant="body2" color="text.secondary" gutterBottom>
                    Created on {formatDate(selectedDocumentForRouting.uploaded_at)}
                  </Typography>

                  <Typography variant="body2" gutterBottom sx={{ mt: 2 }}>
                    <strong>Routing Path & Status:</strong>
                  </Typography>

                  {/* Routing card like the screenshot */}
                  <Box sx={{ p: 2, bgcolor: 'grey.100', borderRadius: 1 }}>
                    {/* Source row */}
                    <Box sx={{ display: 'flex', alignItems: 'flex-start' }}>
                      <Box sx={{ mt: 0.75, mr: 1.5, width: 10, height: 10, borderRadius: '50%', bgcolor: 'success.main' }} />
                      <Box sx={{ flex: 1 }}>
                        <Typography variant="body2" fontWeight="medium">
                          {getCreatedDepartmentForDocument(selectedDocumentForRouting)} (Source)
                        </Typography>
                        <Typography variant="caption" color="text.secondary" display="block">
                          Created: {formatDate(selectedDocumentForRouting.uploaded_at)}
                        </Typography>
                        <Typography variant="caption" color="text.secondary" display="block">
                          Created by: {selectedDocumentForRouting.uploaded_by_name || 'Unknown'}
                        </Typography>
                      </Box>
                    </Box>

                    {/* vertical dotted separator */}
                    <Box sx={{ ml: 1.9, my: 1.2, borderLeft: theme => `2px dotted ${theme.palette.divider}`, height: 20 }} />

                    {/* Textual routing path for clarity */}
                    {dynamicRoutingData?.routing_path?.length ? (
                      <Typography variant="caption" color="text.secondary" display="block" sx={{ ml: 3, mb: 1 }}>
                        Path: {dynamicRoutingData.routing_path.join(' → ')}
                      </Typography>
                    ) : null}

                    {/* Destination row */}
                    <Box sx={{ display: 'flex', alignItems: 'flex-start' }}>
                      <Box sx={{ mt: 0.75, mr: 1.5, width: 10, height: 10, borderRadius: '50%', bgcolor: 'info.main' }} />
                      <Box sx={{ flex: 1 }}>
                        <Typography variant="body2" fontWeight="medium">
                          {selectedDocumentForRouting.department_name || 'Destination Department'} (Destination)
                        </Typography>
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.5 }}>
                          <Typography variant="caption" color="text.secondary">Status:</Typography>
                          <Chip size="small" label={getDisplayStatus(selectedDocumentForRouting)} color={getStatusColor(getDisplayStatus(selectedDocumentForRouting))} sx={{ textTransform: 'capitalize' }} />
                        </Box>
                        {selectedDocumentForRouting.received_by_name && (
                          <>
                            <Typography variant="caption" color="text.secondary" display="block">
                              Received by: {selectedDocumentForRouting.received_by_name}
                            </Typography>
                            {selectedDocumentForRouting.received_at && (
                              <Typography variant="caption" color="text.secondary" display="block">
                                {formatDate(selectedDocumentForRouting.received_at)}
                              </Typography>
                            )}
                          </>
                        )}
                      </Box>
                    </Box>
                  </Box>
                </Box>
              ) : (
                <Box sx={{ mt: 2, p: 2, bgcolor: 'warning.light', borderRadius: 1 }}>
                  <Typography variant="body2" color="warning.contrastText">
                    Loading routing information...
                  </Typography>
                </Box>
              )}
            </Box>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRoutingDialogOpen(false)}>Close</Button>
        </DialogActions>
      </Dialog>

      {/* Forward Dialog */}
      <Dialog open={forwardDialogOpen} onClose={() => setForwardDialogOpen(false)} maxWidth="md" fullWidth>
        <DialogTitle>Forward Document</DialogTitle>
        <DialogContent>
          {selectedDocumentForForward && (
            <Box>
              <Typography variant="body1" gutterBottom>
                <strong>Document:</strong> {selectedDocumentForForward.title}
              </Typography>
              <Typography variant="body2" color="text.secondary" gutterBottom>
                {selectedDocumentForForward.description}
              </Typography>
              
              {/* Search Bar */}
              <TextField
                fullWidth
                placeholder="Search departments..."
                value={forwardSearchTerm}
                onChange={(e) => setForwardSearchTerm(e.target.value)}
                sx={{ mt: 2, mb: 2 }}
                InputProps={{
                  startAdornment: (
                    <InputAdornment position="start">
                      <SearchIcon />
                    </InputAdornment>
                  ),
                }}
              />
              
              <Typography variant="body2" gutterBottom>
                <strong>Select destination department:</strong>
              </Typography>
              
              {/* Status Display */}
              {forwardingStatus === 'forwarding' && (
                <Box sx={{ mt: 2, mb: 2 }}>
                  <LinearProgress />
                  <Typography variant="body2" color="primary" sx={{ mt: 1 }}>
                    Forwarding document...
                  </Typography>
                </Box>
              )}
              
              {forwardingStatus === 'success' && (
                <Box sx={{ mt: 2, mb: 2 }}>
                  <Alert severity="success">
                    Document forwarded successfully!
                  </Alert>
                </Box>
              )}
              
              {forwardingStatus === 'error' && (
                <Box sx={{ mt: 2, mb: 2 }}>
                  <Alert severity="error">
                    Failed to forward document. Please try again.
                  </Alert>
                </Box>
              )}
              
              {/* Department List */}
              <Box sx={{ mt: 2, maxHeight: 400, overflow: 'auto' }}>
                {getFilteredDepartments(selectedDocumentForForward).length > 0 ? (
                  getFilteredDepartments(selectedDocumentForForward).map((dept) => (
                    <Button
                      key={dept.id}
                      variant="outlined"
                      fullWidth
                      disabled={forwardingStatus === 'forwarding'}
                      sx={{ 
                        mb: 1, 
                        justifyContent: 'flex-start',
                        textTransform: 'none'
                      }}
                      onClick={() => handleForwardToDepartment(dept.id)}
                    >
                      {dept.name}
                    </Button>
                  ))
                ) : (
                  <Typography variant="body2" color="text.secondary" sx={{ textAlign: 'center', py: 2 }}>
                    {forwardSearchTerm ? 'No departments found matching your search.' : 'No available departments for forwarding.'}
                  </Typography>
                )}
              </Box>
            </Box>
          )}
        </DialogContent>
        <DialogActions>
          <Button 
            onClick={() => setForwardDialogOpen(false)}
            disabled={forwardingStatus === 'forwarding'}
          >
            Cancel
          </Button>
        </DialogActions>
      </Dialog>

      {/* Action Menu */}
      <Menu
        anchorEl={actionMenuAnchor}
        open={Boolean(actionMenuAnchor)}
        onClose={handleActionMenuClose}
        anchorOrigin={{
          vertical: 'bottom',
          horizontal: 'right',
        }}
        transformOrigin={{
          vertical: 'top',
          horizontal: 'right',
        }}
      >
        {/* Download Action */}
        <MenuItem onClick={() => handleActionMenuAction('download')}>
          <ListItemIcon>
            <DownloadIcon fontSize="small" />
          </ListItemIcon>
          <ListItemText>Download Document</ListItemText>
        </MenuItem>
        
        {/* Barcode Action */}
        <MenuItem onClick={() => handleActionMenuAction('barcode')}>
          <ListItemIcon>
            <QrCodeIcon fontSize="small" />
          </ListItemIcon>
          <ListItemText>View Barcode</ListItemText>
        </MenuItem>
        
        {/* Forward Action - only show if document can be forwarded */}
        {selectedDocumentForAction && canForwardDocument(selectedDocumentForAction) && (
          <MenuItem onClick={() => handleActionMenuAction('forward')}>
            <ListItemIcon>
              <ForwardIcon fontSize="small" color="secondary" />
            </ListItemIcon>
            <ListItemText>Forward Document</ListItemText>
          </MenuItem>
        )}
        
        {/* Cancel Action - only show if document can be cancelled */}
        {selectedDocumentForAction && canCancelDocument(selectedDocumentForAction) && (
          <MenuItem onClick={() => handleActionMenuAction('cancel')}>
            <ListItemIcon>
              <CancelIcon fontSize="small" color="error" />
            </ListItemIcon>
            <ListItemText>Cancel Document</ListItemText>
          </MenuItem>
        )}
        
        
      </Menu>

      {/* Cancel Dialog */}
      <Dialog open={cancelDialogOpen} onClose={() => setCancelDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Cancel Document</DialogTitle>
        <DialogContent>
          {selectedDocumentForCancel && (
            <Box>
              <Typography variant="body1" gutterBottom>
                <strong>Document:</strong> {selectedDocumentForCancel.title}
              </Typography>
              <Typography variant="body2" color="text.secondary" gutterBottom>
                {selectedDocumentForCancel.description}
              </Typography>
              
              <Alert severity="warning" sx={{ mt: 2 }}>
                <Typography variant="body2">
                  <strong>Warning:</strong> Cancelling this document will mark it as rejected and remove it from the workflow. 
                  This action cannot be undone.
                </Typography>
              </Alert>
              <TextField
                fullWidth
                label="Reason for cancellation"
                value={cancelNote}
                onChange={(e) => setCancelNote(e.target.value)}
                margin="normal"
                multiline
                minRows={2}
                required
              />
              
              {/* Status Display */}
              {cancellingStatus === 'cancelling' && (
                <Box sx={{ mt: 2, mb: 2 }}>
                  <LinearProgress />
                  <Typography variant="body2" color="primary" sx={{ mt: 1 }}>
                    Cancelling document...
                  </Typography>
                </Box>
              )}
              
              {cancellingStatus === 'success' && (
                <Box sx={{ mt: 2, mb: 2 }}>
                  <Alert severity="success">
                    Document cancelled successfully!
                  </Alert>
                </Box>
              )}
              
              {cancellingStatus === 'error' && (
                <Box sx={{ mt: 2, mb: 2 }}>
                  <Alert severity="error">
                    Failed to cancel document. Please try again.
                  </Alert>
                </Box>
              )}
            </Box>
          )}
        </DialogContent>
        <DialogActions>
          <Button 
            onClick={() => setCancelDialogOpen(false)}
            disabled={cancellingStatus === 'cancelling'}
          >
            Cancel
          </Button>
          <Button 
            onClick={handleConfirmCancel}
            variant="contained"
            color="error"
            disabled={cancellingStatus === 'cancelling'}
          >
            Confirm Cancel
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default Documents;
