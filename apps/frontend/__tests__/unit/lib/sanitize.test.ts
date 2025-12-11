/**
 * Sanitize Utilities Tests
 * Tests for HTML sanitization and XSS prevention
 */

import { sanitizeHtml, createSafeHtml } from '@/lib/sanitize';

describe('Sanitize Utilities', () => {
  describe('sanitizeHtml', () => {
    it('should allow safe HTML tags', () => {
      const html = '<p>This is <strong>bold</strong> and <em>italic</em> text.</p>';
      const result = sanitizeHtml(html);

      expect(result).toBe(html);
    });

    it('should allow heading tags', () => {
      const html = '<h1>Title</h1><h2>Subtitle</h2><h3>Section</h3>';
      const result = sanitizeHtml(html);

      expect(result).toContain('<h1>Title</h1>');
      expect(result).toContain('<h2>Subtitle</h2>');
      expect(result).toContain('<h3>Section</h3>');
    });

    it('should allow lists', () => {
      const html = '<ul><li>Item 1</li><li>Item 2</li></ul>';
      const result = sanitizeHtml(html);

      expect(result).toContain('<ul>');
      expect(result).toContain('<li>Item 1</li>');
      expect(result).toContain('</ul>');
    });

    it('should allow links with safe attributes', () => {
      const html = '<a href="https://example.com" target="_blank" rel="noopener">Link</a>';
      const result = sanitizeHtml(html);

      expect(result).toContain('href="https://example.com"');
      expect(result).toContain('target="_blank"');
      expect(result).toContain('rel="noopener"');
    });

    it('should allow images with safe attributes', () => {
      const html = '<img src="https://example.com/image.jpg" alt="Test Image" width="800" height="600" />';
      const result = sanitizeHtml(html);

      expect(result).toContain('src="https://example.com/image.jpg"');
      expect(result).toContain('alt="Test Image"');
      expect(result).toContain('width="800"');
      expect(result).toContain('height="600"');
    });

    it('should allow blockquotes', () => {
      const html = '<blockquote>This is a quote</blockquote>';
      const result = sanitizeHtml(html);

      expect(result).toBe(html);
    });

    it('should allow code blocks', () => {
      const html = '<pre><code>const x = 10;</code></pre>';
      const result = sanitizeHtml(html);

      expect(result).toContain('<pre>');
      expect(result).toContain('<code>');
      expect(result).toContain('const x = 10;');
    });

    it('should allow tables', () => {
      const html = `
        <table>
          <thead>
            <tr><th>Header</th></tr>
          </thead>
          <tbody>
            <tr><td>Data</td></tr>
          </tbody>
        </table>
      `;
      const result = sanitizeHtml(html);

      expect(result).toContain('<table>');
      expect(result).toContain('<thead>');
      expect(result).toContain('<th>Header</th>');
      expect(result).toContain('<tbody>');
      expect(result).toContain('<td>Data</td>');
    });

    it('should allow iframes (for embeds)', () => {
      const html = '<iframe src="https://www.youtube.com/embed/VIDEO_ID" frameborder="0" allowfullscreen></iframe>';
      const result = sanitizeHtml(html);

      expect(result).toContain('<iframe');
      expect(result).toContain('src="https://www.youtube.com/embed/VIDEO_ID"');
    });

    it('should allow divs and spans with class', () => {
      const html = '<div class="container"><span class="highlight">Text</span></div>';
      const result = sanitizeHtml(html);

      expect(result).toContain('class="container"');
      expect(result).toContain('class="highlight"');
    });

    it('should allow data attributes', () => {
      const html = '<div data-id="123" data-type="article">Content</div>';
      const result = sanitizeHtml(html);

      expect(result).toContain('data-id="123"');
      expect(result).toContain('data-type="article"');
    });
  });

  describe('sanitizeHtml - XSS Prevention', () => {
    it('should remove script tags', () => {
      const html = '<p>Safe content</p><script>alert("XSS")</script>';
      const result = sanitizeHtml(html);

      expect(result).not.toContain('<script>');
      expect(result).not.toContain('alert("XSS")');
      expect(result).toContain('<p>Safe content</p>');
    });

    it('should remove inline event handlers (onclick)', () => {
      const html = '<button onclick="alert(\'XSS\')">Click me</button>';
      const result = sanitizeHtml(html);

      expect(result).not.toContain('onclick');
      expect(result).not.toContain("alert('XSS')");
    });

    it('should remove inline event handlers (onerror)', () => {
      const html = '<img src="invalid.jpg" onerror="alert(\'XSS\')" />';
      const result = sanitizeHtml(html);

      expect(result).not.toContain('onerror');
      expect(result).not.toContain("alert('XSS')");
    });

    it('should remove inline event handlers (onload)', () => {
      const html = '<body onload="alert(\'XSS\')">Content</body>';
      const result = sanitizeHtml(html);

      expect(result).not.toContain('onload');
      expect(result).not.toContain("alert('XSS')");
    });

    it('should remove inline event handlers (onmouseover)', () => {
      const html = '<div onmouseover="alert(\'XSS\')">Hover me</div>';
      const result = sanitizeHtml(html);

      expect(result).not.toContain('onmouseover');
      expect(result).not.toContain("alert('XSS')");
    });

    it('should remove style tags', () => {
      const html = '<p>Content</p><style>body { background: red; }</style>';
      const result = sanitizeHtml(html);

      expect(result).not.toContain('<style>');
      expect(result).not.toContain('background: red');
    });

    it('should remove javascript: protocol in links', () => {
      const html = '<a href="javascript:alert(\'XSS\')">Click</a>';
      const result = sanitizeHtml(html);

      expect(result).not.toContain('javascript:');
      expect(result).not.toContain("alert('XSS')");
    });

    it('should remove data: protocol with JavaScript', () => {
      const html = '<a href="data:text/html,<script>alert(\'XSS\')</script>">Click</a>';
      const result = sanitizeHtml(html);

      expect(result).not.toContain('data:text/html');
      expect(result).not.toContain('<script>');
    });

    it('should sanitize SVG with embedded scripts', () => {
      const html = '<svg onload="alert(\'XSS\')"><circle r="10" /></svg>';
      const result = sanitizeHtml(html);

      expect(result).not.toContain('onload');
      expect(result).not.toContain("alert('XSS')");
    });

    it('should handle nested script attempts', () => {
      const html = '<scr<script>ipt>alert("XSS")</scr</script>ipt>';
      const result = sanitizeHtml(html);

      // DOMPurify removes the script tags but may leave escaped text
      expect(result).not.toContain('<script>');
      // The string "alert" might remain as text, but the script execution is prevented
      expect(result.toLowerCase()).not.toContain('<script');
    });

    it('should handle encoded script tags', () => {
      const html = '&lt;script&gt;alert("XSS")&lt;/script&gt;';
      const result = sanitizeHtml(html);

      // DOMPurify keeps encoded entities as safe text (doesn't execute them)
      // The important thing is that <script> tags are not present in executable form
      expect(result).not.toContain('<script');
      // Result will be escaped text, which is safe
      expect(result).toContain('&lt;');
    });

    it('should remove dangerous attributes from allowed tags', () => {
      const html = '<div onclick="alert(\'XSS\')" class="safe">Content</div>';
      const result = sanitizeHtml(html);

      expect(result).toContain('class="safe"');
      expect(result).not.toContain('onclick');
    });
  });

  describe('sanitizeHtml - Edge Cases', () => {
    it('should handle empty string', () => {
      const result = sanitizeHtml('');

      expect(result).toBe('');
    });

    it('should handle null/undefined as empty string', () => {
      expect(sanitizeHtml(null as any)).toBe('');
      expect(sanitizeHtml(undefined as any)).toBe('');
    });

    it('should handle plain text without HTML', () => {
      const text = 'Just plain text with no HTML';
      const result = sanitizeHtml(text);

      expect(result).toBe(text);
    });

    it('should handle HTML entities', () => {
      const html = '<p>&lt;bold&gt; &amp; &quot;quotes&quot;</p>';
      const result = sanitizeHtml(html);

      expect(result).toContain('&lt;bold&gt;');
      expect(result).toContain('&amp;');
      // DOMPurify may normalize &quot; to actual quotes, both are safe
      expect(result).toMatch(/(&quot;|")quotes(&quot;|")/);
    });

    it('should handle mixed safe and unsafe content', () => {
      const html = `
        <p>Safe paragraph</p>
        <script>alert("XSS")</script>
        <strong>Bold text</strong>
        <img src="x" onerror="alert('XSS')" />
        <a href="https://safe.com">Safe link</a>
      `;
      const result = sanitizeHtml(html);

      expect(result).toContain('<p>Safe paragraph</p>');
      expect(result).toContain('<strong>Bold text</strong>');
      expect(result).toContain('href="https://safe.com"');
      expect(result).not.toContain('<script>');
      expect(result).not.toContain('onerror');
    });

    it('should handle malformed HTML', () => {
      const html = '<p>Unclosed paragraph<div>Div inside<p>Another paragraph';
      const result = sanitizeHtml(html);

      // DOMPurify will fix the structure
      expect(result).toBeTruthy();
      expect(result).not.toContain('<script>');
    });

    it('should handle very long content', () => {
      const longText = 'a'.repeat(10000);
      const html = `<p>${longText}</p>`;
      const result = sanitizeHtml(html);

      expect(result).toContain('<p>');
      expect(result).toContain('a'.repeat(10000));
      expect(result).toContain('</p>');
    });

    it('should handle Unicode characters', () => {
      const html = '<p>Unicode: 你好世界 🌍 Привет мир</p>';
      const result = sanitizeHtml(html);

      expect(result).toBe(html);
    });

    it('should handle nested tags', () => {
      const html = '<div><p><strong><em>Nested text</em></strong></p></div>';
      const result = sanitizeHtml(html);

      expect(result).toContain('<div>');
      expect(result).toContain('<p>');
      expect(result).toContain('<strong>');
      expect(result).toContain('<em>');
    });
  });

  describe('createSafeHtml', () => {
    it('should return object with __html property', () => {
      const html = '<p>Test content</p>';
      const result = createSafeHtml(html);

      expect(result).toHaveProperty('__html');
      expect(typeof result.__html).toBe('string');
    });

    it('should sanitize HTML in __html property', () => {
      const html = '<p>Safe</p><script>alert("XSS")</script>';
      const result = createSafeHtml(html);

      expect(result.__html).toContain('<p>Safe</p>');
      expect(result.__html).not.toContain('<script>');
    });

    it('should be usable with dangerouslySetInnerHTML', () => {
      const html = '<p>Safe content</p>';
      const safeHtml = createSafeHtml(html);

      // Simulate React usage
      const reactProp = { dangerouslySetInnerHTML: safeHtml };

      expect(reactProp.dangerouslySetInnerHTML).toHaveProperty('__html');
      expect(reactProp.dangerouslySetInnerHTML.__html).toBe('<p>Safe content</p>');
    });

    it('should handle empty string', () => {
      const result = createSafeHtml('');

      expect(result.__html).toBe('');
    });

    it('should sanitize XSS attempts', () => {
      const html = '<img src="x" onerror="alert(\'XSS\')" />';
      const result = createSafeHtml(html);

      expect(result.__html).not.toContain('onerror');
      expect(result.__html).not.toContain("alert('XSS')");
    });
  });

  describe('Real-world article content scenarios', () => {
    it('should handle typical news article HTML', () => {
      const html = `
        <h2>Article Headline</h2>
        <p class="lead">This is the lead paragraph with <strong>important</strong> information.</p>
        <p>First paragraph with a <a href="https://example.com" target="_blank">link</a>.</p>
        <blockquote>Quote from source</blockquote>
        <p>More content with <em>emphasis</em>.</p>
        <ul>
          <li>Point one</li>
          <li>Point two</li>
        </ul>
      `;
      const result = sanitizeHtml(html);

      expect(result).toContain('<h2>Article Headline</h2>');
      expect(result).toContain('class="lead"');
      expect(result).toContain('<strong>important</strong>');
      expect(result).toContain('href="https://example.com"');
      expect(result).toContain('<blockquote>');
      expect(result).toContain('<ul>');
    });

    it('should handle article with embedded YouTube video', () => {
      const html = `
        <p>Watch this video:</p>
        <iframe
          src="https://www.youtube.com/embed/dQw4w9WgXcQ"
          frameborder="0"
          allowfullscreen
          allow="accelerometer; autoplay; encrypted-media"
        ></iframe>
        <p>Video caption</p>
      `;
      const result = sanitizeHtml(html);

      expect(result).toContain('<iframe');
      expect(result).toContain('src="https://www.youtube.com/embed/dQw4w9WgXcQ"');
      expect(result).toContain('allowfullscreen');
    });

    it('should handle article with images and captions', () => {
      const html = `
        <figure>
          <img src="https://cdn.example.com/image.jpg" alt="News photo" width="800" height="600" />
          <figcaption>Photo caption goes here</figcaption>
        </figure>
      `;
      const result = sanitizeHtml(html);

      expect(result).toContain('<figure>');
      expect(result).toContain('<img');
      expect(result).toContain('src="https://cdn.example.com/image.jpg"');
      expect(result).toContain('<figcaption>');
    });

    it('should handle article with data tables', () => {
      const html = `
        <table>
          <thead>
            <tr>
              <th>Year</th>
              <th>Value</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>2024</td>
              <td>100</td>
            </tr>
          </tbody>
        </table>
      `;
      const result = sanitizeHtml(html);

      expect(result).toContain('<table>');
      expect(result).toContain('<thead>');
      expect(result).toContain('<th>Year</th>');
      expect(result).toContain('<tbody>');
      expect(result).toContain('<td>2024</td>');
    });

    it('should handle multilanguage content', () => {
      const html = `
        <p>Romanian: Știri importante despre <strong>politică</strong></p>
        <p>English: Important news about <strong>politics</strong></p>
        <p>Russian: Важные новости о <strong>политике</strong></p>
      `;
      const result = sanitizeHtml(html);

      expect(result).toContain('Știri importante');
      expect(result).toContain('Important news');
      expect(result).toContain('Важные новости');
    });
  });
});
