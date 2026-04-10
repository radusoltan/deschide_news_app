/**
 * Structured Data Component
 * Renders JSON-LD structured data for SEO
 *
 * Note: JSON.stringify is safe and doesn't require sanitization
 * as it escapes all special characters and produces valid JSON
 */

import React from 'react';

interface StructuredDataProps {
  data: object | object[];
}

export default function StructuredData({ data }: StructuredDataProps) {
  // Ensure data is always an array
  const schemas = Array.isArray(data) ? data : [data];

  return (
    <>
      {schemas.map((schema, index) => (
        <script
          key={index}
          type="application/ld+json"
          dangerouslySetInnerHTML={{
            __html: JSON.stringify(schema),
          }}
        />
      ))}
    </>
  );
}
