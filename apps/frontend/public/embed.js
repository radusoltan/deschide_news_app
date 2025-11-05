/**
 * Deschide LiveText Embed SDK
 *
 * Simple JavaScript SDK for embedding LiveText on external sites
 *
 * Usage:
 * <div id="deschide-livetext-123"></div>
 * <script src="https://deschide.local/embed.js"></script>
 * <script>
 *   DeschideLiveText.embed({
 *     containerId: "deschide-livetext-123",
 *     liveTextId: 123,
 *     locale: "ro",
 *     theme: "light",
 *     width: "100%",
 *     height: "600px"
 *   });
 * </script>
 */

(function (window) {
  'use strict';

  const BASE_URL = 'http://localhost:3005'; // Will be replaced in production

  /**
   * DeschideLiveText SDK
   */
  const DeschideLiveText = {
    /**
     * Embed a LiveText in a container
     *
     * @param {Object} options - Embedding options
     * @param {string} options.containerId - Container element ID
     * @param {number} options.liveTextId - LiveText ID
     * @param {string} [options.slug] - LiveText slug (alternative to liveTextId)
     * @param {string} [options.locale='ro'] - Locale (ro, en, ru)
     * @param {string} [options.theme='light'] - Theme (light or dark)
     * @param {string} [options.width='100%'] - Width (CSS value)
     * @param {string} [options.height='600px'] - Height (CSS value)
     * @param {boolean} [options.autoResize=true] - Auto-resize iframe to content
     * @param {Function} [options.onLoad] - Callback when loaded
     * @param {Function} [options.onError] - Callback on error
     */
    embed: function (options) {
      // Validate options
      if (!options.containerId) {
        console.error('DeschideLiveText: containerId is required');
        return;
      }

      if (!options.liveTextId && !options.slug) {
        console.error('DeschideLiveText: liveTextId or slug is required');
        return;
      }

      // Get container
      const container = document.getElementById(options.containerId);
      if (!container) {
        console.error('DeschideLiveText: Container not found: ' + options.containerId);
        return;
      }

      // Default options
      const config = {
        locale: options.locale || 'ro',
        theme: options.theme || 'light',
        width: options.width || '100%',
        height: options.height || '600px',
        autoResize: options.autoResize !== false,
        onLoad: options.onLoad || function () {},
        onError: options.onError || function () {},
      };

      // Build embed URL
      let embedUrl;
      if (options.slug) {
        embedUrl = `${BASE_URL}/${config.locale}/embed/live/${options.slug}?theme=${config.theme}`;
      } else {
        // Need to fetch slug first
        this._fetchSlug(options.liveTextId, config.locale)
          .then((slug) => {
            embedUrl = `${BASE_URL}/${config.locale}/embed/live/${slug}?theme=${config.theme}`;
            this._createIframe(container, embedUrl, config);
          })
          .catch((error) => {
            console.error('DeschideLiveText: Failed to fetch slug', error);
            config.onError(error);
          });
        return;
      }

      // Create iframe
      this._createIframe(container, embedUrl, config);
    },

    /**
     * Fetch slug by LiveText ID
     * @private
     */
    _fetchSlug: function (liveTextId, locale) {
      return fetch(`${BASE_URL}/api/embed/live-text/${liveTextId}?locale=${locale}`)
        .then((response) => {
          if (!response.ok) {
            throw new Error('Failed to fetch LiveText');
          }
          return response.json();
        })
        .then((data) => data.slug);
    },

    /**
     * Create and insert iframe
     * @private
     */
    _createIframe: function (container, embedUrl, config) {
      // Clear container
      container.innerHTML = '';

      // Create iframe
      const iframe = document.createElement('iframe');
      iframe.src = embedUrl;
      iframe.width = config.width;
      iframe.height = config.height;
      iframe.frameBorder = '0';
      iframe.scrolling = 'auto';
      iframe.style.width = config.width;
      iframe.style.height = config.height;
      iframe.style.border = 'none';
      iframe.style.display = 'block';
      iframe.setAttribute('allowfullscreen', '');

      // Handle load event
      iframe.onload = function () {
        config.onLoad(iframe);
      };

      // Handle error
      iframe.onerror = function (error) {
        console.error('DeschideLiveText: Failed to load iframe', error);
        config.onError(error);
      };

      // Append to container
      container.appendChild(iframe);

      // Auto-resize (experimental)
      if (config.autoResize) {
        this._setupAutoResize(iframe);
      }
    },

    /**
     * Setup auto-resize for iframe
     * @private
     */
    _setupAutoResize: function (iframe) {
      // Listen for messages from iframe
      window.addEventListener('message', function (event) {
        // Verify origin
        if (event.origin !== BASE_URL) {
          return;
        }

        // Handle resize message
        if (event.data && event.data.type === 'resize') {
          if (event.data.height) {
            iframe.style.height = event.data.height + 'px';
          }
        }
      });
    },

    /**
     * Get embed code for a LiveText
     *
     * @param {Object} options - Embedding options
     * @returns {Promise<Object>} Embed code object
     */
    getEmbedCode: function (options) {
      if (!options.liveTextId && !options.slug) {
        return Promise.reject(new Error('liveTextId or slug is required'));
      }

      const locale = options.locale || 'ro';
      const theme = options.theme || 'light';
      const width = options.width || '100%';
      const height = options.height || '600px';

      const url = options.liveTextId
        ? `${BASE_URL}/api/embed/code/${options.liveTextId}?locale=${locale}&theme=${theme}&width=${width}&height=${height}`
        : `${BASE_URL}/api/embed/code/slug/${options.slug}?locale=${locale}&theme=${theme}&width=${width}&height=${height}`;

      return fetch(url)
        .then((response) => {
          if (!response.ok) {
            throw new Error('Failed to fetch embed code');
          }
          return response.json();
        });
    },

    /**
     * Get list of embeddable LiveTexts
     *
     * @param {Object} [options] - Filter options
     * @param {string} [options.status] - Filter by status (live, ended, etc.)
     * @param {number} [options.limit=20] - Limit results
     * @returns {Promise<Array>} List of LiveTexts
     */
    list: function (options) {
      options = options || {};
      const status = options.status || '';
      const limit = options.limit || 20;

      const url = `${BASE_URL}/api/embed/list?status=${status}&limit=${limit}`;

      return fetch(url)
        .then((response) => {
          if (!response.ok) {
            throw new Error('Failed to fetch list');
          }
          return response.json();
        })
        .then((data) => data.liveTexts);
    },

    /**
     * Get SDK version
     */
    version: '1.0.0',
  };

  // Export to global scope
  window.DeschideLiveText = DeschideLiveText;

  // Auto-embed if data attributes are present
  document.addEventListener('DOMContentLoaded', function () {
    const embedElements = document.querySelectorAll('[data-deschide-livetext]');

    embedElements.forEach(function (element) {
      const liveTextId = element.getAttribute('data-deschide-livetext');
      const slug = element.getAttribute('data-slug');
      const locale = element.getAttribute('data-locale') || 'ro';
      const theme = element.getAttribute('data-theme') || 'light';
      const width = element.getAttribute('data-width') || '100%';
      const height = element.getAttribute('data-height') || '600px';

      // Generate unique ID if not present
      if (!element.id) {
        element.id = 'deschide-livetext-' + (liveTextId || slug);
      }

      DeschideLiveText.embed({
        containerId: element.id,
        liveTextId: liveTextId ? parseInt(liveTextId) : null,
        slug: slug,
        locale: locale,
        theme: theme,
        width: width,
        height: height,
      });
    });
  });
})(window);
