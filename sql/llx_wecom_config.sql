-- Copyright (C) 2026 modWeCom contributors
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- Table for module WeCom: connection configuration and cached access token.

CREATE TABLE llx_wecom_config(
	rowid			integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity			integer DEFAULT 1 NOT NULL,
	corp_id			varchar(64) DEFAULT '' NOT NULL,
	agent_id		integer,
	access_token	varchar(512),
	token_expires_at	datetime,
	token_updated_at	datetime,
	last_sync_users	datetime,
	last_sync_contacts	datetime,
	status			smallint DEFAULT 1,
	date_creation	datetime,
	tms				timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
