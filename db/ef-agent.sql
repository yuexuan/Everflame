CREATE TABLE IF NOT EXISTS ef_agents (
 id varchar(64) PRIMARY KEY, token_hash char(64) NOT NULL, observer_hash char(64) NOT NULL,
 enabled tinyint NOT NULL DEFAULT 1, billing_start bigint NOT NULL DEFAULT 0,
 last_seen bigint NOT NULL DEFAULT 0, core_status varchar(32) NOT NULL DEFAULT 'unknown',
 applied_revision char(64) NOT NULL DEFAULT '', error text NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS ef_bindings (
 id bigint unsigned PRIMARY KEY AUTO_INCREMENT, agent_id varchar(64) NOT NULL,
 inbound_id int NOT NULL, node_id bigint unsigned NOT NULL UNIQUE,
 probe_password varchar(255) NOT NULL, tariff_id bigint unsigned NOT NULL DEFAULT 0,
 probe_at bigint NOT NULL DEFAULT 0, probe_ok tinyint NOT NULL DEFAULT 0,
 successes int NOT NULL DEFAULT 0, failures int NOT NULL DEFAULT 0,
 probe_reason varchar(255) NOT NULL DEFAULT '等待外部探测',
 KEY agent_inbound (agent_id,inbound_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS ef_tariffs (
 id bigint unsigned PRIMARY KEY AUTO_INCREMENT, binding_id bigint unsigned NOT NULL,
 rate_ppm bigint unsigned NOT NULL, effective_at bigint NOT NULL,
 KEY binding_time (binding_id,effective_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS ef_credentials (
 binding_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL,
 password varchar(255) NOT NULL, PRIMARY KEY (binding_id,user_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS ef_usage (
 agent_id varchar(64) NOT NULL, event_id varchar(96) NOT NULL, digest char(64) NOT NULL,
 binding_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL,
 epoch varchar(64) NOT NULL, seq bigint unsigned NOT NULL, started_at bigint NOT NULL, ended_at bigint NOT NULL,
 raw_u bigint unsigned NOT NULL, raw_d bigint unsigned NOT NULL,
 billed_u bigint unsigned NOT NULL, billed_d bigint unsigned NOT NULL,
 tariff_id bigint unsigned NOT NULL, period varchar(32) NOT NULL, charged tinyint NOT NULL,
 received_at bigint NOT NULL, PRIMARY KEY (agent_id,event_id),
 KEY user_period (user_id,period), KEY user_time (user_id,charged,ended_at), KEY binding_time (binding_id,ended_at)
) ENGINE=InnoDB;
