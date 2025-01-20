/* global wdm_ajax_admin_obj */
import React, { useEffect, useState } from 'react'
import CircularProgress from '@mui/material/CircularProgress';
import Box from '@mui/material/Box';
import { DataGrid } from '@mui/x-data-grid';
import { Typography } from '@mui/material';
import { GridActionsCellItem } from '@mui/x-data-grid';
import AddCircleIcon from '@mui/icons-material/AddCircle';

export default function Members({ eventid, loading }) {
    const [member_data, setMemberData] = useState([]);
    const [internal_loading, setInternalLoading] = useState(false);
    const [add_loading, setAddLoading] = useState(false);
    const add_member = async (user_id) => {
        setInternalLoading(true);
        const url = wdm_ajax_admin_obj.ajax_url;
        let formData = new FormData();
        formData.append('action', 'wdm_add_team_member');
        formData.append('event_id', eventid);
        formData.append('user_id', user_id);
        formData.append('_ajax_nonce', wdm_ajax_admin_obj.nonce);
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        });
        const data = await response.json();
        if (data.success) {
            subacc_users();
        }
        setInternalLoading(false);
    }
    const subacc_users = async () => {
        setInternalLoading(true);
        const url = wdm_ajax_admin_obj.ajax_url;
        let formData = new FormData();
        formData.append('action', 'wdm_list_subacc_users');
        formData.append('event_id', eventid);
        formData.append('_ajax_nonce', wdm_ajax_admin_obj.nonce);
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        });
        const data = await response.json();
        if (data.success) {
            setMemberData(data.data);
        }
        setInternalLoading(false);
    }

    useEffect(() => {
        subacc_users();
    }, [eventid]);
    const common_props = {
        hideable: false,
        autosizeOnMount: true,
        width: 250,
    }
    const initialState = {
        columns: {
            columnVisibilityModel: {
                user_id: false,
            },
        },
        pagination: {
            paginationModel: {
                pageSize: 10,
            },
        },
    }

    const columns = [
        {
            field: 'user_id',
            headerName: 'ID',
            ...common_props,
        },
        {
            field: 'display_name',
            headerName: 'Name',
            ...common_props,
        },
        {
            field: 'user_email',
            headerName: 'Email',
            ...common_props,
        },
        {
            field: 'actions',
            type: 'actions',
            headerName: 'Action',
            getActions: (params) => [
                <GridActionsCellItem
                    icon={<AddCircleIcon />}
                    onClick={() => add_member(params.row.user_id)}
                    label="Delete" />
            ],
        }
    ];
    return (
        <Box sx={{ width: 1, mt: 1, p: 2 }}>
            <Box sx={{
                bgcolor: 'background.paper',
                boxShadow: 1,
                borderRadius: 2,
                p: 2,
                width: 1,
                height: 0.6,
                textAlign: 'center',
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center',
            }}>
                {eventid === '' && <Typography variant="p" component="div" sx={{ flexGrow: 1, textAlign: 'center' }}>Please select an event</Typography>}
                {eventid !== '' && <DataGrid
                    getRowId={(row) => row.user_id}
                    width={1}
                    rows={member_data}
                    columns={columns}
                    initialState={initialState}
                    pageSizeOptions={[5]}
                    checkboxSelection={false}
                    disableRowSelectionOnClick={true}
                    cellSelection={false}
                    loading={loading || internal_loading}
                    slotProps={{
                        loadingOverlay: {
                            variant: 'linear-progress',
                            noRowsVariant: 'linear-progress',
                        },
                    }}
                />}
            </Box>
        </Box>
    )
}