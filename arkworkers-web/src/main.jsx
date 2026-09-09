import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { PersistQueryClientProvider } from '@tanstack/react-query-persist-client';
import { createAsyncStoragePersister } from '@tanstack/query-async-storage-persister';
import { get, set, del } from 'idb-keyval';
import './styles/global.css';
import App from './App.jsx';
import { primeCsrfCookie } from './api/client.js';
import { queryClient, resumeQueuedMutationsOnReconnect } from './queryClient.js';

const persister = createAsyncStoragePersister({
  storage: {
    getItem: get,
    setItem: set,
    removeItem: del,
  },
});

// Fires once at load, so any subsequent write from any screen
// (account requests, invite activation, login) already has the
// XSRF-TOKEN cookie/header pair available. Harmless no-op on the
// Capacitor build, which never matches the stateful domain anyway.
primeCsrfCookie();

// Replays mutations queued while offline, including ones restored
// from a previous session, the moment connectivity returns. Also
// runs once now, in case the app is reopening already online with
// leftover queued work from the last offline session.
resumeQueuedMutationsOnReconnect();
queryClient.resumePausedMutations();

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <PersistQueryClientProvider
      client={queryClient}
      persistOptions={{
        persister,
        // Paused (offline) mutations get written to IndexedDB too, not
        // just queries, so a queued task completion survives the app
        // being closed and reopened while still offline.
        dehydrateOptions: {
          shouldDehydrateMutation: () => true,
        },
      }}
    >
      <App />
    </PersistQueryClientProvider>
  </StrictMode>,
);
