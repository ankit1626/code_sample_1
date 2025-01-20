import * as React from 'react';
import PropTypes from 'prop-types';
import Tabs from '@mui/material/Tabs';
import Tab from '@mui/material/Tab';
import Box from '@mui/material/Box';
import Members from './Members';
import Requests from './Requests';
import TeamMembers from './TeamMembers';
import { Typography } from '@mui/material';

function CustomTabPanel(props) {
    const { children, value, index, ...other } = props;

    return (
        <div
            role="tabpanel"
            hidden={value !== index}
            id={`simple-tabpanel-${index}`}
            aria-labelledby={`simple-tab-${index}`}
            {...other}
        >
            {value === index && <Box sx={{ p: 3 }}>{children}</Box>}
        </div>
    );
}

CustomTabPanel.propTypes = {
    children: PropTypes.node,
    index: PropTypes.number.isRequired,
    value: PropTypes.number.isRequired,
};

function a11yProps(index) {
    return {
        id: `simple-tab-${index}`,
        'aria-controls': `simple-tabpanel-${index}`,
    };
}

export default function BasicTabs({ eventid, loading, setLoading }) {
    const [value, setValue] = React.useState(0);

    const handleChange = (event, newValue) => {
        setValue(newValue);
    };

    return (
        <Box sx={{ width: 1, mt: 1, p: 2, pb: 0, mb: 1 }}>
            <Box sx={{ borderBottom: 1, borderColor: 'divider' }}>
                <Tabs value={value} onChange={handleChange} aria-label="basic tabs example">
                    <Tab label="Chapter Members" {...a11yProps(0)} />
                    <Tab label="Team Members" {...a11yProps(1)} />
                    <Tab label="Incoming Requests" {...a11yProps(2)} />
                    <Tab label="Outgoing Requests" {...a11yProps(3)} />
                </Tabs>
            </Box>
            <CustomTabPanel value={value} index={0}>
                <Members eventid={eventid} loading={loading} />
            </CustomTabPanel>
            <CustomTabPanel value={value} index={1}>
                <TeamMembers eventid={eventid} loading={loading} />
            </CustomTabPanel>
            <CustomTabPanel value={value} index={2}>
                <Requests eventid={eventid} loading={loading} type="incoming" />
            </CustomTabPanel>
            <CustomTabPanel value={value} index={3}>
                <Requests eventid={eventid} loading={loading} type="outgoing" />
            </CustomTabPanel>
        </Box>
    );
}