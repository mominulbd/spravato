"use client";

import Image from "next/image";
import { useEffect, useRef, useState, type ReactNode } from "react";

/**
 * A video behind its cover: nothing from the video host loads (no iframe, no
 * preconnect, no thumbnail) until the visitor presses play. YouTube
 * (nocookie) and Vimeo (dnt) pages are embedded, .mp4/.webm files play in a
 * <video>, any other https address opens in a new tab. Pressing play moves
 * focus into the player so keyboard users can control it at once.
 */
export function VideoCard({ title, url, cover }: { title: string; url: string; cover: ReactNode }) {
  const [playing, setPlaying] = useState(false);
  const player = useRef<HTMLIFrameElement & HTMLVideoElement>(null);
  const kind = videoKind(url);

  useEffect(() => {
    if (playing) player.current?.focus();
  }, [playing]);

  if (playing && kind?.type === "embed") {
    return (
      <iframe
        ref={player}
        src={kind.src}
        title={`Video: ${title}`}
        loading="eager"
        referrerPolicy="strict-origin-when-cross-origin"
        allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
        allowFullScreen
        className="absolute inset-0 size-full border-0"
      />
    );
  }

  if (playing && kind?.type === "file") {
    return <video ref={player} src={kind.src} aria-label={`Video: ${title}`} controls autoPlay playsInline preload="auto" className="absolute inset-0 size-full bg-black object-contain" />;
  }

  // Over a photo a single-colour ring can vanish: white ring on a navy halo.
  const control = "absolute top-1/2 left-1/2 size-[72px] -translate-x-1/2 -translate-y-1/2 rounded-full transition-transform hover:scale-105 motion-reduce:transition-none motion-reduce:hover:scale-100 focus-visible:outline-white focus-visible:outline-offset-4 focus-visible:shadow-[0_0_0_8px_var(--color-navy)]";
  const icon = <Image src="/icons/play.svg" alt="" width={72} height={72} />;

  return (
    <>
      {cover}
      <span aria-hidden="true" className="absolute inset-0 bg-navy opacity-22" />
      {kind?.type === "link" && (
        <a href={kind.src} target="_blank" rel="noopener noreferrer" aria-label={`Watch video: ${title} (opens in a new tab)`} className={control}>
          {icon}
        </a>
      )}
      {(kind?.type === "embed" || kind?.type === "file") && (
        <button type="button" onClick={() => setPlaying(true)} aria-label={`Play video: ${title}`} className={control}>
          {icon}
        </button>
      )}
    </>
  );
}

type VideoKind = { type: "embed" | "file" | "link"; src: string };

/** What the CMS address plays as; null (cover only) when empty or not a web address. */
function videoKind(url: string): VideoKind | null {
  if (url === "") return null;

  const youtube = url.match(/^https:\/\/(?:www\.|m\.)?(?:youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{11})/);
  if (youtube) return { type: "embed", src: `https://www.youtube-nocookie.com/embed/${youtube[1]}?autoplay=1&rel=0` };

  const vimeo = url.match(/^https:\/\/(?:www\.|player\.)?vimeo\.com\/(?:video\/)?(\d+)/);
  if (vimeo) return { type: "embed", src: `https://player.vimeo.com/video/${vimeo[1]}?autoplay=1&dnt=1` };

  // Files may be served by Laravel (/storage/…) or an https host.
  const web = url.startsWith("/") && !url.startsWith("//") ? true : /^https:\/\//i.test(url);
  if (!web) return null;

  return { type: /\.(mp4|webm)(\?|#|$)/i.test(url) ? "file" : "link", src: url };
}
