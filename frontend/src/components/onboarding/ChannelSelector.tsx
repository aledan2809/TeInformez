'use client';

import { Mail, Facebook, Twitter, Instagram, CheckCircle2, Circle } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Channel {
  id: string;
  label: string;
  description: string;
  icon: React.ReactNode;
  enabled: boolean;
}

interface ChannelSelectorProps {
  selectedChannels: string[];
  onToggleChannel: (channelId: string) => void;
}

const CHANNELS: Channel[] = [
  {
    id: 'email',
    label: 'Email',
    description: 'Primești știri direct în inbox',
    icon: <Mail className="h-8 w-8" />,
    enabled: true,
  },
  {
    id: 'facebook',
    label: 'Facebook',
    description: 'Postări publice pe pagina ta',
    icon: <Facebook className="h-8 w-8" />,
    enabled: true,
  },
  {
    id: 'twitter',
    label: 'Twitter/X',
    description: 'Tweet-uri automate cu știri',
    icon: <Twitter className="h-8 w-8" />,
    enabled: true,
  },
  {
    id: 'instagram',
    label: 'Instagram',
    description: 'Coming soon - în curând disponibil',
    icon: <Instagram className="h-8 w-8" />,
    enabled: false,
  },
];

export default function ChannelSelector({ selectedChannels, onToggleChannel }: ChannelSelectorProps) {
  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold mb-2">Unde vrei să primești știrile?</h2>
        <p className="text-gray-600">
          Alege canalele pe care vrei să fie livrate știrile tale personalizate
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {CHANNELS.map((channel) => {
          const isSelected = selectedChannels.includes(channel.id);
          const isDisabled = !channel.enabled;

          return (
            <button
              key={channel.id}
              onClick={() => !isDisabled && onToggleChannel(channel.id)}
              disabled={isDisabled}
              className={cn(
                'relative p-6 rounded-lg border-2 transition-all text-left',
                isDisabled
                  ? 'opacity-50 cursor-not-allowed bg-gray-50 border-gray-200'
                  : isSelected
                  ? 'border-primary-600 bg-primary-50 hover:shadow-md'
                  : 'border-gray-200 bg-white hover:border-gray-300 hover:shadow-md'
              )}
            >
              {/* Checkbox indicator */}
              <div className="absolute top-4 right-4">
                {isSelected ? (
                  <CheckCircle2 className="h-6 w-6 text-primary-600" />
                ) : (
                  <Circle className="h-6 w-6 text-gray-300" />
                )}
              </div>

              {/* Icon */}
              <div className={cn('mb-3', isSelected ? 'text-primary-600' : 'text-gray-400')}>
                {channel.icon}
              </div>

              {/* Label */}
              <h3 className="text-lg font-semibold mb-1">{channel.label}</h3>

              {/* Description */}
              <p className="text-sm text-gray-500">{channel.description}</p>

              {/* Coming soon badge */}
              {!channel.enabled && (
                <div className="mt-3">
                  <span className="inline-block px-2 py-1 text-xs font-medium bg-gray-200 text-gray-700 rounded">
                    În curând
                  </span>
                </div>
              )}
            </button>
          );
        })}
      </div>

      {/* Selection summary */}
      {selectedChannels.length > 0 && (
        <div className="bg-primary-50 border border-primary-200 rounded-lg p-4">
          <p className="text-sm font-medium text-primary-900">
            📬 Vei primi știri pe:{' '}
            <strong>
              {selectedChannels
                .map((id) => CHANNELS.find((c) => c.id === id)?.label)
                .filter(Boolean)
                .join(', ')}
            </strong>
          </p>
        </div>
      )}

      {selectedChannels.length === 0 && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <p className="text-sm text-yellow-800">
            ⚠️ Selectează cel puțin un canal pentru a primi știri
          </p>
        </div>
      )}
    </div>
  );
}
