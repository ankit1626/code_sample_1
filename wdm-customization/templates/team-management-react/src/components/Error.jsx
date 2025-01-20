import React from 'react'
import { Box } from '@mui/material'
import { Typography } from '@mui/material';

export default function Error({ errormsg }) {
    return (
        <Box
            sx={{
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center',
                bgcolor: 'background.paper',
                width: 1,
                height: 0.9,
            }}
        >
            <Typography variant="h6" component="div" sx={{ flexGrow: 1, textAlign: 'center' }}>
                {errormsg}
            </Typography>
        </Box>
    )
}
