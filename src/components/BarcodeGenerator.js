import React, { useEffect, useRef } from 'react';
import { Box, Typography, Paper } from '@mui/material';
import JsBarcode from 'jsbarcode';

const BarcodeGenerator = ({ barcode, title }) => {
  const canvasRef = useRef(null);

  useEffect(() => {
    if (barcode && canvasRef.current) {
      try {
        JsBarcode(canvasRef.current, barcode, {
          format: "CODE128",
          width: 2,
          height: 100,
          displayValue: true,
          fontSize: 16,
          margin: 10,
        });
      } catch (error) {
        console.error('Barcode generation error:', error);
      }
    }
  }, [barcode]);

  return (
    <Box sx={{ textAlign: 'center', p: 2 }}>
      <Typography variant="h6" gutterBottom>
        {title}
      </Typography>
      <Paper
        elevation={2}
        sx={{
          p: 2,
          display: 'inline-block',
          backgroundColor: 'white',
        }}
      >
        <canvas ref={canvasRef} />
      </Paper>
      <Typography variant="body2" color="text.secondary" sx={{ mt: 2 }}>
        Barcode: {barcode}
      </Typography>
    </Box>
  );
};

export default BarcodeGenerator;
