/* global wdm_ajax_admin_obj */
import './App.css';
import * as React from 'react';
import Header from './components/header';
import EventSelector from './components/EventSelector';
import BasicTabs from './components/Tabs';
import Error from './components/Error';
import { useState } from 'react';

function App() {
  const [eventid, setEventId] = useState('');
  const [loading, setLoading] = useState(false);
  const userId = wdm_ajax_admin_obj.user_id;

  return (
    <>
      <Header />
      {userId === '0' && <Error errormsg={'Please Login To Access This Page'} />}
      {userId !== '0' && <EventSelector eventid={eventid} onChange={setEventId} loading={loading} setLoading={setLoading} />}
      {userId !== '0' && <BasicTabs eventid={eventid} loading={loading} />}
    </>
  )
}

export default App;
