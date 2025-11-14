'use client';

import { useState } from 'react';
import { Facebook, Twitter, Linkedin, MessageCircle, Send, Link2, Check } from 'lucide-react';
import { getSharingUrl, copyToClipboard } from '@/lib/utils/socialMetadata';

interface SocialShareButtonsProps {
  url: string;
  title?: string;
  description?: string;
  showLabels?: boolean;
  size?: 'small' | 'medium' | 'large';
  variant?: 'icons' | 'buttons';
}

/**
 * SocialShareButtons component
 *
 * Features:
 * - Share to Facebook, Twitter, LinkedIn, WhatsApp, Telegram
 * - Copy link to clipboard
 * - Customizable size and variant
 * - Success feedback for copy action
 */
export function SocialShareButtons({
  url,
  title,
  description,
  showLabels = false,
  size = 'medium',
  variant = 'icons'
}: SocialShareButtonsProps) {
  const [copied, setCopied] = useState(false);

  const handleCopy = async () => {
    const success = await copyToClipboard(url);
    if (success) {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  const handleShare = (platform: 'facebook' | 'twitter' | 'linkedin' | 'whatsapp' | 'telegram') => {
    const shareUrl = getSharingUrl(platform, url, title, description);
    window.open(shareUrl, '_blank', 'noopener,noreferrer,width=600,height=400');
  };

  const getSizeClasses = () => {
    switch (size) {
      case 'small':
        return 'w-8 h-8 text-sm';
      case 'large':
        return 'w-12 h-12 text-lg';
      default:
        return 'w-10 h-10 text-base';
    }
  };

  const buttonBaseClasses = `${getSizeClasses()} flex items-center justify-center rounded-full transition-all duration-200 hover:scale-110`;

  const shareButtons = [
    {
      name: 'Facebook',
      icon: Facebook,
      onClick: () => handleShare('facebook'),
      bgColor: 'bg-blue-600 hover:bg-blue-700',
      label: 'Facebook'
    },
    {
      name: 'Twitter',
      icon: Twitter,
      onClick: () => handleShare('twitter'),
      bgColor: 'bg-sky-500 hover:bg-sky-600',
      label: 'Twitter'
    },
    {
      name: 'LinkedIn',
      icon: Linkedin,
      onClick: () => handleShare('linkedin'),
      bgColor: 'bg-blue-700 hover:bg-blue-800',
      label: 'LinkedIn'
    },
    {
      name: 'WhatsApp',
      icon: MessageCircle,
      onClick: () => handleShare('whatsapp'),
      bgColor: 'bg-green-600 hover:bg-green-700',
      label: 'WhatsApp'
    },
    {
      name: 'Telegram',
      icon: Send,
      onClick: () => handleShare('telegram'),
      bgColor: 'bg-blue-500 hover:bg-blue-600',
      label: 'Telegram'
    },
    {
      name: 'Copy Link',
      icon: copied ? Check : Link2,
      onClick: handleCopy,
      bgColor: copied ? 'bg-green-600' : 'bg-gray-700 hover:bg-gray-800',
      label: copied ? 'Copied!' : 'Copy Link'
    }
  ];

  if (variant === 'buttons') {
    return (
      <div className="flex flex-wrap gap-3">
        {shareButtons.map((button) => {
          const Icon = button.icon;
          return (
            <button
              key={button.name}
              onClick={button.onClick}
              className={`flex items-center gap-2 px-4 py-2 rounded-lg text-white transition-all duration-200 hover:scale-105 ${button.bgColor}`}
              aria-label={`Share on ${button.label}`}
            >
              <Icon className="w-5 h-5" />
              {showLabels && <span className="text-sm font-medium">{button.label}</span>}
            </button>
          );
        })}
      </div>
    );
  }

  // Default: icons variant
  return (
    <div className="flex items-center gap-2">
      {shareButtons.map((button) => {
        const Icon = button.icon;
        return (
          <button
            key={button.name}
            onClick={button.onClick}
            className={`${buttonBaseClasses} ${button.bgColor} text-white`}
            title={button.label}
            aria-label={`Share on ${button.label}`}
          >
            <Icon className="w-5 h-5" />
          </button>
        );
      })}
    </div>
  );
}
