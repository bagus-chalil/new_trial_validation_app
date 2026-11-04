-- GENERATED. Row-count check: source (fdw) vs raw.

-- Run each statement separately (Ctrl+Enter on each).
-- 1) Summary: expect tables_checked = 47 and tables_mismatch = 0.
SELECT count(*) AS tables_checked, count(*) FILTER (WHERE src <> raw_rows) AS tables_mismatch
FROM (
SELECT 'raw.trial__activity_logs' AS tbl, (SELECT count(*) FROM fdw_trial."activity_logs") AS src, (SELECT count(*) FROM raw.trial__activity_logs) AS raw_rows
UNION ALL
SELECT 'raw.trial__audit_logs' AS tbl, (SELECT count(*) FROM fdw_trial."audit_logs") AS src, (SELECT count(*) FROM raw.trial__audit_logs) AS raw_rows
UNION ALL
SELECT 'raw.trial__line_configuration_lanes' AS tbl, (SELECT count(*) FROM fdw_trial."line_configuration_lanes") AS src, (SELECT count(*) FROM raw.trial__line_configuration_lanes) AS raw_rows
UNION ALL
SELECT 'raw.trial__master_options' AS tbl, (SELECT count(*) FROM fdw_trial."master_options") AS src, (SELECT count(*) FROM raw.trial__master_options) AS raw_rows
UNION ALL
SELECT 'raw.trial__notification_user_status' AS tbl, (SELECT count(*) FROM fdw_trial."notification_user_status") AS src, (SELECT count(*) FROM raw.trial__notification_user_status) AS raw_rows
UNION ALL
SELECT 'raw.trial__notifications' AS tbl, (SELECT count(*) FROM fdw_trial."notifications") AS src, (SELECT count(*) FROM raw.trial__notifications) AS raw_rows
UNION ALL
SELECT 'raw.trial__products' AS tbl, (SELECT count(*) FROM fdw_trial."products") AS src, (SELECT count(*) FROM raw.trial__products) AS raw_rows
UNION ALL
SELECT 'raw.trial__trial_additional_attachments' AS tbl, (SELECT count(*) FROM fdw_trial."trial_additional_attachments") AS src, (SELECT count(*) FROM raw.trial__trial_additional_attachments) AS raw_rows
UNION ALL
SELECT 'raw.trial__trial_attachment_files' AS tbl, (SELECT count(*) FROM fdw_trial."trial_attachment_files") AS src, (SELECT count(*) FROM raw.trial__trial_attachment_files) AS raw_rows
UNION ALL
SELECT 'raw.trial__trial_edit_permissions' AS tbl, (SELECT count(*) FROM fdw_trial."trial_edit_permissions") AS src, (SELECT count(*) FROM raw.trial__trial_edit_permissions) AS raw_rows
UNION ALL
SELECT 'raw.trial__trial_line_configuration_reports' AS tbl, (SELECT count(*) FROM fdw_trial."trial_line_configuration_reports") AS src, (SELECT count(*) FROM raw.trial__trial_line_configuration_reports) AS raw_rows
UNION ALL
SELECT 'raw.trial__trials_header' AS tbl, (SELECT count(*) FROM fdw_trial."trials_header") AS src, (SELECT count(*) FROM raw.trial__trials_header) AS raw_rows
UNION ALL
SELECT 'raw.trial__trials_results' AS tbl, (SELECT count(*) FROM fdw_trial."trials_results") AS src, (SELECT count(*) FROM raw.trial__trials_results) AS raw_rows
UNION ALL
SELECT 'raw.trial__trials_review' AS tbl, (SELECT count(*) FROM fdw_trial."trials_review") AS src, (SELECT count(*) FROM raw.trial__trials_review) AS raw_rows
UNION ALL
SELECT 'raw.trial__trials_weighing' AS tbl, (SELECT count(*) FROM fdw_trial."trials_weighing") AS src, (SELECT count(*) FROM raw.trial__trials_weighing) AS raw_rows
UNION ALL
SELECT 'raw.trial__validation_parameters' AS tbl, (SELECT count(*) FROM fdw_trial."validation_parameters") AS src, (SELECT count(*) FROM raw.trial__validation_parameters) AS raw_rows
UNION ALL
SELECT 'raw.ipc__filling_check_revision_samples' AS tbl, (SELECT count(*) FROM fdw_ipc."filling_check_revision_samples") AS src, (SELECT count(*) FROM raw.ipc__filling_check_revision_samples) AS raw_rows
UNION ALL
SELECT 'raw.ipc__filling_check_revisions' AS tbl, (SELECT count(*) FROM fdw_ipc."filling_check_revisions") AS src, (SELECT count(*) FROM raw.ipc__filling_check_revisions) AS raw_rows
UNION ALL
SELECT 'raw.ipc__filling_check_samples' AS tbl, (SELECT count(*) FROM fdw_ipc."filling_check_samples") AS src, (SELECT count(*) FROM raw.ipc__filling_check_samples) AS raw_rows
UNION ALL
SELECT 'raw.ipc__filling_checks' AS tbl, (SELECT count(*) FROM fdw_ipc."filling_checks") AS src, (SELECT count(*) FROM raw.ipc__filling_checks) AS raw_rows
UNION ALL
SELECT 'raw.ipc__finished_check_revision_samples' AS tbl, (SELECT count(*) FROM fdw_ipc."finished_check_revision_samples") AS src, (SELECT count(*) FROM raw.ipc__finished_check_revision_samples) AS raw_rows
UNION ALL
SELECT 'raw.ipc__finished_check_revisions' AS tbl, (SELECT count(*) FROM fdw_ipc."finished_check_revisions") AS src, (SELECT count(*) FROM raw.ipc__finished_check_revisions) AS raw_rows
UNION ALL
SELECT 'raw.ipc__finished_check_samples' AS tbl, (SELECT count(*) FROM fdw_ipc."finished_check_samples") AS src, (SELECT count(*) FROM raw.ipc__finished_check_samples) AS raw_rows
UNION ALL
SELECT 'raw.ipc__finished_checks' AS tbl, (SELECT count(*) FROM fdw_ipc."finished_checks") AS src, (SELECT count(*) FROM raw.ipc__finished_checks) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_approval_revisions' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_approval_revisions") AS src, (SELECT count(*) FROM raw.ipc__ipc_approval_revisions) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_approvals' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_approvals") AS src, (SELECT count(*) FROM raw.ipc__ipc_approvals) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_attachments' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_attachments") AS src, (SELECT count(*) FROM raw.ipc__ipc_attachments) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_batch_bulk_codes' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_batch_bulk_codes") AS src, (SELECT count(*) FROM raw.ipc__ipc_batch_bulk_codes) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_batches' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_batches") AS src, (SELECT count(*) FROM raw.ipc__ipc_batches) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_logs' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_logs") AS src, (SELECT count(*) FROM raw.ipc__ipc_logs) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_print_logs' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_print_logs") AS src, (SELECT count(*) FROM raw.ipc__ipc_print_logs) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_report_documents' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_report_documents") AS src, (SELECT count(*) FROM raw.ipc__ipc_report_documents) AS raw_rows
UNION ALL
SELECT 'raw.ipc__master_lines' AS tbl, (SELECT count(*) FROM fdw_ipc."master_lines") AS src, (SELECT count(*) FROM raw.ipc__master_lines) AS raw_rows
UNION ALL
SELECT 'raw.ipc__master_product_bulk_codes' AS tbl, (SELECT count(*) FROM fdw_ipc."master_product_bulk_codes") AS src, (SELECT count(*) FROM raw.ipc__master_product_bulk_codes) AS raw_rows
UNION ALL
SELECT 'raw.ipc__master_products' AS tbl, (SELECT count(*) FROM fdw_ipc."master_products") AS src, (SELECT count(*) FROM raw.ipc__master_products) AS raw_rows
UNION ALL
SELECT 'raw.ipc__master_test_types' AS tbl, (SELECT count(*) FROM fdw_ipc."master_test_types") AS src, (SELECT count(*) FROM raw.ipc__master_test_types) AS raw_rows
UNION ALL
SELECT 'raw.ipc__micro_check_revisions' AS tbl, (SELECT count(*) FROM fdw_ipc."micro_check_revisions") AS src, (SELECT count(*) FROM raw.ipc__micro_check_revisions) AS raw_rows
UNION ALL
SELECT 'raw.ipc__micro_checks' AS tbl, (SELECT count(*) FROM fdw_ipc."micro_checks") AS src, (SELECT count(*) FROM raw.ipc__micro_checks) AS raw_rows
UNION ALL
SELECT 'raw.ipc__packing_check_revision_photos' AS tbl, (SELECT count(*) FROM fdw_ipc."packing_check_revision_photos") AS src, (SELECT count(*) FROM raw.ipc__packing_check_revision_photos) AS raw_rows
UNION ALL
SELECT 'raw.ipc__packing_check_revisions' AS tbl, (SELECT count(*) FROM fdw_ipc."packing_check_revisions") AS src, (SELECT count(*) FROM raw.ipc__packing_check_revisions) AS raw_rows
UNION ALL
SELECT 'raw.ipc__packing_checks' AS tbl, (SELECT count(*) FROM fdw_ipc."packing_checks") AS src, (SELECT count(*) FROM raw.ipc__packing_checks) AS raw_rows
UNION ALL
SELECT 'raw.ipc__roles' AS tbl, (SELECT count(*) FROM fdw_ipc."roles") AS src, (SELECT count(*) FROM raw.ipc__roles) AS raw_rows
UNION ALL
SELECT 'raw.ipc__startup_checks' AS tbl, (SELECT count(*) FROM fdw_ipc."startup_checks") AS src, (SELECT count(*) FROM raw.ipc__startup_checks) AS raw_rows
UNION ALL
SELECT 'raw.ipc__startup_inspection_items' AS tbl, (SELECT count(*) FROM fdw_ipc."startup_inspection_items") AS src, (SELECT count(*) FROM raw.ipc__startup_inspection_items) AS raw_rows
UNION ALL
SELECT 'raw.ipc__startup_inspection_samples' AS tbl, (SELECT count(*) FROM fdw_ipc."startup_inspection_samples") AS src, (SELECT count(*) FROM raw.ipc__startup_inspection_samples) AS raw_rows
UNION ALL
SELECT 'raw.ipc__startup_inspection_test_results' AS tbl, (SELECT count(*) FROM fdw_ipc."startup_inspection_test_results") AS src, (SELECT count(*) FROM raw.ipc__startup_inspection_test_results) AS raw_rows
UNION ALL
SELECT 'raw.ipc__startup_inspections' AS tbl, (SELECT count(*) FROM fdw_ipc."startup_inspections") AS src, (SELECT count(*) FROM raw.ipc__startup_inspections) AS raw_rows
) v;

