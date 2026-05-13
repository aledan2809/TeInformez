'use client';

import { ExternalLink } from 'lucide-react';
import type { AffiliateInfo } from '@/types';

interface Props {
  affiliate: AffiliateInfo;
}

export default function AffiliateWidget({ affiliate }: Props) {
  return (
    <div className="mt-8 border border-gray-200 dark:border-gray-700 rounded-lg p-5 bg-gray-50 dark:bg-gray-900">
      <p className="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">
        Partener comercial
      </p>
      <div className="flex items-center gap-4">
        {affiliate.logo_url && (
          <img
            src={affiliate.logo_url}
            alt={affiliate.provider_name}
            width={64}
            height={32}
            className="h-8 w-auto object-contain flex-shrink-0"
          />
        )}
        <div className="flex-1 min-w-0">
          <p className="text-sm font-semibold text-gray-900 dark:text-white truncate">
            {affiliate.provider_name}
          </p>
        </div>
        <a
          href={affiliate.url}
          target="_blank"
          rel="noopener noreferrer sponsored"
          className="inline-flex items-center gap-1.5 px-4 py-2 rounded-md text-sm font-semibold bg-primary-600 hover:bg-primary-700 text-white transition-colors flex-shrink-0"
        >
          {affiliate.cta_label}
          <ExternalLink className="h-3.5 w-3.5" />
        </a>
      </div>
    </div>
  );
}
