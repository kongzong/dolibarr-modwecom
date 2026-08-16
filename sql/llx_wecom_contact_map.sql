-- Copyright (C) 2026 modWeCom contributors
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- Table for module WeCom: mapping between WeCom external contacts and Dolibarr thirdparties/contacts.

CREATE TABLE llx_wecom_contact_map(
	rowid			integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity			integer DEFAULT 1 NOT NULL,
	external_userid	varchar(64) NOT NULL,
	fk_soc			integer,
	fk_contact		integer,
	wecom_type		smallint,
	wecom_state		varchar(32),
	wecom_name		varchar(255),
	wecom_avatar		varchar(512),
	wecom_corp_name	varchar(255),
	wecom_tags		varchar(512),
	owner_wecom_userid	varchar(64),
	status			smallint DEFAULT 1,
	date_creation	datetime,
	tms				timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;

ALTER TABLE llx_wecom_contact_map ADD UNIQUE INDEX uk_wecom_contact_map_external_userid (external_userid);
