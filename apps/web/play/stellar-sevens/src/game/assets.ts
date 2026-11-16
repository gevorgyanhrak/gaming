/** A public-asset path of this game: its art lives under /play/stellar-sevens/ (public/play/stellar-sevens). */
export function asset(path: string): string {
  return '/play/stellar-sevens/' + path.replace(/^\//, '');
}

/** One symbol tile for the DOM screens (intro, game info). */
export function symbolArt(sym: string, cls = 'xc-sym'): string {
  return `<span class="${cls}"><img src="${asset(`assets/art/symbols/${sym}.webp`)}" alt="${sym}"></span>`;
}
