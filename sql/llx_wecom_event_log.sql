-- Copyright (C) 2026 modWeCom contributors
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- Table for module WeCom: webhook event log with idempotency key.

CREATE TABLE llx_wecom_event_log(
	rowid			integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity			integer DEFAULT 1 NOT NULL,
	event_id		varchar(128) NOT NULL,
	event_type		varchar(64),
	event_time		datetime,
	payload_hash	varchar(64),
	payload			text,
	process_status	smallint DEFAULT 0,
	process_message	varchar(255),
	retry_count		smallint DEFAULT 0,
	date_creation	datetime,
	tms				timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;

ALTER TABLE llx_wecom_event_log ADD UNIQUE INDEX uk_wecom_event_log_event_id (event_id);
