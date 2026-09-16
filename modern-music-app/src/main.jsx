import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App.jsx'
import { AuthProvider } from './context/AuthContext.jsx'
import { PlayerProvider } from './context/PlayerContext.jsx'
import { PlaylistModalProvider } from './context/PlaylistModalContext.jsx'
import { ToastProvider } from './context/ToastContext.jsx'
import './index.css'

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <ToastProvider>
      <AuthProvider>
        <PlaylistModalProvider>
          <PlayerProvider>
            <App />
          </PlayerProvider>
        </PlaylistModalProvider>
      </AuthProvider>
    </ToastProvider>
  </React.StrictMode>,
)
