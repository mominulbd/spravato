"use client";

import { useEffect, useRef } from "react";

interface TurnstileApi {
  render: (element: HTMLElement, options: Record<string, unknown>) => string;
  remove: (widgetId: string) => void;
}

declare global {
  interface Window {
    turnstile?: TurnstileApi;
  }
}

const SCRIPT_URL = "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
let loading: Promise<void> | null = null;

/**
 * Cloudflare Turnstile, loaded only once the consultation dialog first
 * opens, so the page itself makes no third-party request. Laravel verifies
 * the token; when the key is not set the widget is not rendered at all.
 */
export function Turnstile({ siteKey, onToken }: { siteKey: string; onToken: (token: string | null) => void }) {
  const container = useRef<HTMLDivElement>(null);
  const callback = useRef(onToken);

  useEffect(() => {
    callback.current = onToken;
  }, [onToken]);

  useEffect(() => {
    let widget: string | null = null;
    let cancelled = false;

    load()
      .then(() => {
        if (cancelled || !container.current || !window.turnstile) return;

        widget = window.turnstile.render(container.current, {
          sitekey: siteKey,
          action: "consultation",
          appearance: "interaction-only",
          callback: (token: string) => callback.current(token),
          "expired-callback": () => callback.current(null),
          "error-callback": () => callback.current(null),
        });
      })
      .catch(() => {
        // Laravel lets requests through while Cloudflare is unreachable.
      });

    return () => {
      cancelled = true;
      if (widget !== null) window.turnstile?.remove(widget);
    };
  }, [siteKey]);

  return <div ref={container} />;
}

function load(): Promise<void> {
  if (window.turnstile) return Promise.resolve();

  loading ??= new Promise<void>((resolve, reject) => {
    const script = document.createElement("script");
    script.src = SCRIPT_URL;
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => {
      loading = null;
      reject(new Error("Turnstile failed to load."));
    };
    document.head.appendChild(script);
  });

  return loading;
}
