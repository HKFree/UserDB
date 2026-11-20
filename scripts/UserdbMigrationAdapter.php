<?php

final class UserdbMigrationAdapter extends Nextras\Migrations\Bridges\NetteDatabase\NetteAdapter
{
    public function exec(string $sql): int {
        // Historic production definers need privileges the local accounts must not have.
        return parent::exec(str_replace('DEFINER=`userdb_v2`@`%`', 'DEFINER=CURRENT_USER', $sql));
    }
}
