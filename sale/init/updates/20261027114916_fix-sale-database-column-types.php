<?php

use equal\db\DBConnector;

$dbms = constant('DB_DBMS');
if(!in_array($dbms, ['MYSQL', 'MARIADB'], true)) {
    trigger_error("DBM::The Discope sale schema update does not support DBMS '$dbms'.", EQ_REPORT_ERROR);
    throw new Exception('unsupported_dbms', EQ_ERROR_INVALID_CONFIG);
}

$db = DBConnector::getInstance(
        constant('DB_HOST'),
        constant('DB_PORT'),
        constant('DB_NAME'),
        constant('DB_USER'),
        constant('DB_PASSWORD'),
        $dbms
    )
    ->connect();

if(!$db) {
    throw new Exception('missing_database', EQ_ERROR_INVALID_CONFIG);
}

$tables = array_fill_keys($db->getTables(), true);

$get_table_schema = static function(string $table) use($db, $tables): array {
    if(!isset($tables[$table])) {
        trigger_error("DBM::Missing expected table '$table' while updating the Discope sale schema.", EQ_REPORT_ERROR);
        throw new Exception('unexpected_database_schema', EQ_ERROR_INVALID_CONFIG);
    }

    return $db->getTableSchema($table);
};

$change_type_if_incompatible = static function(
    string $table,
    string $column,
    array $compatible_types,
    string $target_type
) use($db, $get_table_schema): void {
    $schema = $get_table_schema($table);
    if(!isset($schema[$column])) {
        trigger_error("DBM::Missing expected column '$table.$column' while updating the Discope sale schema.", EQ_REPORT_ERROR);
        throw new Exception('unexpected_database_schema', EQ_ERROR_INVALID_CONFIG);
    }

    $current_type = strtolower($schema[$column]['type']);
    if(in_array($current_type, $compatible_types, true)) {
        return;
    }

    $db->sendQuery("ALTER TABLE `$table` MODIFY COLUMN `$column` $target_type NULL;");
};

$change_type_if_incompatible(
    'sale_camp_camp',
    'sojourn_number',
    ['char', 'varchar', 'nvarchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'blob', 'mediumblob'],
    'VARCHAR(255)'
);

// PosProductModel and Supply share ProductModel's physical table.
$change_type_if_incompatible(
    'sale_catalog_productmodel',
    'nutritional_coefficient',
    ['float', 'decimal', 'real'],
    'DECIMAL(10,2)'
);

$relation_table = 'sale_product_rel_productmodel_category';
$relation_schema = $get_table_schema($relation_table);
if(!isset($relation_schema['id'])) {
    $primary_index_result = $db->sendQuery("SHOW INDEX FROM `$relation_table` WHERE Key_name = 'PRIMARY';");
    $id_index = $db->fetchArray($primary_index_result) ? 'UNIQUE' : 'PRIMARY KEY';

    $db->sendQuery(
        "ALTER TABLE `$relation_table` "
        ."ADD COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT $id_index FIRST;"
    );
}
