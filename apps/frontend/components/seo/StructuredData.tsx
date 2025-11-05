/**
 * Structured Data Component
 * Renders JSON-LD structured data for SEO
 */

import React from 'react';

interface StructuredDataProps {
  data: any | any[];
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
