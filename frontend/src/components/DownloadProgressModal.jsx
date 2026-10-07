import React, { useEffect, useState } from 'react';
import { Loader2, CheckCircle, AlertCircle, Download, X } from 'lucide-react';
import { getJobStatus } from '../api/client';
import { formatBytes } from '../utils/helpers';

export default function DownloadProgressModal({ jobId, format, title, onClose, onCompleted }) {
  const [jobData, setJobData] = useState({
    status: 'queued',
    progress: 5,
    download_url: null,
    file_size: null,
  });
  const [error, setError] = useState(null);
  const [autoCloseSeconds, setAutoCloseSeconds] = useState(null);
  const [downloadTriggered, setDownloadTriggered] = useState(false);
  const hasTriggeredRef = React.useRef(false);

  const triggerBrowserDownload = (url) => {
    try {
      const a = document.createElement('a');
      a.href = url;
      a.setAttribute('download', '');
      a.style.display = 'none';
      document.body.appendChild(a);
      a.click();
      setTimeout(() => {
        if (document.body.contains(a)) {
          document.body.removeChild(a);
        }
      }, 300);
    } catch {
      window.location.assign(url);
    }
  };

  useEffect(() => {
    if (!jobId) return;

    let isMounted = true;
    let pollInterval = null;

    const poll = async () => {
      try {
        const response = await getJobStatus(jobId);
        if (!isMounted) return;

        if (response.success && response.data) {
          const data = response.data;
          setJobData(data);

          if (data.status === 'completed') {
            clearInterval(pollInterval);
            if (onCompleted) {
              onCompleted(data);
            }

            // AUTO-TRIGGER DIRECT DOWNLOAD IMMEDIATELY!
            if (data.download_url && !hasTriggeredRef.current) {
              hasTriggeredRef.current = true;
              setDownloadTriggered(true);
              triggerBrowserDownload(data.download_url);
              setAutoCloseSeconds(4); // Start auto-close countdown
            }
          } else if (data.status === 'failed') {
            clearInterval(pollInterval);
            setError(data.error_message || 'Media processing encountered an error.');
          }
        } else if (!response.success && response.error) {
          clearInterval(pollInterval);
          setError(response.error.message || 'Failed to inspect job progress.');
        }
      } catch (err) {
        if (!isMounted) return;
        // Don't kill polling on a transient network glitch, unless 404/gone
        if (err.response?.status === 404 || err.response?.status === 410) {
          clearInterval(pollInterval);
          setError(err.response?.data?.error?.message || 'Download job expired or not found.');
        }
      }
    };

    // Initial poll immediately, then every 1.5 seconds
    poll();
    pollInterval = setInterval(poll, 1500);

    return () => {
      isMounted = false;
      if (pollInterval) clearInterval(pollInterval);
    };
  }, [jobId]);

  // Handle countdown to auto-close modal
  useEffect(() => {
    if (autoCloseSeconds === null) return;
    if (autoCloseSeconds <= 0) {
      onClose();
      return;
    }
    const timer = setTimeout(() => {
      setAutoCloseSeconds((prev) => (prev > 0 ? prev - 1 : 0));
    }, 1000);
    return () => clearTimeout(timer);
  }, [autoCloseSeconds, onClose]);

  const getStepText = (status, progress) => {
    if (status === 'completed') return 'Ready for Download';
    if (status === 'failed') return 'Processing Failed';
    if (progress < 15) return 'Preparing job & validating media...';
    if (progress < 85) return 'Downloading source media streams...';
    return 'Processing and merging video & audio...';
  };

  const handleTriggerDownload = () => {
    if (jobData.download_url) {
      window.location.href = jobData.download_url;
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm animate-in fade-in duration-200">
      <div className="w-full max-w-lg bg-white rounded-3xl p-6 sm:p-8 shadow-2xl border border-sky-200 relative">
        
        {/* Close Button */}
        <button
          onClick={onClose}
          className="absolute top-5 right-5 p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
          title="Close"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Header */}
        <div className="text-center mb-6">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-sky-50 border border-sky-200 text-sky-600 mb-4 shadow-sm">
            {jobData.status === 'completed' ? (
              <CheckCircle className="w-7 h-7 text-sky-600" />
            ) : error ? (
              <AlertCircle className="w-7 h-7 text-red-500" />
            ) : (
              <Loader2 className="w-7 h-7 animate-spin text-sky-500" />
            )}
          </div>

          <h3 className="font-heading text-xl font-bold text-slate-900 mb-1">
            {jobData.status === 'completed'
              ? 'Download Started! 🎉'
              : error
              ? 'Download Failed'
              : 'Processing Media'}
          </h3>

          <p className="text-xs text-slate-500 line-clamp-1 max-w-sm mx-auto">
            {jobData.status === 'completed'
              ? 'File is downloading directly to your device now.'
              : title || 'Media Item'}
          </p>
        </div>

        {/* Error State */}
        {error ? (
          <div className="space-y-4">
            <div className="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm text-left">
              <span className="font-semibold block mb-1">Processing Error:</span>
              <p className="text-xs text-red-600 leading-relaxed">{error}</p>
            </div>
            <button
              onClick={onClose}
              className="w-full py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold transition cursor-pointer"
            >
              Dismiss
            </button>
          </div>
        ) : (
          /* Normal Progress / Completed State */
          <div className="space-y-6">
            
            {/* Progress Bar & Status Text */}
            <div>
              <div className="flex items-center justify-between text-xs text-slate-600 mb-2">
                <span className="font-medium">{getStepText(jobData.status, jobData.progress)}</span>
                <span className="font-mono font-bold text-sky-600">{jobData.progress}%</span>
              </div>

              <div className="w-full h-3 rounded-full bg-slate-100 border border-slate-200 overflow-hidden p-0.5">
                <div
                  className={`h-full rounded-full transition-all duration-300 ease-out ${
                    jobData.status === 'completed'
                      ? 'bg-gradient-to-r from-sky-400 to-blue-600'
                      : 'bg-gradient-to-r from-sky-400 via-sky-500 to-blue-600'
                  }`}
                  style={{ width: `${Math.max(5, Math.min(100, jobData.progress))}%` }}
                />
              </div>
            </div>

            {/* Stepper info */}
            <div className="grid grid-cols-4 gap-2 text-center text-[10px]">
              <div className={`p-2 rounded-lg border font-medium ${jobData.progress >= 10 ? 'bg-sky-50 border-sky-200 text-sky-800 font-bold' : 'bg-slate-50 border-slate-200 text-slate-400'}`}>
                Preparing
              </div>
              <div className={`p-2 rounded-lg border font-medium ${jobData.progress >= 40 ? 'bg-sky-50 border-sky-200 text-sky-800 font-bold' : 'bg-slate-50 border-slate-200 text-slate-400'}`}>
                Downloading
              </div>
              <div className={`p-2 rounded-lg border font-medium ${jobData.progress >= 85 ? 'bg-sky-50 border-sky-200 text-sky-800 font-bold' : 'bg-slate-50 border-slate-200 text-slate-400'}`}>
                Merging
              </div>
              <div className={`p-2 rounded-lg border font-medium ${jobData.status === 'completed' ? 'bg-sky-100 border-sky-300 text-sky-900 font-bold' : 'bg-slate-50 border-slate-200 text-slate-400'}`}>
                Ready
              </div>
            </div>

            {/* Completed Actions - Auto Downloaded */}
            {jobData.status === 'completed' && (
              <div className="space-y-3 pt-2">
                <div className="p-3.5 rounded-xl bg-sky-50 border border-sky-200 flex items-center justify-between text-xs text-sky-900">
                  <div className="flex items-center gap-2">
                    <CheckCircle className="w-4 h-4 text-sky-600 shrink-0" />
                    <span>Download initiated directly! Check your browser downloads.</span>
                  </div>
                  {jobData.file_size && (
                    <span className="text-slate-500 shrink-0 font-medium">{formatBytes(jobData.file_size)}</span>
                  )}
                </div>

                <div className="flex items-center gap-3">
                  <button
                    onClick={onClose}
                    className="flex-1 py-3 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 text-white font-semibold text-xs shadow-md shadow-sky-500/20 transition cursor-pointer flex items-center justify-center gap-1.5"
                  >
                    <span>Done {autoCloseSeconds !== null ? `(${autoCloseSeconds}s)` : ''}</span>
                  </button>

                  <button
                    onClick={handleTriggerDownload}
                    className="px-4 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition cursor-pointer flex items-center justify-center gap-1.5"
                    title="Click here if download didn't start automatically"
                  >
                    <Download className="w-3.5 h-3.5" />
                    <span>Download Again</span>
                  </button>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
