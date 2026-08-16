-- Copyright (C) 2026 modWeCom contributors
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- Table for module WeCom: mapping between WeCom users and Dolibarr users.

CREATE TABLE llx_wecom_user_map(
	rowid			integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity			integer DEFAULT 1 NOT NULL,
	fk_user			integer NOT NULL,
	wecom_userid	varchar(64) NOT NULL,
	wecom_unionid	varchar(64),
	wecom_openid	varchar(64),
	wecom_department_ids	varchar(255),
	status			smallint DEFAULT 1,
	date_creation	datetime,
	tms				timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;

ALTER TABLE llx_wecom_user_map ADD UNIQUE INDEX uk_wecom_user_map_userid (wecom_userid);
ALTER TABLE llx_wecom_user_map ADD UNIQUE INDEX uk_wecom_user_map_fk_user (fk_user);
