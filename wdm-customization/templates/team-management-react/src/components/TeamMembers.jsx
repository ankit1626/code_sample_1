/* global wdm_ajax_admin_obj */
import React, { useEffect, useState } from 'react'
import Box from '@mui/material/Box';
import { DataGrid } from '@mui/x-data-grid';
import { Typography } from '@mui/material';
import { GridActionsCellItem } from '@mui/x-data-grid';
import GroupRemoveIcon from '@mui/icons-material/GroupRemove';

export default function TeamMembers({ eventid, loading }) {
    const [member_data, setMemberData] = useState([]);
    const [internal_loading, setInternalLoading] = useState(false);

    const remove_members = async (user_id, team_id) => {
        setInternalLoading(true);
        const url = wdm_ajax_admin_obj.ajax_url;
        let formData = new FormData();
        formData.append('action', 'wdm_remove_team_members');
        formData.append('event_id', eventid);
        formData.append('user_id', user_id);
        formData.append('team_id', team_id);
        formData.append('_ajax_nonce', wdm_ajax_admin_obj.nonce);
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        });
        const data = await response.json();
        if (data.success) {
            team_members();
        } else {
            alert(data.data[0].message);
            team_members();
        }
        setInternalLoading(false);
    }
    const team_members = async () => {
        setInternalLoading(true);
        const url = wdm_ajax_admin_obj.ajax_url;
        let formData = new FormData();
        formData.append('action', 'wdm_get_team_members');
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
        team_members();
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
                team_id: false,
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
            field: 'team_id',
            headerName: 'Team ID',
            ...common_props,
        },
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
                    icon={<GroupRemoveIcon />}
                    onClick={() => remove_members(params.row.user_id, params.row.team_id)}
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