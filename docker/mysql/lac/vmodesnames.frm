TYPE=VIEW
query=select `a`.`id_mode` AS `id_mode`,`a`.`system` AS `system`,`a`.`name` AS `name`,`a`.`description` AS `description`,`a`.`type` AS `type`,`b`.`sysName` AS `sysName` from (`lac`.`game_modes` `a` left join `lac`.`game_modes-names` `b` on(`a`.`id_mode` = `b`.`id_mode`))
md5=b89499c33d447c793ff3cbb314e5c1c7
updatable=0
algorithm=0
definer_user=root
definer_host=%
suid=2
with_check_option=0
timestamp=2022-09-13 14:05:13
create-version=2
source=SELECT\n   `a`.`id_mode` AS `id_mode`,\n   `a`.`system` AS `system`,\n   `a`.`name` AS `name`,\n   `a`.`description` AS `description`,\n   `a`.`type` AS `type`,\n   `b`.`sysName` AS `sysName`\nFROM (`game_modes` `a` left join `game_modes-names` `b` on(`a`.`id_mode` = `b`.`id_mode`))
client_cs_name=utf8mb4
connection_cl_name=utf8mb4_general_ci
view_body_utf8=select `a`.`id_mode` AS `id_mode`,`a`.`system` AS `system`,`a`.`name` AS `name`,`a`.`description` AS `description`,`a`.`type` AS `type`,`b`.`sysName` AS `sysName` from (`lac`.`game_modes` `a` left join `lac`.`game_modes-names` `b` on(`a`.`id_mode` = `b`.`id_mode`))
mariadb-version=100609
