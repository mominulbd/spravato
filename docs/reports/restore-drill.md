# Backups and restore drill

Phase 4 · Security (docs/DEVELOPMENT-PLAN.md). Scripts: `scripts/backup.sh`, `scripts/restore.sh`. No extra dependencies: `mysqldump`, `mysql`, `tar`, `gzip`, `sha256sum`/`shasum`, and `gpg` when backups are encrypted.

## What a backup holds

`scripts/backup.sh` writes one directory per run, `spravato-<UTC timestamp>/`, into `BACKUP_DIR` (default `storage/app/backups`, git-ignored and never web-served):

| File | Contents |
| --- | --- |
| `database.sql.gz` | `mysqldump --single-transaction --quick --routines --triggers --hex-blob` of `DB_DATABASE`. A consistent InnoDB snapshot without locking the site. No `CREATE DATABASE`/`USE`, so it restores under any name |
| `storage-public.tar.gz` | `storage/app/public`: the media library and its variants |
| `ROWCOUNTS` | rows per table at dump time; `restore.sh` compares them after loading |
| `MANIFEST` | source database, host, git commit, table count |
| `SHA256SUMS` | checked by `restore.sh` before it changes anything |

- It runs on a directory named `.<name>.partial` and renames it only when everything has been written, so a failed run never looks like a backup (the drill hit this once by accident: a gpg failure exited non-zero and left nothing behind).
- It keeps the newest `BACKUP_KEEP` backups (default 14) and deletes older ones.
- `umask 077`: files are `0600`. The database password goes in a temporary `0600` option file, never on the command line.
- With `BACKUP_GPG_RECIPIENT=<key id>` the dump and the archive are encrypted to that public key (`*.gpg`) and the plain copies are removed. `restore.sh` decrypts with the private key in the operator's keyring. **Use it for every copy that leaves the server**: the dump holds health information.
- Settings come from the environment first, then the app's `.env` (read, never sourced): `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `BACKUP_DIR`, `BACKUP_KEEP`, `STORAGE_DIR`, `BACKUP_GPG_RECIPIENT`.

### APP_KEY is not in the backup and must be kept separately

Consultation requests (`name`, `phone`, `email`, `message`, `insurance`) and notes are encrypted with Laravel's `encrypted` cast (AES-256-CBC + HMAC with `APP_KEY`). The scripts never copy `.env`, so **a backup is useless without the production `APP_KEY`**, and anyone holding both can read every request. So:

1. Store `APP_KEY` in the clinic's password manager / secrets store, owned by two named people, **never** beside the backups or in the same bucket.
2. Never rotate `APP_KEY` without re-encrypting the data first: older backups then need the old key too, so keep every key that was ever live, labelled with its dates.
3. Write down where the key lives in the launch checklist. The drill below shows that a restore with any other key decrypts nothing.

## Schedule and off-site copy (production)

```cron
# as the deploy user, after the nightly queue lull
15 2 * * *  cd /var/www/spravato/current && BACKUP_GPG_RECIPIENT=<ops key id> scripts/backup.sh >> /var/log/spravato-backup.log 2>&1
```

