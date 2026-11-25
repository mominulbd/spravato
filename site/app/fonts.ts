import { Nunito, Nunito_Sans, Source_Serif_4 } from "next/font/google";

/*
 * The Figma families, self-hosted by next/font so patients' browsers never
 * contact Google. The Spravato page uses no italics, so only the upright cut
 * of Source Serif 4 is loaded.
 */
export const sourceSerif = Source_Serif_4({ subsets: ["latin"], axes: ["opsz"], variable: "--font-source-serif", display: "swap" });
export const nunito = Nunito({ subsets: ["latin"], variable: "--font-nunito", display: "swap" });
export const nunitoSans = Nunito_Sans({ subsets: ["latin"], variable: "--font-nunito-sans", display: "swap" });

export const fontVariables = `${sourceSerif.variable} ${nunito.variable} ${nunitoSans.variable}`;
