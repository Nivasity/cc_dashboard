--
-- Sample test data for manual_export_audits
-- This demonstrates the grant management workflow
--

-- Sample export records (assuming manual_id=19 and hoc_user_id=1 exist in your database)
-- Replace with actual IDs from your database

-- Pending export (to be granted)
INSERT INTO `manual_export_audits` (`code`, `manual_id`, `hoc_user_id`, `students_count`, `total_amount`, `downloaded_at`, `grant_status`) VALUES
('GXCZEFPJVY', 19, 1, 1, 600, '2025-11-16 13:37:27', 'pending');

-- Another pending export
INSERT INTO `manual_export_audits` (`code`, `manual_id`, `hoc_user_id`, `students_count`, `total_amount`, `downloaded_at`, `grant_status`) VALUES
('ABCDEFGHIJ', 19, 1, 25, 15000, '2025-11-17 10:15:00', 'pending');

-- Already granted export (for demonstration)
INSERT INTO `manual_export_audits` (`code`, `manual_id`, `hoc_user_id`, `students_count`, `total_amount`, `downloaded_at`, `grant_status`, `granted_by`, `granted_at`) VALUES
('KLMNOPQRST', 19, 1, 50, 30000, '2025-11-15 09:00:00', 'granted', 1, '2025-11-15 14:30:00');

-- Note: Make sure to adjust manual_id, hoc_user_id, and granted_by to match actual IDs in your database
