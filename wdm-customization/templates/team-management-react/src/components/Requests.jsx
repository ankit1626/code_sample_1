/* global wdm_ajax_admin_obj */
import React, { useEffect, useState } from 'react'
import ThumbUpIcon from '@mui/icons-material/ThumbUp';
import ThumbDownIcon from '@mui/icons-material/ThumbDown';
import Box from '@mui/material/Box';
import { DataGrid } from '@mui/x-data-grid';
import { Typography } from '@mui/material';
import { GridActionsCellItem } from '@mui/x-data-grid';

export default function Requests({ eventid, loading, type }) {
    const [internal_loading, setInternalLoading] = useState(false);
    const [requests_data, setRequestsData] = useState([]);

    const initialState = {
        columns: {
            columnVisibilityModel: {
                request_id: false,
                status: type === 'incoming' ? false : true,
            },
        },
        pagination: {
            paginationModel: {
                pageSize: 10,
            },
        },
    }
    const requests = async () => {
        setInternalLoading(true);
        const url = wdm_ajax_admin_obj.ajax_url;
        let formData = new FormData();
        formData.append('action', 'wdm_get_incoming_requests');
        formData.append('event_id', eventid);
        formData.append('type', type);
        formData.append('_ajax_nonce', wdm_ajax_admin_obj.nonce);
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        });
        const data = await response.json();
        if (data.success) {
            setRequestsData(data.data);
        }
        setInternalLoading(false);
    }

    const decline_request = async (request_id) => {
        setInternalLoading(true);
        const url = wdm_ajax_admin_obj.ajax_url;
        let formData = new FormData();
        formData.append('action', 'wdm_decline_team_request');
        formData.append('request_id', request_id);
        formData.append('event_id', eventid);
        formData.append('_ajax_nonce', wdm_ajax_admin_obj.nonce);
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        });
        const data = await response.json();
        if (data.success) {
            requests();
        }
        setInternalLoading(false);
    }

    const accept_request = async (request_id) => {
        setInternalLoading(true);
        const url = wdm_ajax_admin_obj.ajax_url;
        let formData = new FormData();
        formData.append('action', 'wdm_accept_team_request');
        formData.append('request_id', request_id);
        formData.append('_ajax_nonce', wdm_ajax_admin_obj.nonce);
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        });
        const data = await response.json();
        if (data.success) {
            requests();
        } else {
            alert(data.data[0].message);
            requests();
        }
        setInternalLoading(false);
    }
    const common_props = {
        hideable: false,
        autosizeOnMount: true,
        width: 250,
    }
    const columns = [
        {
            field: 'request_id',
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
                    icon={<ThumbUpIcon />}
                    onClick={params.row.status === '0' ? () => accept_request(params.row.request_id) : () => alert('You cannot modify the state of this request')}
                    label="Accept"
                    color={params.row.status === '1' ? 'success' : 'default'}
                />,
                <GridActionsCellItem
                    icon={<ThumbDownIcon />}
                    onClick={params.row.status === '0' ? () => decline_request(params.row.request_id) : () => alert('You cannot modify the state of this request')}
                    label="Decline"
                    color={params.row.status === '-1' ? 'error' : 'default'}
                />
            ],
        }
    ];
    useEffect(() => {
        requests();
    }, [eventid]);

    if (type === 'outgoing') {
        columns.pop();
        columns.push({
            field: 'status',
            headerName: 'Status',
            ...common_props,
            valueFormatter: (value) => {
                if (value === '0') {
                    return 'Pending';
                }
                if (value === '1') {
                    return 'Accepted';
                }
                if (value === '-1') {
                    return 'Declined';
                }
            }
        })
    }
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
                    getRowId={(row) => row.request_id}
                    width={1}
                    rows={requests_data}
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
