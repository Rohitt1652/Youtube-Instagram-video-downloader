import React, { useState, useEffect } from 'react';
import Navbar from './components/Navbar';
import Hero from './components/Hero';
import MediaPreview from './components/MediaPreview';
import DownloadProgressModal from './components/DownloadProgressModal';
import DownloadHistory from './components/DownloadHistory';
import Footer from './components/Footer';
import { getMediaInfo, createMediaDownload } from './api/client';
import { Zap, Sparkles, ShieldCheck, Film, Music, CheckCircle2 } from 'lucide-react';

export default function App() {
  const [url, setUrl] = useState('');
  const [isLoadingInfo, setIsLoadingInfo] = useState(false);
  const [mediaInfo, setMediaInfo] = useState(null);
  const [error, setError] = useState(null);
  const [activeJob, setActiveJob] = useState(null);
  const [history, setHistory] = useState([]);
  const [isHistoryOpen, setIsHistoryOpen] = useState(false);

  // Load history from localStorage
  useEffect(() => {
    try {
      const stored = localStorage.getItem('mediagrab_history');
      if (stored) {
        setHistory(JSON.parse(stored));
      }
    } catch {
      // Ignore localStorage errors
    }
  }, []);

  // Save history to localStorage
  const saveToHistory = (item) => {
    try {
      const updated = [item, ...history.filter((h) => h.jobId !== item.jobId)].slice(0, 20);
      setHistory(updated);
      localStorage.setItem('mediagrab_history', JSON.stringify(updated));
    } catch {
      // Ignore
    }
  };

  const handleClearHistory = () => {
    setHistory([]);
    try {
      localStorage.removeItem('mediagrab_history');
    } catch {
      // Ignore
    }
  };

  // Submit URL to fetch metadata
  const handleFetchMedia = async (e) => {
    if (e && e.preventDefault) e.preventDefault();

    const cleanUrl = url.trim();
    if (!cleanUrl) {
      setError({ code: 'EMPTY_URL', message: 'Please paste a valid media URL.' });
      return;
    }

    if (!cleanUrl.startsWith('https://')) {
      setError({ code: 'INSECURE_URL', message: 'Only secure HTTPS URLs are permitted.' });
      return;
    }

    setIsLoadingInfo(true);
    setError(null);
    setMediaInfo(null);

    try {
      const response = await getMediaInfo(cleanUrl);
      if (response.success && response.data) {
        setMediaInfo(response.data);
      } else {
        setError(response.error || { message: 'Failed to extract media information.' });
      }
    } catch (err) {
      if (err.response?.data?.error) {
        setError(err.response.data.error);
      } else if (err.code === 'ECONNABORTED') {
        setError({ code: 'TIMEOUT', message: 'Request timed out while inspecting media URL.' });
      } else {
        setError({
          code: 'NETWORK_ERROR',
          message: err.message || 'Unable to connect to MediaGrab API server. Please ensure backend is running.',
        });
      }
    } finally {
      setIsLoadingInfo(false);
    }
  };

  // Trigger download process
  const handleStartDownload = async (formatId, formatObj) => {
    setError(null);
    try {
      const response = await createMediaDownload(url.trim(), formatId);
      if (response.success && response.job_id) {
        setActiveJob({
          jobId: response.job_id,
          format: formatObj,
          title: mediaInfo?.title || 'Media Item',
        });
      } else {
        setError(response.error || { message: 'Failed to initiate download job.' });
      }
    } catch (err) {
      if (err.response?.data?.error) {
        setError(err.response.data.error);
      } else {
        setError({ message: 'Unable to start download process.' });
      }
    }
  };

  const handleJobCompleted = (jobResult) => {
    saveToHistory({
      jobId: activeJob?.jobId,
      title: activeJob?.title,
      platform: mediaInfo?.platform,
      quality: activeJob?.format?.quality,
      downloadUrl: jobResult.download_url,
      timestamp: new Date().toISOString(),
    });
  };

  return (
    <div className="min-h-screen flex flex-col bg-[#f8fafc] text-slate-800 selection:bg-sky-500/20 selection:text-sky-700 relative overflow-hidden">
      {/* Dynamic Ambient Sky-Blue Background Glow Elements */}
      <div className="ambient-glow-sky-1"></div>
      <div className="ambient-glow-sky-2"></div>
      <div className="ambient-glow-cyan"></div>

      {/* Navbar */}
      <Navbar
        onOpenHistory={() => setIsHistoryOpen(true)}
        historyCount={history.length}
      />

      {/* Main Container */}
      <main className="flex-1 flex flex-col items-center justify-start pt-4 pb-12 relative z-10 w-full">
        {/* Hero & Input */}
        <Hero
          url={url}
          setUrl={(newUrl) => {
            setUrl(newUrl);
            if (error) setError(null);
          }}
          onSubmit={handleFetchMedia}
          isLoading={isLoadingInfo}
          error={error}
        />

        {/* Media Preview & Formats Card */}
        {mediaInfo && (
          <MediaPreview
            media={mediaInfo}
            onDownload={handleStartDownload}
            isDownloading={!!activeJob}
          />
        )}

        {/* Feature Cards Showcase (when no URL is fetched yet) */}
        {!mediaInfo && (
          <div className="w-full max-w-5xl mx-auto px-4 mt-8 sm:mt-12 animate-in fade-in duration-500">
            {/* 3 Core Highlights */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
              <div className="bg-white p-6 sm:p-7 rounded-3xl border border-sky-100 hover:border-sky-300 shadow-lg shadow-sky-500/5 transition-all duration-300 group hover:-translate-y-1">
                <div className="w-12 h-12 rounded-2xl bg-sky-50 border border-sky-200 text-sky-600 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform shadow-2xs">
                  <Zap className="w-6 h-6" />
                </div>
                <h3 className="font-heading font-extrabold text-lg text-slate-900 mb-2">Instant Stream Parsing</h3>
                <p className="text-slate-600 text-xs sm:text-sm leading-relaxed">
                  Direct DASH and progressive stream manifest inspection with live quality discovery.
                </p>
              </div>

              <div className="bg-white p-6 sm:p-7 rounded-3xl border border-sky-100 hover:border-sky-300 shadow-lg shadow-sky-500/5 transition-all duration-300 group hover:-translate-y-1">
                <div className="w-12 h-12 rounded-2xl bg-sky-50 border border-sky-200 text-sky-600 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform shadow-2xs">
                  <Sparkles className="w-6 h-6" />
                </div>
                <h3 className="font-heading font-extrabold text-lg text-slate-900 mb-2">Up to 4K Ultra HD</h3>
                <p className="text-slate-600 text-xs sm:text-sm leading-relaxed">
                  Lossless FFmpeg server remuxing for 1080p, 1440p, 4K video and studio 320kbps MP3 audio.
                </p>
              </div>

              <div className="bg-white p-6 sm:p-7 rounded-3xl border border-sky-100 hover:border-sky-300 shadow-lg shadow-sky-500/5 transition-all duration-300 group hover:-translate-y-1">
                <div className="w-12 h-12 rounded-2xl bg-sky-50 border border-sky-200 text-sky-600 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform shadow-2xs">
                  <ShieldCheck className="w-6 h-6" />
                </div>
                <h3 className="font-heading font-extrabold text-lg text-slate-900 mb-2">100% Private & Safe</h3>
                <p className="text-slate-600 text-xs sm:text-sm leading-relaxed">
                  Hardened SSRF defenses, zero credential collection, and automatic temporary file cleanup.
                </p>
              </div>
            </div>

            {/* How It Works Steps */}
            <div className="text-center mb-12">
              <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-sky-50 border border-sky-200 text-[11px] font-bold text-sky-700 uppercase tracking-widest mb-3 shadow-2xs">
                Simple Workflow
              </div>
              <h2 className="font-heading font-black text-2xl sm:text-3xl text-slate-900 mb-3">
                How MediaGrab Works
              </h2>
              <p className="text-slate-600 text-sm max-w-xl mx-auto mb-10">
                Three simple steps to save your authorized content directly to your computer or phone.
              </p>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-6 text-left">
                <div className="p-6 rounded-2xl bg-white border border-slate-200/80 hover:border-sky-300 shadow-sm transition duration-200">
                  <span className="text-[11px] font-mono font-extrabold text-sky-700 bg-sky-50 px-2.5 py-1 rounded-lg border border-sky-200 mb-3 inline-block">
                    STEP 01
                  </span>
                  <h4 className="font-heading font-bold text-slate-900 text-base mb-1.5">Paste Media Link</h4>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    Copy any authorized YouTube or Instagram URL and paste it into the search box above.
                  </p>
                </div>

                <div className="p-6 rounded-2xl bg-white border border-slate-200/80 hover:border-sky-300 shadow-sm transition duration-200">
                  <span className="text-[11px] font-mono font-extrabold text-sky-700 bg-sky-50 px-2.5 py-1 rounded-lg border border-sky-200 mb-3 inline-block">
                    STEP 02
                  </span>
                  <h4 className="font-heading font-bold text-slate-900 text-base mb-1.5">Select Quality</h4>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    Choose from available video resolutions (360p to 4K) or direct audio formats (MP3 / M4A).
                  </p>
                </div>

                <div className="p-6 rounded-2xl bg-white border border-slate-200/80 hover:border-sky-300 shadow-sm transition duration-200">
                  <span className="text-[11px] font-mono font-extrabold text-sky-700 bg-sky-50 px-2.5 py-1 rounded-lg border border-sky-200 mb-3 inline-block">
                    STEP 03
                  </span>
                  <h4 className="font-heading font-bold text-slate-900 text-base mb-1.5">Direct Auto-Download</h4>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    The server merges the streams and saves the file directly to your device with zero extra clicks.
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}
      </main>

      {/* Active Download Progress Modal */}
      {activeJob && (
        <DownloadProgressModal
          jobId={activeJob.jobId}
          format={activeJob.format}
          title={activeJob.title}
          onClose={() => setActiveJob(null)}
          onCompleted={handleJobCompleted}
        />
      )}

      {/* Local Download History Drawer */}
      <DownloadHistory
        isOpen={isHistoryOpen}
        onClose={() => setIsHistoryOpen(false)}
        history={history}
        onClearHistory={handleClearHistory}
      />

      {/* Footer */}
      <Footer />
    </div>
  );
}
