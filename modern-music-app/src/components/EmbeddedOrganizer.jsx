import React, { useState, useRef, useEffect } from 'react';
import { getBaseUrl } from '../utils/api';

/**
 * EmbeddedOrganizer
 * A permanent embedded tab/view for the Aether Admin portal to run the auto_organizer script.
 * 
 * Props:
 * - fetchRecentlyAdded (function): Callback to refresh the Aether library cache when done.
 */
const EmbeddedOrganizer = ({ fetchRecentlyAdded }) => {
  const [logs, setLogs] = useState([]);
  const [isRunning, setIsRunning] = useState(false);
  const terminalEndRef = useRef(null);

  // Auto-scroll the terminal to the bottom as new logs arrive
  useEffect(() => {
    if (terminalEndRef.current) {
      terminalEndRef.current.scrollIntoView({ behavior: 'smooth' });
    }
  }, [logs]);

  const handleRunSync = async () => {
    if (isRunning) return;
    
    setIsRunning(true);
    setLogs([]); // Clear previous logs on new run
    
    // Add the initial system start message
    setLogs(['<div style="color: #2196f3;">[System] Initiating Auto-Organizer stream...</div>']);

    try {
      // Pass ?api=true to ensure the PHP script skips returning the HTML skeleton
      const response = await fetch(`${getBaseUrl()}/auto_organizer.php?api=true`);
      
      if (!response.body) {
        throw new Error('ReadableStream not supported by the browser.');
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder('utf-8');

      // Process the stream chunk by chunk
      while (true) {
        const { value, done } = await reader.read();
        
        if (done) {
          // Stream is finished
          break;
        }
        
        // Decode the Uint8Array chunk to string
        const chunk = decoder.decode(value, { stream: true });
        
        // Append the new chunk directly to our logs array. 
        // Because PHP flushes distinct HTML divs, we just store them.
        setLogs((prev) => [...prev, chunk]);
      }

      // Add completion message
      setLogs((prev) => [...prev, '<div style="color: #4caf50;">[System] Catalog Update Complete.</div>']);

      // Trigger the background cache refresh in Aether
      if (fetchRecentlyAdded) {
        fetchRecentlyAdded();
      }
      
    } catch (error) {
      setLogs((prev) => [...prev, `<div style="color: #f44336;">[Error] Connection failed: ${error.message}</div>`]);
    } finally {
      // Re-enable the button
      setIsRunning(false);
    }
  };

  return (
    <div className="bg-slate-900/60 backdrop-blur-md p-6 rounded-2xl border border-white/5 flex flex-col gap-6">
      
      {/* Header Section */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h3 className="text-xl font-bold text-white mb-2">Library Staging & Auto-Organizer</h3>
          <p className="text-slate-400 text-sm max-w-xl">
            Automatically scan the Staging folder on the MAMP server, embed cover art, organize by Artist/Album, and instantly force Ampache to rebuild the master catalog.
          </p>
        </div>
        
        <button
          onClick={handleRunSync}
          disabled={isRunning}
          className={`px-6 py-2.5 rounded-lg font-semibold text-white whitespace-nowrap shadow-lg transition-all duration-300 flex items-center gap-2 ${
            isRunning 
              ? 'bg-slate-700 cursor-not-allowed opacity-70 shadow-none' 
              : 'bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-400 hover:to-purple-500 hover:-translate-y-0.5 shadow-purple-500/25'
          }`}
        >
          {isRunning ? (
            <>
              <svg className="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Processing...
            </>
          ) : (
            <>
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              Run Organization & Sync
            </>
          )}
        </button>
      </div>

      {/* Embedded Terminal Window */}
      <div className="bg-black/90 font-mono text-xs text-emerald-400 p-4 rounded-xl border border-white/10 h-96 overflow-y-auto leading-relaxed shadow-inner">
        {logs.length === 0 ? (
          <div className="text-slate-500 italic">Waiting for command...</div>
        ) : (
          logs.map((logChunk, index) => (
             // Because PHP returns pre-formatted color divs, we use dangerouslySetInnerHTML
            <div key={index} dangerouslySetInnerHTML={{ __html: logChunk }} />
          ))
        )}
        <div ref={terminalEndRef} />
      </div>

    </div>
  );
};

export default EmbeddedOrganizer;
