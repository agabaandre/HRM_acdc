-- Run on the Staff / CBP database before using "Director" on Settings → Directorates.
-- Links directorates.director_id to staff.staff_id (same convention as divisions.director_id).

ALTER TABLE `directorates`
  ADD COLUMN `director_id` INT NULL DEFAULT NULL COMMENT 'Director staff_id (staff.staff_id)' AFTER `is_active`,
  ADD KEY `directorates_director_id_index` (`director_id`);
