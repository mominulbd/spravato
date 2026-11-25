"use client";

import Image from "next/image";
import { useState, type ReactNode } from "react";

/**
 * A video behind its cover: nothing from the video host loads until the
 * visitor presses play. YouTube (nocookie) and Vimeo pages are embedded,
 * .mp4/.webm files play in a <video>, anything else opens in a new tab.
 */
export function VideoCard({ title, url, cover }: { title: string; url: string; cover: ReactNode }) {
  const [playing, setPlaying] = useState(false);
  const embed = embedUrl(url);
  const file = /\.(mp4|webm)(\?|$)/i.test(url);

  if (playing && embed) {
    return <iframe src={embed} title={title} allow="autoplay; encrypted-media; picture-in-picture; fullscreen" className="absolute inset-0 size-full border-0" />;
  }

  if (playing && file) {
    return <video src={url} controls autoPlay playsInline className="absolute inset-0 size-full bg-black object-contain" />;
  }

  const play = () => (embed || file ? setPlaying(true) : window.open(url, "_blank", "noopener,noreferrer"));

  return (
    <>
      {cover}
      <span aria-hidden="true" className="absolute inset-0 bg-navy opacity-22" />
      {url !== "" && (
        <button type="button" onClick={play} aria-label={`Play video: ${title}`} className="absolute top-1/2 left-1/2 size-[72px] -translate-x-1/2 -translate-y-1/2 rounded-full transition-transform hover:scale-105">
          <Image src="/icons/play.svg" alt="" width={72} height={72} />
        </button>
      )}
    </>
  );
}

function embedUrl(url: string): string | null {
  const youtube = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]{11})/);
  if (youtube) return `https://www.youtube-nocookie.com/embed/${youtube[1]}?autoplay=1&rel=0`;

  const vimeo = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
  if (vimeo) return `https://player.vimeo.com/video/${vimeo[1]}?autoplay=1&dnt=1`;

  return null;
}
