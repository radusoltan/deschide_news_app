'use client';

import { useEffect } from 'react';

export default function FlowbiteInit() {
  useEffect(() => {
    // Dynamically import Flowbite to initialize on client side
    import('flowbite').then((flowbite) => {
      if (typeof window !== 'undefined') {
        // Initialize Flowbite components
        flowbite.initFlowbite();
      }
    });
  }, []);

  return null;
}
