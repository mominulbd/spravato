/*
 * Alternative to deploy/systemd/spravato-site@.service: the Next.js site
 * under pm2, for hosts that already use it. Cluster mode lets
 * `pm2 reload` replace the workers one at a time, so a release drops no
 * request without a second port. deploy.sh uses it with SITE_MANAGER=pm2:
 *
 *   pm2 startOrReload /var/www/spravato/current/deploy/pm2/ecosystem.config.cjs --update-env
 *
 * First time (as the spravato user): the command above, then
 *   pm2 save && pm2 startup systemd   # prints the root command to run once
 *
 * Runtime values come from the same shared/site.env the build reads.
 */
const { readFileSync } = require("node:fs");

const APP_DIR = process.env.SPRAVATO_APP_DIR || "/var/www/spravato";

/** KEY=value lines of shared/site.env; comments and blanks skipped. */
function readEnv(file) {
  const env = {};
  for (const line of readFileSync(file, "utf8").split("\n")) {
    const match = line.match(/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$/);
    if (!match) continue;
    env[match[1]] = match[2].replace(/^(["'])(.*)\1$/, "$2");
  }
  return env;
}

module.exports = {
  apps: [
    {
      name: "spravato-site",
      // Through `current`: every reload picks up the release it points to.
      cwd: `${APP_DIR}/current/site/.next/standalone/site`,
      script: "server.js",
      exec_mode: "cluster",
      instances: 2,
      max_memory_restart: "512M",
      kill_timeout: 20000,
      listen_timeout: 15000,
      env: {
        ...readEnv(`${APP_DIR}/shared/site.env`),
        NODE_ENV: "production",
        PORT: "3000",
        HOSTNAME: "127.0.0.1",
        NEXT_TELEMETRY_DISABLED: "1",
      },
    },
  ],
};
