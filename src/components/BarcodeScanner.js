import React, { useState, useRef, useEffect } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Box,
  Typography,
  Alert,
} from '@mui/material';
import { Html5QrcodeScanner } from 'html5-qrcode';

const BarcodeScanner = ({ open, onClose, onScan, title = "Scan Document Barcode" }) => {
  const [error, setError] = useState('');
  const [manualInput, setManualInput] = useState('');
  const [scanMode, setScanMode] = useState(null); // null, 'camera', or 'manual'
  const scannerRef = useRef(null);
  const html5QrcodeScannerRef = useRef(null);

  useEffect(() => {
    if (open && scannerRef.current) {
      // Initialize the scanner
      html5QrcodeScannerRef.current = new Html5QrcodeScanner(
        scannerRef.current.id,
        {
          qrbox: { width: 250, height: 250 },
          fps: 5,
        },
        false
      );

      html5QrcodeScannerRef.current.render(
        (decodedText) => {
          onScan(decodedText);
        },
        (error) => {
          // Handle scan errors silently
          console.log('Scan error:', error);
        }
      );
    }

    return () => {
      if (html5QrcodeScannerRef.current) {
        html5QrcodeScannerRef.current.clear();
      }
    };
  }, [open, onScan]);

  const handleManualSubmit = () => {
    if (manualInput.trim()) {
      onScan(manualInput.trim());
      setManualInput('');
    } else {
      setError('Please enter a QR code');
    }
  };

  const handleClose = () => {
    if (html5QrcodeScannerRef.current) {
      html5QrcodeScannerRef.current.clear();
    }
    setError('');
    setManualInput('');
    setScanMode(null);
    onClose();
  };

  const handleModeSelect = (mode) => {
    setScanMode(mode);
    setError('');
    if (mode === 'manual') {
      // Focus on input field after a short delay
      setTimeout(() => {
        const input = document.querySelector('input[placeholder="Paste or type the QR code here"]');
        if (input) input.focus();
      }, 100);
    }
  };

  return (
    <Dialog open={open} onClose={handleClose} maxWidth="sm" fullWidth>
      <DialogTitle>{title}</DialogTitle>
      <DialogContent>
        <Box sx={{ textAlign: 'center', mb: 3 }}>
          {!scanMode ? (
            // Initial mode selection
            <Box>
              <Typography variant="h6" gutterBottom>
                Choose Scanning Method
              </Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
                Select how you want to scan the QR code
              </Typography>
              
              <Box sx={{ display: 'flex', gap: 3, justifyContent: 'center', flexWrap: 'wrap' }}>
                <Button
                  variant="contained"
                  size="large"
                  onClick={() => handleModeSelect('camera')}
                  sx={{ 
                    minWidth: 180,
                    height: 100,
                    flexDirection: 'column',
                    gap: 1,
                    borderRadius: 2,
                    boxShadow: 3,
                    '&:hover': {
                      boxShadow: 6,
                      transform: 'translateY(-2px)',
                      transition: 'all 0.2s ease-in-out'
                    }
                  }}
                >
                  <span style={{ fontSize: '2rem' }}>📷</span>
                  <Typography variant="h6" fontWeight="bold">
                    Camera Scan
                  </Typography>
                  <Typography variant="caption" sx={{ opacity: 0.8 }}>
                    Use device camera to scan QR code
                  </Typography>
                </Button>
                
                <Button
                  variant="outlined"
                  size="large"
                  onClick={() => handleModeSelect('manual')}
                  sx={{ 
                    minWidth: 180,
                    height: 100,
                    flexDirection: 'column',
                    gap: 1,
                    borderRadius: 2,
                    borderWidth: 2,
                    '&:hover': {
                      borderWidth: 3,
                      transform: 'translateY(-2px)',
                      transition: 'all 0.2s ease-in-out'
                    }
                  }}
                >
                  <span style={{ fontSize: '2rem' }}>⌨️</span>
                  <Typography variant="h6" fontWeight="bold">
                    Manual Input
                  </Typography>
                  <Typography variant="caption" sx={{ opacity: 0.8 }}>
                    Type or paste the QR code
                  </Typography>
                </Button>
              </Box>
            </Box>
          ) : scanMode === 'camera' ? (
            // Camera scanning mode
            <Box>
              <Typography variant="body1" gutterBottom>
                Position the QR code within the camera view
              </Typography>
              
              <Box
                ref={scannerRef}
                id="qr-reader"
                sx={{
                  width: '100%',
                  maxWidth: 400,
                  mx: 'auto',
                  mb: 2,
                }}
              />
              
              <Button
                variant="outlined"
                onClick={() => setScanMode(null)}
                sx={{ mb: 2 }}
              >
                ← Back to Options
              </Button>
            </Box>
          ) : (
            // Manual input mode
            <Box>
              <Typography variant="body1" gutterBottom>
                Enter the QR code manually
              </Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                Type or paste the QR code below
              </Typography>
              
              <TextField
                fullWidth
                label="Enter QR Code Manually"
                value={manualInput}
                onChange={(e) => setManualInput(e.target.value)}
                onKeyPress={(e) => {
                  if (e.key === 'Enter') {
                    handleManualSubmit();
                  }
                }}
                autoFocus
                placeholder="Paste or type the QR code here"
                sx={{ mb: 2 }}
              />
              
              <Button
                variant="outlined"
                onClick={() => setScanMode(null)}
                sx={{ mb: 2 }}
              >
                ← Back to Options
              </Button>
            </Box>
          )}

          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error}
            </Alert>
          )}
        </Box>
      </DialogContent>
      <DialogActions>
        <Button onClick={handleClose}>Cancel</Button>
        {scanMode === 'manual' && (
          <Button
            onClick={handleManualSubmit}
            variant="contained"
            disabled={!manualInput.trim()}
          >
            Submit
          </Button>
        )}
      </DialogActions>
    </Dialog>
  );
};

export default BarcodeScanner;
