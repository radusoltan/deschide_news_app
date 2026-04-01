import { Golos_Text, Noto_Serif } from 'next/font/google';

export const golosText = Golos_Text({
  subsets: ['latin', 'cyrillic'],
  variable: '--font-golos',
  display: 'swap',
});

export const notoSerif = Noto_Serif({
  subsets: ['latin', 'cyrillic'],
  variable: '--font-noto-serif',
  display: 'swap',
});
