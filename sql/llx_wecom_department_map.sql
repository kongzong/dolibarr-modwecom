-- Copyright (C) 2026 modWeCom contributors
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- Table for module WeCom: mapping between WeCom departments and Dolibarr user groups (spec §11).

CREATE TABLE llx_wecom_department_map(
	rowid			integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity			integer DEFAULT 1 NOT NULL,
	wecom_department_id	integer NOT NULL,
	fk_usergroup	integer,
	wecom_name		varchar(255),
	wecom_parent_id	integer,
	wecom_order		integer,
	status			smallint DEFAULT 1,
	date_creation	datetime,
	tms				timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;

ALTER TABLE llx_wecom_department_map ADD UNIQUE INDEX uk_wecom_department_map_id (wecom_department_id);
