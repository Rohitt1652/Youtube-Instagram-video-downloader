import React, { useState } from 'react';
import { Download, Film, Music, Clock, User, CheckCircle2, Zap } from 'lucide-react';
import { YoutubeIcon, InstagramIcon } from './Icons';
import { formatDuration, formatBytes } from '../utils/helpers';

export default function MediaPreview({ media, onDownload, isDownloading }) {
  if (!media) return null;

  const videoFormats = (media.formats || []).filter((f) => f.type === 'video');
  const audioFormats = (media.formats || []).filter((f) => f.type === 'audio');

  // Select first available format by default (prefer 720p or 1080p if available)
  const defaultFormat = videoFormats.find((f) => f.quality?.includes('720p')) || videoFormats[0] || audioFormats[0];
  const [selectedFormatId, setSelectedFormatId] = useState(defaultFormat?.id || '');

  const selectedFormat = (media.formats || []).find((f) => f.id === selectedFormatId);

  const handleDownloadClick = () => {
    if (selectedFormatId) {
      onDownload(selectedFormatId, selectedFormat);
    }
  };

  const getQualityBadge = (quality, type) => {
    if (type === 'audio') return 'HQ Audio';
    if (!quality) return null;
    if (quality.includes('2160p') || quality.includes('4K')) return '4K Ultra';
    if (quality.includes('1440p') || quality.includes('2K')) return '2K QHD';
    if (quality.includes('1080p')) return '1080p FHD';
    if (quality.includes('720p')) return '720p HD';
    return null;
  };

  return (
    <div className="w-full max-w-4xl mx-auto px-4 py-6 animate-in fade-in slide-in-from-bottom-5 duration-300">
      <div className="bg-white rounded-3xl p-6 sm:p-8 shadow-xl shadow-sky-500/10 border border-sky-100 relative overflow-hidden">
        
        {/* Subtle decorative sky-blue aura inside card */}
        <div className="absolute top-0 right-0 w-80 h-80 bg-sky-100/50 rounded-full blur-3xl pointer-events-none"></div>

        {/* Top Header & Details Grid */}
        <div className="grid grid-cols-1 md:grid-cols-12 gap-6 lg:gap-8 items-start pb-8 border-b border-slate-100 relative z-10">
          
          {/* Thumbnail Preview */}
          <div className="md:col-span-5 relative group overflow-hidden rounded-2xl bg-slate-100 border border-slate-200 aspect-video flex items-center justify-center shadow-md">
            {media.thumbnail ? (
              <img
                src={media.thumbnail}
                alt={media.title}
                className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                onError={(e) => {
                  e.target.style.display = 'none';
                }}
              />
            ) : (
              <div className="flex flex-col items-center justify-center text-slate-400">
                <Film className="w-12 h-12 mb-2 stroke-1 text-slate-300" />
                <span className="text-xs">No preview thumbnail</span>
              </div>
            )}

            {/* Duration Tag */}
            {media.duration !== null && (
              <div className="absolute bottom-3 right-3 px-2.5 py-1 rounded-lg bg-slate-900/85 backdrop-blur-md text-white text-xs font-mono font-bold flex items-center gap-1.5 shadow-md border border-white/10">
                <Clock className="w-3.5 h-3.5 text-sky-400" />
                <span>{formatDuration(media.duration)}</span>
              </div>
            )}

            {/* Platform Badge Overlay */}
            <div className="absolute top-3 left-3">
              {media.platform === 'youtube' ? (
                <div className="px-3 py-1.5 rounded-xl bg-red-600 text-white text-xs font-bold flex items-center gap-1.5 shadow-md">
                  <YoutubeIcon className="w-4 h-4 text-white" />
                  <span>YouTube</span>
                </div>
              ) : (
                <div className="px-3 py-1.5 rounded-xl bg-gradient-to-r from-pink-600 to-purple-600 text-white text-xs font-bold flex items-center gap-1.5 shadow-md">
                  <InstagramIcon className="w-4 h-4 text-white" />
                  <span>Instagram</span>
                </div>
              )}
            </div>
          </div>

          {/* Metadata Information */}
          <div className="md:col-span-7 flex flex-col justify-between h-full">
            <div>
              <div className="flex flex-wrap items-center gap-2 mb-3">
                <span className="text-[11px] font-bold uppercase tracking-wider text-emerald-800 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200 flex items-center gap-1.5 shadow-2xs">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                  Verified Media Stream
                </span>

                {media.author && (
                  <span className="text-xs text-slate-700 font-semibold bg-slate-100 px-2.5 py-1 rounded-full border border-slate-200 flex items-center gap-1.5">
                    <User className="w-3.5 h-3.5 text-sky-500" />
                    <span className="line-clamp-1 max-w-[200px]">{media.author}</span>
                  </span>
                )}
              </div>

              <h2 className="font-heading text-xl sm:text-2xl font-bold text-slate-900 leading-snug line-clamp-3 mb-3">
                {media.title || 'Untitled Media'}
              </h2>
            </div>

            {/* Stats Chips */}
            <div className="mt-4 pt-4 border-t border-slate-100 flex flex-wrap gap-4 text-xs">
              <div className="bg-slate-50 px-3 py-2 rounded-xl border border-slate-200/80 shadow-2xs">
                <span className="text-slate-500 block text-[10px] uppercase font-bold tracking-wider">Source</span>
                <span className="font-bold text-slate-800 capitalize">{media.platform}</span>
              </div>
              {media.duration !== null && (
                <div className="bg-slate-50 px-3 py-2 rounded-xl border border-slate-200/80 shadow-2xs">
                  <span className="text-slate-500 block text-[10px] uppercase font-bold tracking-wider">Length</span>
                  <span className="font-bold text-slate-800">{formatDuration(media.duration)}</span>
                </div>
              )}
              <div className="bg-sky-50 px-3 py-2 rounded-xl border border-sky-200 shadow-2xs">
                <span className="text-sky-700 block text-[10px] uppercase font-bold tracking-wider">Available Formats</span>
                <span className="font-bold text-sky-900">{media.formats?.length || 0} Stream Options</span>
              </div>
            </div>
          </div>
        </div>

        {/* Format Selection Section */}
        <div className="pt-6 relative z-10">
          <div className="flex items-center justify-between mb-4">
            <div>
              <h3 className="font-heading text-base font-bold text-slate-900">
                Choose Quality & Format
              </h3>
              <p className="text-xs text-slate-500">
                Select your preferred stream to begin direct instant download
              </p>
            </div>
            <span className="hidden sm:inline-flex items-center gap-1 text-[11px] font-bold text-sky-700 bg-sky-50 px-2.5 py-1 rounded-lg border border-sky-200 shadow-2xs">
              <Zap className="w-3 h-3 text-sky-500" />
              Direct Auto-Save
            </span>
          </div>

          {/* Video Options */}
          {videoFormats.length > 0 && (
            <div className="mb-6">
              <div className="flex items-center gap-2 text-xs font-bold text-slate-600 mb-3 tracking-wide uppercase">
                <Film className="w-4 h-4 text-sky-500" />
                <span>Video Streams (MP4)</span>
              </div>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                {videoFormats.map((fmt) => {
                  const isSelected = selectedFormatId === fmt.id;
                  const qualityBadge = getQualityBadge(fmt.quality, 'video');

                  return (
                    <button
                      key={fmt.id}
                      type="button"
                      onClick={() => setSelectedFormatId(fmt.id)}
                      className={`group p-3.5 rounded-2xl border text-left transition-all duration-200 cursor-pointer flex flex-col justify-between relative overflow-hidden ${
                        isSelected
                          ? 'bg-sky-50/90 border-sky-500 text-sky-950 shadow-md shadow-sky-500/10 ring-2 ring-sky-400'
                          : 'bg-white border-slate-200 text-slate-700 hover:border-sky-300 hover:bg-sky-50/40 hover:scale-[1.02] shadow-2xs'
                      }`}
                    >
                      {qualityBadge && (
                        <span className={`text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-md self-start mb-2 ${
                          isSelected
                            ? 'bg-sky-500 text-white'
                            : 'bg-slate-100 text-slate-600 border border-slate-200'
                        }`}>
                          {qualityBadge}
                        </span>
                      )}

                      <div className="flex items-center justify-between w-full mb-1.5">
                        <span className="font-heading font-extrabold text-base text-slate-900">
                          {fmt.quality || 'Standard'}
                        </span>
                        {isSelected ? (
                          <CheckCircle2 className="w-4 h-4 text-sky-600 shrink-0" />
                        ) : (
                          <Download className="w-3.5 h-3.5 text-slate-400 group-hover:text-sky-600 transition-colors shrink-0" />
                        )}
                      </div>

                      <div className="flex items-center justify-between text-[11px] text-slate-500 font-medium">
                        <span className="uppercase font-mono text-slate-600">{fmt.extension || 'mp4'}</span>
                        {fmt.filesize && (
                          <span className="text-slate-500">{formatBytes(fmt.filesize)}</span>
                        )}
                      </div>
                    </button>
                  );
                })}
              </div>
            </div>
          )}

          {/* Audio Options */}
          {audioFormats.length > 0 && (
            <div className="mb-6">
              <div className="flex items-center gap-2 text-xs font-bold text-slate-600 mb-3 tracking-wide uppercase">
                <Music className="w-4 h-4 text-sky-600" />
                <span>Audio Only (MP3 & M4A)</span>
              </div>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                {audioFormats.map((fmt) => {
                  const isSelected = selectedFormatId === fmt.id;

                  return (
                    <button
                      key={fmt.id}
                      type="button"
                      onClick={() => setSelectedFormatId(fmt.id)}
                      className={`group p-3.5 rounded-2xl border text-left transition-all duration-200 cursor-pointer flex flex-col justify-between relative overflow-hidden ${
                        isSelected
                          ? 'bg-sky-50/90 border-sky-500 text-sky-950 shadow-md shadow-sky-500/10 ring-2 ring-sky-400'
                          : 'bg-white border-slate-200 text-slate-700 hover:border-sky-300 hover:bg-sky-50/40 hover:scale-[1.02] shadow-2xs'
                      }`}
                    >
                      <span className={`text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-md self-start mb-2 ${
                        isSelected ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-600 border border-slate-200'
                      }`}>
                        Studio Audio
                      </span>

                      <div className="flex items-center justify-between w-full mb-1.5">
                        <span className="font-heading font-extrabold text-base text-slate-900">
                          {fmt.quality || 'Audio'}
                        </span>
                        {isSelected ? (
                          <CheckCircle2 className="w-4 h-4 text-sky-600 shrink-0" />
                        ) : (
                          <Download className="w-3.5 h-3.5 text-slate-400 group-hover:text-sky-600 transition-colors shrink-0" />
                        )}
                      </div>

                      <div className="flex items-center justify-between text-[11px] text-slate-500 font-medium">
                        <span className="uppercase font-mono text-sky-700">{fmt.extension || 'mp3'}</span>
                        <span className="text-slate-500">High Bitrate</span>
                      </div>
                    </button>
                  );
                })}
              </div>
            </div>
          )}

          {/* Download Action Footer */}
          <div className="pt-5 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div className="text-xs text-slate-600 text-center sm:text-left flex items-center gap-2">
              <span className="text-slate-500">Selected Format:</span>
              <span className="font-bold text-sky-900 bg-sky-50 px-2.5 py-1 rounded-lg border border-sky-200">
                {selectedFormat?.quality || 'Default'} ({selectedFormat?.extension?.toUpperCase() || 'MP4'})
              </span>
            </div>

            <button
              type="button"
              onClick={handleDownloadClick}
              disabled={isDownloading || !selectedFormatId}
              className="w-full sm:w-auto px-9 py-4 rounded-2xl bg-gradient-to-r from-sky-500 via-sky-600 to-blue-600 hover:from-sky-600 hover:via-sky-700 hover:to-blue-700 text-white font-extrabold text-base shadow-lg shadow-sky-500/25 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2.5 cursor-pointer shimmer-effect hover:scale-[1.02] active:scale-[0.98]"
            >
              <Zap className="w-5 h-5 fill-white text-white" />
              <span>Download {selectedFormat?.quality || ''}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
