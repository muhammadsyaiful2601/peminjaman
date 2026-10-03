'use strict';

const HTML_ENTITIES = {
  amp: '&',
  apos: "'",
  gt: '>',
  lt: '<',
  nbsp: ' ',
  quot: '"',
};

function decodeEntity(entity, name) {
  const normalized = name.toLowerCase();

  if (normalized.startsWith('#')) {
    const codePoint = normalized.startsWith('#x')
      ? Number.parseInt(normalized.slice(2), 16)
      : Number.parseInt(normalized.slice(1), 10);

    return Number.isInteger(codePoint) && codePoint >= 0 && codePoint <= 0x10ffff
      ? String.fromCodePoint(codePoint)
      : entity;
  }

  return HTML_ENTITIES[normalized] ?? entity;
}

function formatReleaseNotes(releaseNotes) {
  return String(releaseNotes ?? '')
    .replace(/<\s*(script|style)\b[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/gi, '')
    .replace(/<\s*br\s*\/?>/gi, '\n')
    .replace(/<\s*(?:h[1-6]|p|div|ul|ol|section|article)\b[^>]*>/gi, '\n')
    .replace(/<\s*li\b[^>]*>/gi, '\n- ')
    .replace(/<\s*\/\s*(?:li|h[1-6]|p|div|ul|ol|section|article)\s*>/gi, '\n')
    .replace(/<[^>]*>/g, '')
    .replace(/&(#x[\da-f]+|#\d+|amp|apos|gt|lt|nbsp|quot);/gi, decodeEntity)
    .replace(/^\s{0,3}#{1,6}\s+/gm, '')
    .replace(/\*\*(.*?)\*\*/g, '$1')
    .replace(/__(.*?)__/g, '$1')
    .replace(/`([^`]+)`/g, '$1')
    .replace(/^\s*[-*+]\s+/gm, '- ')
    .replace(/\r\n?/g, '\n')
    .replace(/[ \t]+\n/g, '\n')
    .replace(/\n[ \t]+/g, '\n')
    .replace(/[ \t]{2,}/g, ' ')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

module.exports = { formatReleaseNotes };
