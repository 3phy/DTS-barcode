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

  useEffect(() => {
    fetchDocuments();
    fetchDepartments();
    fetchRoutingInfo();
  }, []);

  const fetchDocuments = async () => {
    try {
      const token = localStorage.getItem('token');
      console.log("Fetching documents...");
      const response = await axios.get(`${API_BASE_URL}/documents/list.php`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      console.log("Response:", response.data);
      if (response.data.success) {
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



  const handleForwardDocument = async (document) => {
    try {
      const token = localStorage.getItem('token');
      const response = await axios.post(`${API_BASE_URL}/documents/forward.php`, {
        document_id: document.id
      }, {
        headers: { 
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.data.success) {
        fetchDocuments();
        setError('');
        setSuccessMessage(response.data.message);
        // Clear success message after 3 seconds
        setTimeout(() => setSuccessMessage(''), 3000);
      } else {
        setError(response.data.message || 'Failed to forward document');
      }
    } catch (error) {
      console.error('Forward document error:', error);
      if (error.response?.data?.message) {
        setError(error.response.data.message);
      } else {
        setError('Failed to forward document. Please try again.');
      }
    }
  };

  const handleShowRouting = (document) => {
    setSelectedDocumentForRouting(document);
    setRoutingDialogOpen(true);
  };

  const getRoutingPathForDocument = (document) => {
    if (!document.department_id) return null;
    
    const routing = routingInfo.find(r => r.to_department_id == document.department_id);
    return routing ? routing.routing_path : null;
  };

  const canForwardDocument = (document) => {
    // Check if document is in current user's department and has routing info
    if (!document.department_id || !routingInfo.length) return false;
    
    const routing = routingInfo.find(r => r.to_department_id == document.department_id);
    if (!routing) return false;
    
    // Check if document is at a position in the routing path where it can be forwarded
    // Document should be at the starting department or intermediate department
    const currentDeptId = document.current_department_id;
    const fromDeptId = routing.from_department_id;
    const intermediateDeptId = routing.intermediate_department_id;
    
    // Can forward if:
    // 1. Document is at the starting department (from_department_id) OR intermediate department
    // 2. Document has been received (status is 'received' or has received_by)
    const isAtValidPosition = currentDeptId == fromDeptId || (intermediateDeptId && currentDeptId == intermediateDeptId);
    const hasBeenReceived = document.status === 'received' || document.received_by;
    
    return isAtValidPosition && hasBeenReceived;
  };


  const getStatusColor = (status) => {
    switch (status) {
      case 'outgoing': return 'primary';
      case 'pending': return 'warning';
      case 'received': return 'success';
      default: return 'default';
    }
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

      {/* Document Workflow Help */}
      <Alert severity="info" sx={{ mb: 2 }}>
        <Typography variant="body2" component={'div'}>
          <strong>Document Workflow:</strong>
          <br />• <strong>Upload:</strong> Select destination department during upload → Document shows{" "}<Chip label="outgoing" color="primary" size="small" />
          <br />• <strong>Receive:</strong> Scan QR code to receive document → Status changes to{" "}<Chip label="received" color="success" size="small" />
          <br />• <strong>Forward:</strong> After receiving, click forward button to send to next department in routing path
          <br />• <strong>Routing:</strong> Documents follow routing path (e.g., IT → Operations → HR)
          <br />• <strong>Routing Info:</strong> Click the routing icon to see the complete path your document will take
        </Typography>
      </Alert>

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
                    <TableCell>Received By</TableCell>
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
                        {doc.current_department_name || 'No Department'}
                      </Typography>
                      <Typography variant="caption" color="text.secondary">
                        {(doc.display_status || doc.status) === 'outgoing' ? 'Current Department' : 
                         (doc.display_status || doc.status) === 'pending' ? 'Pending at Department' : 
                         'Received at Department'}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2" fontWeight="medium">
                        {doc.department_name || 'No Department'}
                      </Typography>
                      {getRoutingPathForDocument(doc) && (
                        <Typography variant="caption" color="text.secondary">
                          Route: {getRoutingPathForDocument(doc).join(' → ')}
                        </Typography>
                      )}
                    </TableCell>
                    <TableCell>
                      <Chip
                        label={doc.display_status || doc.status}
                        color={getStatusColor(doc.display_status || doc.status)}
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
                      {doc.received_by_name ? (
                        <Box>
                          <Typography variant="body2">
                            {doc.received_by_name}
                          </Typography>
                          <Typography variant="caption" color="text.secondary">
                            {doc.received_at ? `${new Date(doc.received_at).toLocaleDateString()} ${new Date(doc.received_at).toLocaleTimeString()}` : 'Not received'}
                          </Typography>
                        </Box>
                      ) : (
                        <Typography variant="body2" color="text.secondary">
                          Not received yet
                        </Typography>
                      )}
                    </TableCell>
                    <TableCell>
                      <Box sx={{ display: 'flex', gap: 1 }}>
                        <Tooltip title="Download Document">
                          <IconButton 
                            size="small"
                            onClick={() => handleDownload(doc)}
                          >
                            <DownloadIcon />
                          </IconButton>
                        </Tooltip>
                        <Tooltip title="View Barcode">
                          <IconButton 
                            size="small"
                            onClick={() => {
                              setSelectedDocument(doc);
                              setBarcodeDialogOpen(true);
                            }}
                          >
                            <QrCodeIcon />
                          </IconButton>
                        </Tooltip>
                        {/* Show Forward button for documents that can be forwarded */}
                        {canForwardDocument(doc) && (
                          <Tooltip title="Forward to Next Department">
                            <IconButton 
                              size="small"
                              onClick={() => handleForwardDocument(doc)}
                              color="secondary"
                            >
                              <ForwardIcon />
                            </IconButton>
                          </Tooltip>
                        )}
                        {/* Show Routing button for documents uploaded by current user */}
                        {doc.uploaded_by === user?.id && (
                          <Tooltip title="Show Routing Path">
                            <IconButton 
                              size="small"
                              onClick={() => handleShowRouting(doc)}
                              color="info"
                            >
                              <RouteIcon />
                            </IconButton>
                          </Tooltip>
                        )}
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



      {/* Routing Dialog */}
      <Dialog open={routingDialogOpen} onClose={() => setRoutingDialogOpen(false)} maxWidth="sm" fullWidth>
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
              <Typography variant="body2" gutterBottom sx={{ mt: 2 }}>
                <strong>Routing Path:</strong>
              </Typography>
              {(() => {
                const routingPath = getRoutingPathForDocument(selectedDocumentForRouting);
                if (routingPath) {
                  return (
                    <Box sx={{ mt: 2, p: 2, bgcolor: 'info.light', borderRadius: 1 }}>
                      <Typography variant="body2" color="info.contrastText">
                        <strong>Your document will be sent through:</strong>
                      </Typography>
                      <Typography variant="h6" color="info.contrastText" sx={{ mt: 1 }}>
                        {routingPath.join(' → ')}
                      </Typography>
                      {routingPath.length > 2 && (
                        <Typography variant="caption" display="block" sx={{ mt: 1 }}>
                          Note: Document will first go to {routingPath[1]} before reaching the final destination.
                        </Typography>
                      )}
                    </Box>
                  );
                } else {
                  return (
                    <Box sx={{ mt: 2, p: 2, bgcolor: 'warning.light', borderRadius: 1 }}>
                      <Typography variant="body2" color="warning.contrastText">
                        No routing information available for this destination department.
                      </Typography>
                    </Box>
                  );
                }
              })()}
            </Box>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRoutingDialogOpen(false)}>Close</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default Documents;
