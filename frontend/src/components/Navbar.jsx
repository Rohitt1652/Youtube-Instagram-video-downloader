import React from 'react';
import { Download, Sparkles, History, ShieldCheck, Zap } from 'lucide-react';

export default function Navbar({ onOpenHistory, historyCount = 0 }) {
  return (
    <nav className="sticky top-0 z-40 w-full border-b border-sky-100/80 bg-white/80 backdrop-blur-xl shadow-xs">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-18 flex items-center justify-between">
        {/* Brand Logo */}
        <div
          className="flex items-center gap-3.5 group cursor-pointer"
          onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
        >
          <div className="relative">
            <div className="absolute -inset-1 rounded-2xl bg-gradient-to-r from-sky-400 via-sky-500 to-blue-500 opacity-60 blur-xs group-hover:opacity-100 transition duration-300"></div>
            <div className="relative h-11 w-11 rounded-xl bg-white border border-sky-200/80 flex items-center justify-center shadow-md">
              <Download className="w-5 h-5 text-sky-500 group-hover:text-sky-600 transition-colors group-hover:scale-110 duration-200" />
            </div>
          </div>
          <div>
            <div className="flex items-center gap-2">
              <span className="font-heading font-black text-2xl tracking-tight text-slate-900">
                Media<span className="bg-gradient-to-r from-sky-500 via-sky-600 to-blue-600 bg-clip-text text-transparent">Grab</span>
              </span>
              <span className="text-[10px] font-bold tracking-widest uppercase px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 border border-sky-200 shadow-2xs">
                PRO
              </span>
            </div>
            <p className="text-[10px] text-slate-500 font-medium tracking-wide -mt-0.5 hidden sm:block">
              Fast 4K & MP3 Extractor
            </p>
          </div>
        </div>

        {/* Action Controls */}
        <div className="flex items-center gap-3 sm:gap-4">
          {/* Status Badge */}
          <div className="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-sky-50/80 border border-sky-200/80 text-xs font-medium text-slate-600 shadow-2xs">
            <span className="relative flex h-2 w-2">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <span className="text-slate-500 text-[11px]">System:</span>
            <span className="text-emerald-600 font-bold text-[11px]">Online</span>
          </div>

          {/* History Button */}
          <button
            onClick={onOpenHistory}
            className="flex items-center gap-2 px-4 py-2 rounded-xl bg-white hover:bg-sky-50/80 border border-slate-200/80 hover:border-sky-300 text-sm font-semibold text-slate-700 hover:text-sky-700 transition-all relative shadow-xs cursor-pointer"
            title="Download History"
          >
            <History className="w-4 h-4 text-sky-500" />
            <span className="hidden sm:inline">History</span>
            {historyCount > 0 && (
              <span className="ml-0.5 px-2 py-0.5 text-[10px] font-bold rounded-full bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-xs">
                {historyCount}
              </span>
            )}
          </button>
        </div>
      </div>
    </nav>
  );
}
