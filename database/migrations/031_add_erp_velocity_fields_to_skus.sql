-- Add fields needed for ERP velocity sync and QoQ auto-promotion
ALTER TABLE skus
  ADD COLUMN erp_velocity_90d INT NULL AFTER erp_cppc,
  ADD COLUMN previous_velocity_90d INT NULL AFTER erp_velocity_90d;

