import { useState, useEffect, useRef } from 'react';
import { Volume2, Headphones, Radio, Tv, Laptop, Smartphone, Speaker, Check, ChevronDown, Cast, Sparkles } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';

/**
 * OutputMenu Component
 * Provides visual indication and interactive switching for audio playback destinations
 * utilizing standard W3C Media Capture and Streams API & Audio Output Devices API (setSinkId),
 * with fallback support for Apple AirPlay (WebKitPlaybackTargetAvailability / x-webkit-airplay).
 */
export default function OutputMenu({ compact = false }) {
  const { audioRef } = usePlayer();
  const [devices, setDevices] = useState([]);
  const [selectedDeviceId, setSelectedDeviceId] = useState(() => {
    if (typeof window !== 'undefined') {
      return localStorage.getItem('aether_audio_sink_id') || 'default';
    }
    return 'default';
  });
  const [isOpen, setIsOpen] = useState(false);
  const [isSinkSupported, setIsSinkSupported] = useState(true);
  const [hasAirPlay, setHasAirPlay] = useState(false);
  const [hasPermission, setHasPermission] = useState(false);
  const dropdownRef = useRef(null);

  // Helper to determine the device category, label, and appropriate icon
  const getDeviceMeta = (device, isCurrent = false) => {
    const label = (device?.label || '').toLowerCase();
    const id = device?.deviceId || 'default';

    if (label.includes('airplay') || label.includes('apple tv') || label.includes('homepod')) {
      return {
        type: 'AirPlay',
        name: device?.label || 'AirPlay Device',
        icon: Radio,
        badgeColor: 'text-indigo-400 bg-indigo-500/15 border-indigo-500/30'
      };
    }
    if (label.includes('bluetooth') || label.includes('airpods') || label.includes('buds') || label.includes('wireless') || label.includes('wh-') || label.includes('wf-') || label.includes('beats')) {
      return {
        type: 'Bluetooth',
        name: device?.label || 'Bluetooth Audio',
        icon: Headphones,
        badgeColor: 'text-sky-400 bg-sky-500/15 border-sky-500/30'
      };
    }
    if (label.includes('headphone') || label.includes('headset') || label.includes('earphone') || label.includes('jack') || label.includes('aux')) {
      return {
        type: 'Wired',
        name: device?.label || 'Wired Headphones',
        icon: Headphones,
        badgeColor: 'text-purple-400 bg-purple-500/15 border-purple-500/30'
      };
    }
    if (label.includes('iphone') || label.includes('ipad') || label.includes('phone')) {
      return {
        type: 'Mobile',
        name: device?.label || 'iPhone Speaker',
        icon: Smartphone,
        badgeColor: 'text-emerald-400 bg-emerald-500/15 border-emerald-500/30'
      };
    }
    if (label.includes('macbook') || label.includes('laptop') || label.includes('internal') || label.includes('built-in')) {
      return {
        type: 'Built-in',
        name: device?.label || 'MacBook Speaker',
        icon: Laptop,
        badgeColor: 'text-amber-400 bg-amber-500/15 border-amber-500/30'
      };
    }
    if (label.includes('hdmi') || label.includes('display') || label.includes('tv')) {
      return {
        type: 'Display/TV',
        name: device?.label || 'HDMI / TV Display',
        icon: Tv,
        badgeColor: 'text-rose-400 bg-rose-500/15 border-rose-500/30'
      };
    }

    // Default system fallback
    const isMobile = typeof navigator !== 'undefined' && /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
    return {
      type: isMobile ? 'Speaker' : 'Default Output',
      name: device?.label || (isMobile ? 'Device Speaker' : 'Default System Output'),
      icon: isMobile ? Smartphone : Speaker,
      badgeColor: 'text-slate-400 bg-slate-500/15 border-slate-500/30'
    };
  };

  const refreshDevices = async () => {
    if (typeof navigator === 'undefined' || !navigator.mediaDevices?.enumerateDevices) {
      setIsSinkSupported(false);
      return;
    }

    try {
      const allDevices = await navigator.mediaDevices.enumerateDevices();
      const audioOutputs = allDevices.filter(d => d.kind === 'audiooutput');
      
      const namedOutputs = audioOutputs.filter(d => Boolean(d.label));
      if (namedOutputs.length > 0) {
        setHasPermission(true);
      }

      // If no explicit audiooutput returned (e.g. mobile Safari), create a synthetic default device
      if (audioOutputs.length === 0) {
        const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
        setDevices([{
          deviceId: 'default',
          kind: 'audiooutput',
          label: isMobile ? 'Phone Speaker' : 'Default Audio Output'
        }]);
      } else {
        setDevices(audioOutputs);
      }
    } catch (err) {
      console.debug('Failed to enumerate audio devices:', err);
    }
  };

  useEffect(() => {
    // Check if HTMLAudioElement supports setSinkId
    const testAudio = document.createElement('audio');
    const sinkSupported = typeof testAudio.setSinkId === 'function';
    setIsSinkSupported(sinkSupported);

    // Check Apple AirPlay WebKit availability
    if (window.WebKitPlaybackTargetAvailabilityEvent || 'webkitCurrentPlaybackTargetIsWireless' in testAudio) {
      setHasAirPlay(true);
    }

    // Ensure the live <audio> element always exposes the WebKit AirPlay fallback attribute
    if (audioRef?.current) {
      audioRef.current.setAttribute('x-webkit-airplay', 'allow');
    }

    refreshDevices();

    if (navigator.mediaDevices?.addEventListener) {
      navigator.mediaDevices.addEventListener('devicechange', refreshDevices);
      return () => {
        navigator.mediaDevices.removeEventListener('devicechange', refreshDevices);
      };
    }
  }, []);

  // Listen to AirPlay availability on current audio element
  useEffect(() => {
    const audio = audioRef?.current;
    if (!audio) return;

    const handleAirPlayAvailability = (event) => {
      setHasAirPlay(event.availability === 'available');
    };

    if (window.WebKitPlaybackTargetAvailabilityEvent) {
      audio.addEventListener('webkitplaybacktargetavailabilitychanged', handleAirPlayAvailability);
      return () => {
        audio.removeEventListener('webkitplaybacktargetavailabilitychanged', handleAirPlayAvailability);
      };
    }
  }, [audioRef?.current]);

  // Apply setSinkId when selected device changes or audio element updates
  useEffect(() => {
    if (!isSinkSupported || !audioRef?.current || !selectedDeviceId) return;
    if (typeof audioRef.current.setSinkId === 'function') {
      audioRef.current.setSinkId(selectedDeviceId).catch((e) => {
        console.warn('[Aether Audio] setSinkId notice:', e);
      });
    }
  }, [selectedDeviceId, audioRef?.current, isSinkSupported]);

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
        setIsOpen(false);
      }
    };
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      document.addEventListener('touchstart', handleClickOutside);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('touchstart', handleClickOutside);
    };
  }, [isOpen]);

  const handleSelectDevice = async (deviceId) => {
    setSelectedDeviceId(deviceId);
    if (typeof window !== 'undefined') {
      localStorage.setItem('aether_audio_sink_id', deviceId);
    }

    if (audioRef?.current && typeof audioRef.current.setSinkId === 'function') {
      try {
        await audioRef.current.setSinkId(deviceId);
      } catch (err) {
        console.warn('[Aether Audio] Failed to switch output destination:', err);
      }
    }
    setIsOpen(false);
  };

  const handleTriggerAirPlay = () => {
    if (audioRef?.current && typeof audioRef.current.webkitShowPlaybackTargetPicker === 'function') {
      audioRef.current.webkitShowPlaybackTargetPicker();
      setIsOpen(false);
    }
  };

  const requestDeviceLabels = async () => {
    try {
      if (navigator.mediaDevices?.getUserMedia) {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        stream.getTracks().forEach(track => track.stop());
        setHasPermission(true);
        refreshDevices();
      }
    } catch (e) {
      console.debug('User dismissed device permission dialog');
    }
  };

  // Find metadata for current active device
  const currentDevice = devices.find(d => d.deviceId === selectedDeviceId) || devices[0];
  const activeMeta = getDeviceMeta(currentDevice, true);
  const ActiveIcon = activeMeta.icon;

  return (
    <div className="relative inline-block" ref={dropdownRef}>
      {/* Visual Indicator & Interactive Trigger Button */}
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className={`flex items-center gap-2 rounded-xl transition-all select-none ${
          compact
            ? 'w-12 h-12 text-slate-300 hover:text-white hover:bg-white/10 active:scale-95 flex items-center justify-center'
            : 'px-2.5 py-1.5 bg-slate-800/80 hover:bg-slate-700/80 border border-white/10 text-slate-200 text-xs font-medium shadow-sm hover:border-purple-500/40'
        }`}
        title={`Audio Output: ${activeMeta.name} (${activeMeta.type})`}
        aria-label="Select Audio Output Destination"
        aria-expanded={isOpen}
      >
        <div className="relative flex items-center justify-center">
          <ActiveIcon size={compact ? 18 : 15} className="text-purple-400" />
          <span className="absolute -bottom-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-emerald-400 ring-2 ring-slate-900 animate-pulse" />
        </div>

        {!compact && (
          <>
            <span className="max-w-[110px] xl:max-w-[140px] truncate text-[11px] font-medium text-slate-300">
              {activeMeta.name}
            </span>
            <ChevronDown size={13} className={`text-slate-400 transition-transform ${isOpen ? 'rotate-180 text-purple-400' : ''}`} />
          </>
        )}
      </button>

      {/* Interactive Dropdown Menu */}
      {isOpen && (
        <div
          className="absolute bottom-full right-0 mb-2 z-50 w-[min(20rem,calc(100vw-1rem))] rounded-2xl bg-slate-950/98 backdrop-blur-2xl border border-white/15 p-2.5 shadow-[0_15px_40px_rgba(0,0,0,0.85)] animate-in fade-in zoom-in-95 duration-150"
        >
          {/* Header */}
          <div className="flex items-center justify-between px-2.5 py-2 border-b border-white/10 mb-1.5">
            <div className="flex items-center gap-1.5">
              <Volume2 size={16} className="text-purple-400" />
              <h4 className="text-xs font-bold text-white uppercase tracking-wider">Audio Destination</h4>
            </div>
            <span className="text-[10px] font-mono px-1.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30">
              {devices.length} {devices.length === 1 ? 'Device' : 'Devices'}
            </span>
          </div>

          {/* AirPlay Quick-Trigger Option (iOS Safari & WebKit support) */}
          {hasAirPlay && (
            <div className="mb-2 p-1.5 rounded-xl bg-gradient-to-r from-purple-900/30 to-indigo-900/30 border border-purple-500/30">
              <button
                type="button"
                onClick={handleTriggerAirPlay}
                className="w-full min-h-12 flex items-center justify-between px-3 py-2 rounded-lg bg-purple-600/30 hover:bg-purple-600/50 active:scale-98 text-white transition-all text-xs font-semibold"
              >
                <div className="flex items-center gap-2">
                  <Cast size={16} className="text-indigo-300" />
                  <span>Connect AirPlay / Bluetooth</span>
                </div>
                <Radio size={14} className="text-purple-300 animate-pulse" />
              </button>
            </div>
          )}

          {/* List of Detected Output Devices */}
          <div className="space-y-1 max-h-56 overflow-y-auto touch-scroll pr-0.5">
            {devices.map((device, idx) => {
              const meta = getDeviceMeta(device);
              const ItemIcon = meta.icon;
              const isSelected = (device.deviceId === selectedDeviceId) || (selectedDeviceId === 'default' && idx === 0);

              return (
                <button
                  key={device.deviceId || `device-${idx}`}
                  type="button"
                  onClick={() => handleSelectDevice(device.deviceId)}
                  className={`w-full min-h-12 flex items-center justify-between p-2 rounded-xl text-left transition-all ${
                    isSelected
                      ? 'bg-purple-500/25 border border-purple-500/50 shadow-[0_0_12px_rgba(168,85,247,0.2)]'
                      : 'hover:bg-white/10 active:bg-white/15'
                  }`}
                >
                  <div className="flex items-center gap-2.5 min-w-0 flex-1 pr-2">
                    <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 border ${meta.badgeColor}`}>
                      <ItemIcon size={16} />
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className={`text-xs font-medium truncate ${isSelected ? 'text-white font-semibold' : 'text-slate-200'}`}>
                        {meta.name}
                      </p>
                      <span className="text-[10px] text-slate-400 font-mono">
                        {meta.type}
                      </span>
                    </div>
                  </div>

                  {isSelected && (
                    <div className="w-5 h-5 rounded-full bg-purple-500 flex items-center justify-center text-white shrink-0 shadow-sm">
                      <Check size={12} strokeWidth={3} />
                    </div>
                  )}
                </button>
              );
            })}
          </div>

          {/* Browser Permission Unlock Helper (if labels are obscured by browser security) */}
          {!hasPermission && (
            <div className="mt-2 pt-2 border-t border-white/10 px-1 text-center">
              <button
                type="button"
                onClick={requestDeviceLabels}
                className="w-full min-h-12 py-1 px-2 rounded-lg bg-white/5 hover:bg-white/10 text-[11px] text-slate-300 hover:text-white flex items-center justify-center gap-1.5 transition-colors border border-white/5"
              >
                <Sparkles size={12} className="text-amber-400" />
                <span>Show Full Device Names</span>
              </button>
            </div>
          )}

          {/* Browser compatibility footer notes */}
          {!isSinkSupported && !hasAirPlay && (
            <div className="mt-2 pt-2 border-t border-white/10 px-2 text-[10px] text-slate-400 text-center">
              Standard audio routed to system default.
            </div>
          )}
        </div>
      )}
    </div>
  );
}

