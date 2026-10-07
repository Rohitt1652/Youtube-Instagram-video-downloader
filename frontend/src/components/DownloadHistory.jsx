import React from 'react';
import { X, Clock, Download, Trash2 } from 'lucide-react';
import { formatBytes } from '../utils/helpers';

export default function DownloadHistory({ isOpen, onClose, history = [], onClearHistory }) {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-end bg-slate-900/40 backdrop-blur-sm animate-in fade-in duration-200">
      <div className="w-full max-w-md h-full bg-white border-l border-slate-200 p-6 flex flex-col shadow-2xl animate-in slide-in-from-right duration-300">
        
        {/* Header */}
        <div className="flex items-center justify-between pb-5 border-b border-slate-100">
          <div>
            <div className="flex items-center gap-2">
              <h3 className="font-heading text-lg font-bold text-slate-900">Download History</h3>
              {history.length > 0 && (
                <span className="px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 text-[10px] font-bold border border-sky-200">
                  {history.length}
                </span>
              )}
            </div>
            <span className="text-xs text-slate-500">Stored locally on your browser</span>
          </div>
          <button
            onClick={onClose}
            className="p-2.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer"
            title="Close"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content List */}
        <div className="flex-1 overflow-y-auto py-4 space-y-3.5 pr-1">
          {history.length === 0 ? (
            <div className="h-72 flex flex-col items-center justify-center text-center text-slate-400">
              <div className="w-16 h-16 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-center mb-3">
                <Clock className="w-8 h-8 stroke-1 text-slate-400" />
              </div>
              <p className="font-heading font-semibold text-slate-700 text-sm">No recent downloads</p>
              <p className="text-xs text-slate-400 mt-1 max-w-[200px]">
                Processed videos and audio will be saved here for quick access
              </p>
            </div>
          ) : (
            history.map((item, idx) => (
              <div
                key={item.jobId || idx}
                className="p-4 rounded-2xl bg-white border border-slate-200 hover:border-sky-300 hover:bg-sky-50/30 transition duration-200 group shadow-2xs"
              >
                <div className="flex items-start justify-between gap-2.5 mb-2">
                  <h4 className="text-xs font-semibold text-slate-800 line-clamp-1 flex-1 font-heading group-hover:text-sky-600 transition-colors">
                    {item.title || 'Untitled Media'}
                  </h4>
                  <span className={`text-[10px] uppercase font-bold px-2 py-0.5 rounded-md border shrink-0 ${
                    item.platform === 'youtube'
                      ? 'bg-red-50 text-red-600 border-red-200'
                      : 'bg-pink-50 text-pink-600 border-pink-200'
                  }`}>
                    {item.platform || 'Media'}
                  </span>
                </div>

                <div className="flex items-center justify-between text-[11px] text-slate-500 pt-2.5 border-t border-slate-100">
                  <span className="font-mono bg-slate-100 px-2 py-0.5 rounded text-slate-700 text-[10px]">
                    {item.quality || 'Standard'}
                  </span>
                  {item.downloadUrl && (
                    <a
                      href={item.downloadUrl}
                      className="inline-flex items-center gap-1.5 text-sky-600 hover:text-sky-700 font-bold transition-colors cursor-pointer"
                    >
                      <Download className="w-3.5 h-3.5" />
                      <span>Re-download</span>
                    </a>
                  )}
                </div>
              </div>
            ))
          )}
        </div>

        {/* Footer Actions */}
        {history.length > 0 && (
          <div className="pt-4 border-t border-slate-100">
            <button
              onClick={onClearHistory}
              className="w-full py-3 rounded-xl bg-slate-50 hover:bg-red-50 hover:text-red-600 text-xs text-slate-600 border border-slate-200 hover:border-red-200 transition flex items-center justify-center gap-2 cursor-pointer font-semibold"
            >
              <Trash2 className="w-3.5 h-3.5" />
              <span>Clear All History</span>
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
