import Link from "next/link";

export default function NotFound() {
  return (
    <main id="main" className="mx-auto flex min-h-dvh max-w-[1200px] flex-col justify-center gap-4 px-6">
      <h1 className="font-serif text-4xl text-navy">Page not found</h1>
      <Link href="/" className="font-semibold text-royal hover:underline">Back to the Spravato page</Link>
    </main>
  );
}
