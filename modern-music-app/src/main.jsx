import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App.jsx'
import { AuthProvider } from './context/AuthContext.jsx'
import { PlayerProvider } from './context/PlayerContext.jsx'
import { PlaylistModalProvider } from './context/PlaylistModalContext.jsx'
import { LikedSongsProvider } from './context/LikedSongsContext.jsx'
import { ToastProvider } from './context/ToastContext.jsx'
import { sanitizeClientStorage } from './utils/storageSanitizer.js'
import './index.css'

// Run before any component reads localStorage/sessionStorage (AuthContext, PlayerContext, etc.)
sanitizeClientStorage()

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <ToastProvider>
      <AuthProvider>
        <LikedSongsProvider>
          <PlaylistModalProvider>
            <PlayerProvider>
              <App />
            </PlayerProvider>
          </PlaylistModalProvider>
        </LikedSongsProvider>
      </AuthProvider>
    </ToastProvider>
  </React.StrictMode>,
)
