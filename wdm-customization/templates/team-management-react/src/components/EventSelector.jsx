/* global wdm_ajax_admin_obj */
import Box from '@mui/material/Box';
import InputLabel from '@mui/material/InputLabel';
import MenuItem from '@mui/material/MenuItem';
import FormControl from '@mui/material/FormControl';
import Select from '@mui/material/Select';
import { useEffect } from 'react';
import { useState } from 'react';
function EventSelector({ eventid, onChange, loading, setLoading }) {
    const [result, setData] = useState({});
    const fetchInfo = async () => {
        setLoading(true);
        const url = wdm_ajax_admin_obj.ajax_url;
        let formData = new FormData();
        formData.append('action', 'wdm_get_events');
        formData.append('_ajax_nonce', wdm_ajax_admin_obj.nonce);
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        });
        const data = await response.json();
        if (data.success) {
            setData(data.data);
            setLoading(false);
        }
    }
    useEffect(async () => {
        fetchInfo();
    }, []);
    onChange(result.event_id);

    return (
        <Box sx={{ height: 0.1, w: 1, mt: 2, p: 2 }}>
            <FormControl fullWidth>
                <InputLabel id="demo-simple-select-helper-label">{loading ? 'Loading...' : parseInt(result.event_id) > 0 ? 'Select Event' : 'You are not enrolled in any team events.'}</InputLabel>
                <Select
                    labelId="demo-simple-select-helper-label"
                    id="demo-simple-select-helper"
                    value={eventid}
                    label={loading ? 'Loading...' : parseInt(result.event_id) > 0 ? 'Select Event' : 'You are not enrolled in any team events.'}
                    disabled={true}
                >
                    <MenuItem value={result.event_id}>
                        {result.event_o_name}
                    </MenuItem>
                </Select>
            </FormControl>
        </Box>
    );
}

export default EventSelector