-- 2) Only the mismatches (expect zero rows).
SELECT * FROM (
SELECT 'raw.trial__activity_logs' AS tbl, (SELECT count(*) FROM fdw_trial."activity_logs") AS src, (SELECT count(*) FROM raw.trial__activity_logs) AS raw_rows
UNION ALL
SELECT 'raw.trial__audit_logs' AS tbl, (SELECT count(*) FROM fdw_trial."audit_logs") AS src, (SELECT count(*) FROM raw.trial__audit_logs) AS raw_rows
UNION ALL
SELECT 'raw.trial__line_configuration_lanes' AS tbl, (SELECT count(*) FROM fdw_trial."line_configuration_lanes") AS src, (SELECT count(*) FROM raw.trial__line_configuration_lanes) AS raw_rows
UNION ALL
SELECT 'raw.trial__master_options' AS tbl, (SELECT count(*) FROM fdw_trial."master_options") AS src, (SELECT count(*) FROM raw.trial__master_options) AS raw_rows
UNION ALL
SELECT 'raw.trial__notification_user_status' AS tbl, (SELECT count(*) FROM fdw_trial."notification_user_status") AS src, (SELECT count(*) FROM raw.trial__notification_user_status) AS raw_rows
UNION ALL
SELECT 'raw.trial__notifications' AS tbl, (SELECT count(*) FROM fdw_trial."notifications") AS src, (SELECT count(*) FROM raw.trial__notifications) AS raw_rows
UNION ALL
SELECT 'raw.trial__products' AS tbl, (SELECT count(*) FROM fdw_trial."products") AS src, (SELECT count(*) FROM raw.trial__products) AS raw_rows
UNION ALL
SELECT 'raw.trial__trial_additional_attachments' AS tbl, (SELECT count(*) FROM fdw_trial."trial_additional_attachments") AS src, (SELECT count(*) FROM raw.trial__trial_additional_attachments) AS raw_rows
UNION ALL
SELECT 'raw.trial__trial_attachment_files' AS tbl, (SELECT count(*) FROM fdw_trial."trial_attachment_files") AS src, (SELECT count(*) FROM raw.trial__trial_attachment_files) AS raw_rows
UNION ALL
SELECT 'raw.trial__trial_edit_permissions' AS tbl, (SELECT count(*) FROM fdw_trial."trial_edit_permissions") AS src, (SELECT count(*) FROM raw.trial__trial_edit_permissions) AS raw_rows
UNION ALL
SELECT 'raw.trial__trial_line_configuration_reports' AS tbl, (SELECT count(*) FROM fdw_trial."trial_line_configuration_reports") AS src, (SELECT count(*) FROM raw.trial__trial_line_configuration_reports) AS raw_rows
UNION ALL
SELECT 'raw.trial__trials_header' AS tbl, (SELECT count(*) FROM fdw_trial."trials_header") AS src, (SELECT count(*) FROM raw.trial__trials_header) AS raw_rows
UNION ALL
SELECT 'raw.trial__trials_results' AS tbl, (SELECT count(*) FROM fdw_trial."trials_results") AS src, (SELECT count(*) FROM raw.trial__trials_results) AS raw_rows
UNION ALL
SELECT 'raw.trial__trials_review' AS tbl, (SELECT count(*) FROM fdw_trial."trials_review") AS src, (SELECT count(*) FROM raw.trial__trials_review) AS raw_rows
UNION ALL
SELECT 'raw.trial__trials_weighing' AS tbl, (SELECT count(*) FROM fdw_trial."trials_weighing") AS src, (SELECT count(*) FROM raw.trial__trials_weighing) AS raw_rows
UNION ALL
SELECT 'raw.trial__validation_parameters' AS tbl, (SELECT count(*) FROM fdw_trial."validation_parameters") AS src, (SELECT count(*) FROM raw.trial__validation_parameters) AS raw_rows
UNION ALL
SELECT 'raw.ipc__filling_check_revision_samples' AS tbl, (SELECT count(*) FROM fdw_ipc."filling_check_revision_samples") AS src, (SELECT count(*) FROM raw.ipc__filling_check_revision_samples) AS raw_rows
UNION ALL
SELECT 'raw.ipc__filling_check_revisions' AS tbl, (SELECT count(*) FROM fdw_ipc."filling_check_revisions") AS src, (SELECT count(*) FROM raw.ipc__filling_check_revisions) AS raw_rows
UNION ALL
SELECT 'raw.ipc__filling_check_samples' AS tbl, (SELECT count(*) FROM fdw_ipc."filling_check_samples") AS src, (SELECT count(*) FROM raw.ipc__filling_check_samples) AS raw_rows
UNION ALL
SELECT 'raw.ipc__filling_checks' AS tbl, (SELECT count(*) FROM fdw_ipc."filling_checks") AS src, (SELECT count(*) FROM raw.ipc__filling_checks) AS raw_rows
UNION ALL
SELECT 'raw.ipc__finished_check_revision_samples' AS tbl, (SELECT count(*) FROM fdw_ipc."finished_check_revision_samples") AS src, (SELECT count(*) FROM raw.ipc__finished_check_revision_samples) AS raw_rows
UNION ALL
SELECT 'raw.ipc__finished_check_revisions' AS tbl, (SELECT count(*) FROM fdw_ipc."finished_check_revisions") AS src, (SELECT count(*) FROM raw.ipc__finished_check_revisions) AS raw_rows
UNION ALL
SELECT 'raw.ipc__finished_check_samples' AS tbl, (SELECT count(*) FROM fdw_ipc."finished_check_samples") AS src, (SELECT count(*) FROM raw.ipc__finished_check_samples) AS raw_rows
UNION ALL
SELECT 'raw.ipc__finished_checks' AS tbl, (SELECT count(*) FROM fdw_ipc."finished_checks") AS src, (SELECT count(*) FROM raw.ipc__finished_checks) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_approval_revisions' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_approval_revisions") AS src, (SELECT count(*) FROM raw.ipc__ipc_approval_revisions) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_approvals' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_approvals") AS src, (SELECT count(*) FROM raw.ipc__ipc_approvals) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_attachments' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_attachments") AS src, (SELECT count(*) FROM raw.ipc__ipc_attachments) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_batch_bulk_codes' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_batch_bulk_codes") AS src, (SELECT count(*) FROM raw.ipc__ipc_batch_bulk_codes) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_batches' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_batches") AS src, (SELECT count(*) FROM raw.ipc__ipc_batches) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_logs' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_logs") AS src, (SELECT count(*) FROM raw.ipc__ipc_logs) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_print_logs' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_print_logs") AS src, (SELECT count(*) FROM raw.ipc__ipc_print_logs) AS raw_rows
UNION ALL
SELECT 'raw.ipc__ipc_report_documents' AS tbl, (SELECT count(*) FROM fdw_ipc."ipc_report_documents") AS src, (SELECT count(*) FROM raw.ipc__ipc_report_documents) AS raw_rows
UNION ALL
SELECT 'raw.ipc__master_lines' AS tbl, (SELECT count(*) FROM fdw_ipc."master_lines") AS src, (SELECT count(*) FROM raw.ipc__master_lines) AS raw_rows
UNION ALL
SELECT 'raw.ipc__master_product_bulk_codes' AS tbl, (SELECT count(*) FROM fdw_ipc."master_product_bulk_codes") AS src, (SELECT count(*) FROM raw.ipc__master_product_bulk_codes) AS raw_rows
UNION ALL
SELECT 'raw.ipc__master_products' AS tbl, (SELECT count(*) FROM fdw_ipc."master_products") AS src, (SELECT count(*) FROM raw.ipc__master_products) AS raw_rows
UNION ALL
SELECT 'raw.ipc__master_test_types' AS tbl, (SELECT count(*) FROM fdw_ipc."master_test_types") AS src, (SELECT count(*) FROM raw.ipc__master_test_types) AS raw_rows
UNION ALL
SELECT 'raw.ipc__micro_check_revisions' AS tbl, (SELECT count(*) FROM fdw_ipc."micro_check_revisions") AS src, (SELECT count(*) FROM raw.ipc__micro_check_revisions) AS raw_rows
UNION ALL
SELECT 'raw.ipc__micro_checks' AS tbl, (SELECT count(*) FROM fdw_ipc."micro_checks") AS src, (SELECT count(*) FROM raw.ipc__micro_checks) AS raw_rows
UNION ALL
SELECT 'raw.ipc__packing_check_revision_photos' AS tbl, (SELECT count(*) FROM fdw_ipc."packing_check_revision_photos") AS src, (SELECT count(*) FROM raw.ipc__packing_check_revision_photos) AS raw_rows
UNION ALL
SELECT 'raw.ipc__packing_check_revisions' AS tbl, (SELECT count(*) FROM fdw_ipc."packing_check_revisions") AS src, (SELECT count(*) FROM raw.ipc__packing_check_revisions) AS raw_rows
UNION ALL
SELECT 'raw.ipc__packing_checks' AS tbl, (SELECT count(*) FROM fdw_ipc."packing_checks") AS src, (SELECT count(*) FROM raw.ipc__packing_checks) AS raw_rows
UNION ALL
SELECT 'raw.ipc__roles' AS tbl, (SELECT count(*) FROM fdw_ipc."roles") AS src, (SELECT count(*) FROM raw.ipc__roles) AS raw_rows
UNION ALL
SELECT 'raw.ipc__startup_checks' AS tbl, (SELECT count(*) FROM fdw_ipc."startup_checks") AS src, (SELECT count(*) FROM raw.ipc__startup_checks) AS raw_rows
UNION ALL
SELECT 'raw.ipc__startup_inspection_items' AS tbl, (SELECT count(*) FROM fdw_ipc."startup_inspection_items") AS src, (SELECT count(*) FROM raw.ipc__startup_inspection_items) AS raw_rows
UNION ALL
SELECT 'raw.ipc__startup_inspection_samples' AS tbl, (SELECT count(*) FROM fdw_ipc."startup_inspection_samples") AS src, (SELECT count(*) FROM raw.ipc__startup_inspection_samples) AS raw_rows
UNION ALL
SELECT 'raw.ipc__startup_inspection_test_results' AS tbl, (SELECT count(*) FROM fdw_ipc."startup_inspection_test_results") AS src, (SELECT count(*) FROM raw.ipc__startup_inspection_test_results) AS raw_rows
UNION ALL
SELECT 'raw.ipc__startup_inspections' AS tbl, (SELECT count(*) FROM fdw_ipc."startup_inspections") AS src, (SELECT count(*) FROM raw.ipc__startup_inspections) AS raw_rows
) v
WHERE src <> raw_rows
ORDER BY tbl;
