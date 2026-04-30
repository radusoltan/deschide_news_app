/**
 * Plain-text helpers for article previews (cards, lead snippets, social sharing).
 *
 * The CMS stores `article.lead` and `article.content` as HTML, sometimes with
 * named entities (`&icirc;`, `&acirc;`) coming from upstream feeds. When that
 * raw string is rendered with `{value}` in JSX, React escapes it and the
 * reader sees `<p>` / `&icirc;` literally. These helpers strip tags and decode
 * entities so previews display as plain text on both server and client.
 */

const NAMED_ENTITIES: Record<string, string> = {
  amp: '&',
  lt: '<',
  gt: '>',
  quot: '"',
  apos: "'",
  nbsp: ' ',
  hellip: '…',
  ndash: '–',
  mdash: '—',
  laquo: '«',
  raquo: '»',
  bdquo: '„',
  ldquo: '“',
  rdquo: '”',
  lsquo: '‘',
  rsquo: '’',
  sbquo: '‚',
  copy: '©',
  reg: '®',
  trade: '™',
  deg: '°',
  middot: '·',
  bull: '•',
  Auml: 'Ä', auml: 'ä',
  Ouml: 'Ö', ouml: 'ö',
  Uuml: 'Ü', uuml: 'ü',
  szlig: 'ß',
  Acirc: 'Â', acirc: 'â',
  Ecirc: 'Ê', ecirc: 'ê',
  Icirc: 'Î', icirc: 'î',
  Ocirc: 'Ô', ocirc: 'ô',
  Ucirc: 'Û', ucirc: 'û',
  Abreve: 'Ă', abreve: 'ă',
  Scedil: 'Ş', scedil: 'ş',
  Tcedil: 'Ţ', tcedil: 'ţ',
  Scaron: 'Š', scaron: 'š',
  Ccedil: 'Ç', ccedil: 'ç',
  Ntilde: 'Ñ', ntilde: 'ñ',
};

function decodeEntities(input: string): string {
  if (!input || input.indexOf('&') === -1) return input;

  return input.replace(/&(#x[0-9a-fA-F]+|#\d+|[a-zA-Z][a-zA-Z0-9]+);/g, (match, body: string) => {
    if (body.charAt(0) === '#') {
      const code = body.charAt(1) === 'x' || body.charAt(1) === 'X'
        ? parseInt(body.slice(2), 16)
        : parseInt(body.slice(1), 10);
      if (!Number.isFinite(code) || code < 0 || code > 0x10FFFF) return match;
      try {
        return String.fromCodePoint(code);
      } catch {
        return match;
      }
    }
    const replacement = NAMED_ENTITIES[body];
    return replacement !== undefined ? replacement : match;
  });
}

/**
 * Convert an HTML fragment to plain text for previews.
 * Strips tags, decodes entities, collapses whitespace.
 */
export function stripHtml(html: string | null | undefined): string {
  if (!html) return '';
  const withoutTags = html.replace(/<[^>]*>/g, ' ');
  const decoded = decodeEntities(withoutTags);
  return decoded.replace(/\s+/g, ' ').trim();
}

/**
 * Plain-text excerpt for cards. Falls back to `content` when `lead` is empty.
 * Truncates at the last word boundary up to `maxLength` and appends an ellipsis.
 */
export function getPlainExcerpt(
  lead: string | null | undefined,
  content: string | null | undefined = null,
  maxLength: number = 200,
): string {
  const source = stripHtml(lead) || stripHtml(content);
  if (!source) return '';
  if (source.length <= maxLength) return source;

  const sliced = source.slice(0, maxLength);
  const lastSpace = sliced.lastIndexOf(' ');
  const cut = lastSpace > maxLength * 0.6 ? sliced.slice(0, lastSpace) : sliced;
  return cut.trimEnd() + '…';
}
