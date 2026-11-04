# warehouse/ — SQL for the COSMAX PostgreSQL data warehouse (`cosmax_dwh`)

Not part of either app. Nothing here is deployed or called by trial/IPC; the apps
do not know the warehouse exists. The warehouse **pulls** from MySQL through
`mysql_fdw` (read-only user `fdw_readonly`). Files are run by hand (DBeaver) on the DWH.

## Flow
MySQL (live, apps) --mysql_fdw--> `fdw_trial` / `fdw_ipc` --INSERT..SELECT--> `raw` --> `staging` --> `mart` / `embeddings`

## Run order
0. Pre-flight (as the role you will use): `SELECT count(*) FROM fdw_ipc.master_lines;` must work.
   `CREATE TABLE ... AS SELECT` needs SELECT on the fdw schemas, and mysql_fdw needs a user mapping
   for that role. If it errors, use `dwh_admin` for step 1 or add `GRANT USAGE ON SCHEMA fdw_*` /
   `GRANT SELECT ON ALL TABLES IN SCHEMA fdw_*` and `CREATE USER MAPPING` for the role.
1. `sql/01_raw_ddl.sql` — creates 47 raw tables (DROPs and recreates them: only run on first setup or to rebuild).
2. `sql/02_raw_load.sql` — full refresh, one transaction per table. Reads live MySQL: run off-peak.
3. `sql/03_verify.sql` — source vs raw row counts, every `diff` must be 0.

## Regenerate
`php warehouse/tools/generate.php` (reads the LOCAL dev DBs, SELECT only on information_schema).
Edit the category arrays / `SENSITIVE_COLS` / `$redact` at the top of the script, never the generated SQL.
See `docs/table_mapping.md` for what is loaded, skipped, dropped, redacted.

## Defaults chosen (change in generator if wrong)
- Skipped: framework tables, `users` tables (both apps), `ai_*` output tables (trial), `master_imports`/`master_import_issues` (IPC).
- Dropped columns: password/token/secret columns, `ip_address`, `user_agent`.
- `activity_logs`/`audit_logs` `old_data`/`new_data` NULLed when they mention password/token/secret.
- No credentials anywhere in this folder.
