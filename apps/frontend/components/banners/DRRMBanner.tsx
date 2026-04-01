'use client';

import Image from 'next/image';
import Link from 'next/link';

interface DRRMBannerProps {
  href?: string;
  className?: string;
}

export default function DRRMBanner({
  href = 'https://drrm.gov.ro/w/',
  className = '',
}: DRRMBannerProps) {
  const content = (
    <div
      className={`w-full overflow-hidden border border-[#2b3d5b]/30 ${className}`}
      style={{ fontFamily: "'Segoe UI', 'Helvetica Neue', Arial, sans-serif" }}
    >
      {/* ── Top Row: Seal+Title | Project Description ── */}
      <div className="flex flex-col sm:flex-row items-stretch min-h-[70px]">
        {/* Left: Official DRRM Logo */}
        <div className="flex items-center bg-surface px-3 py-2.5 sm:px-4 sm:py-3">
          <Image
            src="/images/partners/logo-drrm.png"
            alt="Departamentul pentru Relația cu Republica Moldova - Guvernul României"
            width={811}
            height={211}
            className="h-16 sm:h-[76px] md:h-[88px] w-auto"
            priority={false}
          />
        </div>

        {/* Right: Project Description - muted steel-blue background */}
        <div className="flex-1 bg-[#b3c5d4] flex items-center px-4 py-2.5 sm:px-5 sm:py-3 border-l border-[#8fa3b8]/40">
          <p
            className="text-[#1b2a4a] leading-[1.35]"
            style={{
              fontSize: 'clamp(11px, 1.2vw, 15px)',
              fontWeight: 500,
            }}
          >
            Proiectul &quot;De la cooperare, la integrare: România și Republica
            Moldova, împreună în Uniunea Europeană&quot; este finanțat de
            Departamentul pentru Relația cu Republica Moldova
          </p>
        </div>
      </div>

      {/* ── Bottom Disclaimer Strip ── */}
      <div className="bg-[#1b2a4a] flex flex-col sm:flex-row items-center justify-between gap-0.5 sm:gap-2 px-3 sm:px-4 py-1.5 sm:py-2">
        <p className="text-white/85 leading-snug text-center sm:text-left" style={{ fontSize: 'clamp(9px, 1vw, 12px)' }}>
          Conținutul acestui site nu reprezintă poziția oficială a Departamentului
          pentru Relația cu Republica Moldova
        </p>
        <span className="text-white font-bold whitespace-nowrap" style={{ fontSize: 'clamp(11px, 1.1vw, 14px)' }}>
          www.drrm.gov.ro
        </span>
      </div>
    </div>
  );

  if (href) {
    return (
      <Link
        href={href}
        target="_blank"
        rel="noopener noreferrer sponsored"
        className="block hover:opacity-95 transition-opacity"
        aria-label="Departamentul pentru Relația cu Republica Moldova - Vizitați site-ul oficial"
      >
        {content}
      </Link>
    );
  }

  return content;
}
