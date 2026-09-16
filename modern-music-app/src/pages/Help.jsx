import { HelpCircle, Keyboard, Server, Shield } from 'lucide-react';

export default function Help() {
  return (
    <div className="pb-24 max-w-4xl mx-auto">
      <div className="flex items-center gap-4 mb-8">
        <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
          <HelpCircle size={24} className="text-white" />
        </div>
        <h1 className="text-3xl font-bold text-white">Help & Information</h1>
      </div>
      
      <div className="space-y-6">
        <div className="bg-slate-900/40 backdrop-blur-sm p-6 rounded-xl border border-white/5">
          <div className="flex items-center gap-3 mb-4">
            <Server className="text-purple-400" size={24} />
            <h2 className="text-xl font-semibold text-white">About Aether Audio</h2>
          </div>
          <p className="text-slate-300 leading-relaxed">
            Aether Audio is a lightweight, headless React frontend designed to modernize your existing Ampache music server. 
            It directly interfaces with your 2011 MacBook Pro via the Subsonic REST API. By outsourcing the rendering logic 
            to your browser, the server only has to deliver lightweight JSON and raw audio files, drastically improving 
            performance and preventing the 2011 hardware from overheating.
          </p>
        </div>

        <div className="bg-slate-900/40 backdrop-blur-sm p-6 rounded-xl border border-white/5">
          <div className="flex items-center gap-3 mb-4">
            <Keyboard className="text-purple-400" size={24} />
            <h2 className="text-xl font-semibold text-white">Keyboard Shortcuts</h2>
          </div>
          <ul className="space-y-3 text-slate-300">
            <li className="flex items-center justify-between">
              <span>Play / Pause</span>
              <kbd className="bg-slate-800 border border-white/10 px-2 py-1 rounded text-sm font-mono">Space</kbd>
            </li>
            <li className="flex items-center justify-between">
              <span>Next Track</span>
              <kbd className="bg-slate-800 border border-white/10 px-2 py-1 rounded text-sm font-mono">Ctrl + &rarr;</kbd>
            </li>
            <li className="flex items-center justify-between">
              <span>Previous Track</span>
              <kbd className="bg-slate-800 border border-white/10 px-2 py-1 rounded text-sm font-mono">Ctrl + &larr;</kbd>
            </li>
            <li className="flex items-center justify-between">
              <span>Search</span>
              <kbd className="bg-slate-800 border border-white/10 px-2 py-1 rounded text-sm font-mono">/</kbd>
            </li>
          </ul>
        </div>

        <div className="bg-slate-900/40 backdrop-blur-sm p-6 rounded-xl border border-white/5">
          <div className="flex items-center gap-3 mb-4">
            <Shield className="text-purple-400" size={24} />
            <h2 className="text-xl font-semibold text-white">Troubleshooting</h2>
          </div>
          <div className="space-y-4 text-slate-300">
            <div>
              <h4 className="font-semibold text-white mb-1">Music isn't streaming / Player says 0:00</h4>
              <p className="text-sm">Verify that your external hard drive (`/Volumes/Music/`) is firmly plugged into the 2011 Mac server and properly mounted in macOS.</p>
            </div>
            <div>
              <h4 className="font-semibold text-white mb-1">Missing Album Art</h4>
              <p className="text-sm">Ampache relies on embedded ID3 tags or `cover.jpg` files in the folder. If an album has no art, it will display a generic icon.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
