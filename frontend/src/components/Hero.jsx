import React from 'react';
import { Search, Loader2, Clipboard, X, AlertCircle, ArrowRight } from 'lucide-react';
import { YoutubeIcon, InstagramIcon } from './Icons';
import { detectPlatform } from '../utils/helpers';

export default function Hero({ url, setUrl, onSubmit, isLoading, error }) {
  const detected = detectPlatform(url);

  const handlePaste = async () => {
    try {
      const text = await navigator.clipboard.readText();
      if (text) {
        setUrl(text.trim());
      }
    } catch {
      // Clipboard read may be blocked by browser permission
    }
  };

  const handleClear = () => {
    setUrl('');
  };

  return (
    <div className="w-full text-center py-10 sm:py-16 max-w-4xl mx-auto px-4 relative z-10">
      {/* Top Floating Badge */}
      <div className="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-sky-50 border border-sky-200 shadow-sm shadow-sky-500/5 mb-6 group cursor-default">
        <span className="flex h-2 w-2 relative">
          <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>
          <span className="relative inline-flex rounded-full h-2 w-2 bg-sky-500"></span>
        </span>
        <span className="text-xs font-bold text-sky-800">
          Direct High-Speed Media Processing
        </span>
        <span className="text-[10px] uppercase font-extrabold px-1.5 py-0.5 rounded bg-sky-500 text-white shadow-2xs">
          Fast
        </span>
      </div>

      {/* Main Hero Headline */}
      <h1 className="font-heading text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-black tracking-tight text-slate-900 mb-4 sm:mb-6 leading-[1.1]">
        Download{' '}
        <span className="bg-gradient-to-r from-sky-500 via-sky-600 to-blue-600 bg-clip-text text-transparent">
          Your Media
        </span>
      </h1>

      {/* Subheading */}
      <p className="text-base sm:text-lg md:text-xl text-slate-600 max-w-2xl mx-auto mb-8 sm:mb-12 leading-relaxed font-normal">
        Download media you own or have permission to save.
        Clean server-side extraction for lossless video quality and studio audio.
      </p>

      {/* Interactive URL Input Form */}
      <form onSubmit={onSubmit} className="max-w-3xl mx-auto relative group">
        <div className="gradient-border-glow rounded-3xl transition-all duration-300">
          <div className="bg-white rounded-3xl p-2.5 sm:p-3 flex flex-col sm:flex-row items-center gap-2.5 shadow-xl shadow-sky-500/10 border border-sky-100 transition-all">
            
            {/* Input Platform Indicator */}
            <div className="w-full sm:w-auto flex items-center justify-between sm:justify-start px-3 py-1 text-slate-400">
              {detected === 'youtube' ? (
                <div className="flex items-center gap-2 px-3 py-1.5 rounded-xl badge-youtube text-xs font-bold animate-in fade-in duration-200 shadow-2xs">
                  <YoutubeIcon className="w-4 h-4 text-red-500" />
                  <span>YouTube</span>
                </div>
              ) : detected === 'instagram' ? (
                <div className="flex items-center gap-2 px-3 py-1.5 rounded-xl badge-instagram text-xs font-bold animate-in fade-in duration-200 shadow-2xs">
                  <InstagramIcon className="w-4 h-4 text-pink-500" />
                  <span>Instagram</span>
                </div>
              ) : (
                <div className="flex items-center gap-2 text-slate-400">
                  <Search className="w-5 h-5 text-sky-500" />
                  <span className="text-xs text-slate-500 sm:hidden">Enter URL:</span>
                </div>
              )}
            </div>

            {/* Input field */}
            <input
              type="url"
              value={url}
              onChange={(e) => setUrl(e.target.value)}
              placeholder="Paste a YouTube or Instagram URL..."
              disabled={isLoading}
              required
              aria-label="Media URL"
              className="w-full bg-transparent text-slate-900 placeholder-slate-400 text-base sm:text-lg px-3 py-2.5 focus:outline-none focus:ring-0 disabled:opacity-50 font-medium"
            />

            {/* Quick Actions (Paste / Clear) */}
            <div className="flex items-center gap-1.5 px-2">
              {url ? (
                <button
                  type="button"
                  onClick={handleClear}
                  className="p-2.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                  title="Clear input"
                >
                  <X className="w-4 h-4" />
                </button>
              ) : (
                <button
                  type="button"
                  onClick={handlePaste}
                  className="hidden sm:flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-sky-700 bg-sky-50/80 hover:bg-sky-100 border border-sky-200 transition-all cursor-pointer shadow-2xs"
                  title="Paste from clipboard"
                >
                  <Clipboard className="w-3.5 h-3.5 text-sky-500" />
                  <span>Paste</span>
                </button>
              )}
            </div>

            {/* Submit Button */}
            <button
              type="submit"
              disabled={isLoading || !url.trim()}
              className="w-full sm:w-auto px-8 py-4 rounded-2xl bg-gradient-to-r from-sky-500 via-sky-600 to-blue-600 hover:from-sky-600 hover:to-blue-700 text-white font-bold text-base shadow-lg shadow-sky-500/25 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 whitespace-nowrap cursor-pointer shimmer-effect hover:scale-[1.02] active:scale-[0.98]"
            >
              {isLoading ? (
                <>
                  <Loader2 className="w-5 h-5 animate-spin" />
                  <span>Fetching...</span>
                </>
              ) : (
                <>
                  <span>Fetch Media</span>
                  <ArrowRight className="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
                </>
              )}
            </button>
          </div>
        </div>

        {/* Validation / API Error Banner */}
        {error && (
          <div className="mt-4 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-start gap-3.5 text-left animate-in fade-in slide-in-from-top-3 shadow-md">
            <AlertCircle className="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
            <div className="flex-1">
              <span className="font-bold text-red-800">{error.code ? `[${error.code}] ` : ''}</span>
              <span className="text-red-700">{error.message || 'An error occurred.'}</span>
            </div>
          </div>
        )}
      </form>

      {/* Supported Platforms Indicators */}
      <div className="mt-10 sm:mt-12 flex flex-wrap items-center justify-center gap-3 sm:gap-6 text-xs text-slate-500">
        <span className="font-bold text-slate-400 tracking-wider text-[11px] uppercase">
          Supported:
        </span>

        <div className="flex items-center gap-2.5 px-3.5 py-2 rounded-xl bg-white border border-slate-200/80 text-slate-700 hover:border-sky-300 transition-colors shadow-2xs">
          <YoutubeIcon className="w-4 h-4 text-red-500" />
          <span className="font-medium">YouTube</span>
          <span className="text-[10px] text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded font-mono">4K • Shorts</span>
        </div>

        <div className="flex items-center gap-2.5 px-3.5 py-2 rounded-xl bg-white border border-slate-200/80 text-slate-700 hover:border-sky-300 transition-colors shadow-2xs">
          <InstagramIcon className="w-4 h-4 text-pink-500" />
          <span className="font-medium">Instagram</span>
          <span className="text-[10px] text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded font-mono">Reels • Posts</span>
        </div>
      </div>
    </div>
  );
}
