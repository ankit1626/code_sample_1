/* global wdm_ajax_admin_obj */
import * as React from 'react';
import AppBar from '@mui/material/AppBar';
import Box from '@mui/material/Box';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import Link from '@mui/material/Link';




export default function Header() {
    let sitelogo = wdm_ajax_admin_obj.site_logo;

    return (
        <Box sx={{ flexGrow: 1 }}>
            <AppBar position="static">
                <Toolbar>
                    {typeof sitelogo === 'string' && sitelogo.trim() !== '' && (
                        <div style={{ flexGrow: 1 }} >
                            <a href="/"><img src={sitelogo} alt="bb" className='bb-logo' /></a>
                        </div>)}


                    <Button
                        sx={{ '&:hover': { bgcolor: 'none' } }}
                        color="inherit"
                        component={Link}
                        href="/account"
                    >My Account</Button>

                </Toolbar>
            </AppBar>
        </Box>
    );
}
