export const featureFlags = {
  aiChatEnabled: process.env.NEXT_PUBLIC_AI_CHAT_ENABLED === 'true',
  // Future flags here
} as const;

export function requireFeatureFlag(flag: keyof typeof featureFlags): boolean {
  return featureFlags[flag];
}
