// `output: "standalone"` leaves out the static assets and public/; the
// standalone server expects them beside server.js (deploy/README.md).
import { cpSync, existsSync } from "node:fs";
import { join } from "node:path";

const dist = process.env.NEXT_DIST_DIR ?? ".next";
const app = join(dist, "standalone", "site");

if (!existsSync(join(app, "server.js"))) {
  process.exit(0); // not a standalone build (e.g. a custom dist dir without output)
}

cpSync(join(dist, "static"), join(app, dist, "static"), { recursive: true });
cpSync("public", join(app, "public"), { recursive: true });
console.log(`Copied static assets and public/ into ${app}`);