Copy `storage/app/backups/` off the server after each run (rsync/rclone to the host's object storage or another machine) and keep 30 days there. Local copies alone do not protect against losing the server. Repeat the drill below every quarter and after any schema-heavy release, and record each run at the end of this file.

## Restore

```sh
# 1. Load into a NEW database (refuses a non-empty one unless --force).
scripts/restore.sh storage/app/backups/spravato-20261126T021500Z \
  --database spravato_restored --storage-dir /var/www/spravato/shared/storage-restored

# 2. Point Laravel at it: DB_DATABASE=spravato_restored, with the original APP_KEY.
# 3. Swap the media: move storage/app/public aside, move storage-restored into place.
# 4. php artisan migrate --force (if the backup predates the release), then
#    php artisan optimize:clear && php artisan queue:restart, then reload PHP-FPM
#    and restart the Next.js service so its cache refills from the restored data.
```

`restore.sh` checks the checksums, creates the database (utf8mb4_unicode_ci), loads the dump, compares every table's row count with `ROWCOUNTS`, and unpacks the media when `--storage-dir` is given. Restoring straight over the live database is possible with `--force` but loses whatever was written since the backup. Prefer a new database and a config switch.

## Drill — 2026-11-26

Run on the development Mac (MySQL 9.3.0 Homebrew). Throwaway databases only; the dev database `spravato` was not touched.

**Source.** `spravato_backup_drill_src`, built with `php artisan migrate:fresh --seed` under a freshly generated drill `APP_KEY`: the full site content plus 11 demo consultation requests (encrypted). 40 tables. Non-empty ones: business_hours 7, consultation_requests 11, content_items 8, faqs 33, insurance_partners 10, media 27, media_folders 6, media_usages 20, media_variants 116, menu_items 22, menus 6, migrations 21, page_blocks 22, pages 4, permissions 15, revisions 8, role_permission 40, roles 4, seo_meta 4, team_members 3, testimonials 4, users 4. The media part used a 27-file sample of `storage/app/public/media` (1.8 MB): the dev copy is 9.1 GB of test uploads, and the archive step is the same `tar` either way.

An earlier pass dumped `spravato_e2e` (40 tables, 12 requests; taken while holding the e2e lock so no Playwright run could reset it mid-dump). That restore was byte-identical too, but those rows had been written under an older e2e `APP_KEY`, so they could not show the key check. That is why the drill uses its own seeded source.

**Evidence** (paths shortened to `<scratch>`):

```text
$ scripts/backup.sh            # DB_DATABASE=spravato_backup_drill_src STORAGE_DIR=<sample>
backup: dumping spravato_backup_drill_src@127.0.0.1:3306
backup: archiving <scratch>/drill/storage-sample
backup: wrote <scratch>/drill/backups2/spravato-20261126T050330Z (1.8M)

$ cat SHA256SUMS
39e4df74d679ebe1ec9ad0c6e27c0b06f079b939400d7445756be5b0ced08d7f  ./database.sql.gz
b9cfa3f7091feb4afe230893429b2f44d9d4994b99157c5ef72bcd961cd22ea7  ./MANIFEST
c351e9b5679c77bd748321d953c7d313366566377d04cdf4f311bd83a72d8ac6  ./ROWCOUNTS
c72c7e136df787f4bc333eacec2f25da74f62ab955a3f152fb51257711c01eed  ./storage-public.tar.gz

$ zcat database.sql.gz | grep -c "CREATE DATABASE\|^USE "
0

$ time scripts/restore.sh <backup> --database spravato_restore_drill --storage-dir <scratch>/drill/restored2
restore: verifying checksums in <backup>
restore: loading database.sql.gz into spravato_restore_drill
restore: database loaded in 1s
restore: row counts match for all 40 tables
restore: media unpacked into <scratch>/drill/restored2 (27 files)
real 1.50s

$ diff -r storage-sample restored2
media identical (27 files)

$ CHECKSUM TABLE (all 40 tables), hashed — source vs restored
spravato_backup_drill_src: b5439a72908e74d1
spravato_restore_drill:    b5439a72908e74d1

# Laravel against the restored database, reading name/phone/email/message of every request
$ APP_KEY=<drill key> DB_DATABASE=spravato_restore_drill php artisan tinker …
requests decrypted=11 failed=0
$ APP_KEY=<any other key> DB_DATABASE=spravato_restore_drill php artisan tinker …
requests decrypted=0 failed=11          # DecryptException: The MAC is invalid.
```

Safety checks, same day:

```text
$ scripts/restore.sh <backup> --database spravato_restore_drill          # second time, no --force
restore: spravato_restore_drill already has 40 tables; pass --force to replace them   (exit 1)

$ echo x >> tampered/database.sql.gz; scripts/restore.sh tampered --database spravato_restore_tamper
./database.sql.gz: FAILED                                                          (exit 1; no database created)

$ for i in 1 2 3; do BACKUP_KEEP=2 scripts/backup.sh; done
backup: removing old …/spravato-20261126T050149Z                                   (two newest kept)

$ BACKUP_GPG_RECIPIENT=drill@backup.invalid scripts/backup.sh   # throwaway key in a temp GNUPGHOME
→ database.sql.gz.gpg  MANIFEST  ROWCOUNTS  SHA256SUMS  storage-public.tar.gz.gpg
$ scripts/restore.sh <encrypted backup> --database spravato_restore_drill --force
restore: row counts match for all 40 tables
```

**Result: pass.** Backup to restored and readable in under 2 s for this size. The drill databases (`spravato_backup_drill_src`, `spravato_restore_drill`) and the temporary GPG home were deleted afterwards.

**Recovery objectives** (proposed for the launch checklist): RPO 24 h (nightly backup); RTO 1 h (restore + config switch + cache warm-up). Tighten the RPO with MySQL binlogs or a twice-daily run if the clinic wants it.
