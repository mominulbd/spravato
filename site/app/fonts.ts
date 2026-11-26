import { Nunito, Nunito_Sans, Source_Serif_4 } from "next/font/google";

/*
 * The Figma families, self-hosted by next/font so patients' browsers never
 * contact Google. The Spravato page uses no italics, so only the upright cut
 * of Source Serif 4 is loaded.
 *
 * All three families are above the fold (the hero's eyebrow, headline and
 * lead), so each latin subset is preloaded; nothing else is. `swap` shows
 * text at once in a fallback that next/font metric-matches to the web font
 * (adjustFontFallback: size-adjust and ascent/descent overrides on a local
 * Times New Roman / Arial face), so the swap does not move the layout.
 */
// next/font reads these options at build time: they must be literals, not a shared object.
export const sourceSerif = Source_Serif_4({
  subsets: ["latin"],
  axes: ["opsz"],
  display: "swap",
  preload: true,
  adjustFontFallback: true,
  fallback: ["Georgia", "serif"],
  variable: "--font-source-serif",
});
export const nunito = Nunito({ subsets: ["latin"], display: "swap", preload: true, adjustFontFallback: true, fallback: ["system-ui", "sans-serif"], variable: "--font-nunito" });
export const nunitoSans = Nunito_Sans({
  subsets: ["latin"],
  display: "swap",
  preload: true,
  adjustFontFallback: true,
  fallback: ["system-ui", "sans-serif"],
  variable: "--font-nunito-sans",
});

export const fontVariables = `${sourceSerif.variable} ${nunito.variable} ${nunitoSans.variable}`;
