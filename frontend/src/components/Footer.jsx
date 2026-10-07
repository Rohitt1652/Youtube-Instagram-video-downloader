import React from 'react';
import { Shield } from 'lucide-react';

export default function Footer() {
  return (
    <footer className="w-full border-t border-slate-200/80 bg-white py-12 mt-20 relative z-10 shadow-xs">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8 pb-8 border-b border-slate-100">
          
          {/* Brand Info */}
          <div>
            <div className="flex items-center gap-2 mb-3">
              <span className="font-heading font-extrabold text-xl text-slate-900">
                Media<span className="text-sky-600">Grab</span>
              </span>
            </div>
            <p className="text-xs text-slate-500 leading-relaxed">
              High-performance media extractor built for creators and engineers to process authorized YouTube and Instagram media.
            </p>
          </div>

          {/* Legal and Compliance */}
          <div className="md:col-span-2">
            <div className="flex items-center gap-2 text-xs font-bold text-amber-700 mb-2">
              <Shield className="w-4 h-4 text-amber-500" />
              <span>Authorized Content & Security Compliance</span>
            </div>
            <p className="text-xs text-slate-500 leading-relaxed">
              MediaGrab is strictly for processing media you own or have explicit permission to download. The application contains rigorous SSRF, input sanitization, and access-control protections. We do not bypass DRM, paywalls, or private-account walls.
            </p>
          </div>
        </div>

        {/* Bottom Credits */}
        <div className="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
          <div>
            © {new Date().getFullYear()} MediaGrab. All rights reserved.
          </div>
          <div className="flex items-center gap-4 text-slate-500 font-medium">
            <span>Laravel 12 API</span>
            <span>•</span>
            <span>React + Vite</span>
            <span>•</span>
            <span>FFmpeg 9.0</span>
            <span>•</span>
            <span>Tailwind CSS</span>
          </div>
        </div>
      </div>
    </footer>
  );
}
