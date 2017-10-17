
CREATE TABLE `item` (
	`group_name` TEXT,
	`item` TEXT,
	`date` TEXT,
	PRIMARY KEY(`group_name`, `item`)
);


CREATE TABLE `log` (
	`id` BLOB NOT NULL,
	`subject` TEXT NOT NULL,
	`text` TEXT,
	`message_id` BLOB,
	`date` INTEGER NOT NULL,
	PRIMARY KEY(`id`)
);


CREATE TABLE `message` (
	`id` BLOB NOT NULL,
	`queue` TEXT NOT NULL,
	`data` TEXT NOT NULL,
	`date` TEXT NOT NULL,
	`created` TEXT NOT NULL,
	`status` INTEGER NOT NULL,
	`processed` TEXT,
	PRIMARY KEY(`id`)
);


CREATE TABLE `producer` (
	`producer` TEXT,
	`lastrun` TEXT NOT NULL,
	PRIMARY KEY(`producer`)
)
