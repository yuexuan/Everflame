CREATE TABLE IF NOT EXISTS node_hourly_usage (
 user_id bigint unsigned NOT NULL,
 node_id bigint unsigned NOT NULL,
 hour_at bigint NOT NULL,
 bytes bigint unsigned NOT NULL DEFAULT 0,
 PRIMARY KEY (user_id, hour_at, node_id)
) ENGINE=InnoDB;
