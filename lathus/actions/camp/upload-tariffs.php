<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;

[$params, $provider] = eQual::announce([
    'description'   => "Uploads the tariffs csv files to Lathus website for synchronisation.",
    'params'        => [],
    'access'        => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $provider;

$tariffs_csv = eQual::run('get', 'lathus_camp_export-tariffs-csv');

$ftp_server = Setting::get_value('sale', 'integration', 'camp.sync_website.ftp_server');
if(is_null($ftp_server)) {
    throw new Exception("ftp_server_not_defined", EQ_ERROR_INVALID_CONFIG);
}

$ftp_user = Setting::get_value('sale', 'integration', 'camp.sync_website.ftp_user');
$ftp_pass = Setting::get_value('sale', 'integration', 'camp.sync_website.ftp_password');
if(is_null($ftp_user) || is_null($ftp_pass)) {
    throw new Exception("ftp_credentials_not_defined", EQ_ERROR_INVALID_CONFIG);
}

$remote_file = Setting::get_value('sale', 'integration', 'camp.sync_website.ftp_tariffs_file_path');
if(is_null($remote_file)) {
    throw new Exception("ftp_remote_file_path_not_defined", EQ_ERROR_INVALID_CONFIG);
}

if(!($conn_id = ftp_connect($ftp_server))) {
    throw new Exception("ftp_connection_failed", EQ_ERROR_UNKNOWN);
}

if(@ftp_login($conn_id, $ftp_user, $ftp_pass)) {
    trigger_error("FTP::authentication successful to $ftp_server", EQ_REPORT_INFO);
} else {
    throw new Exception("ftp_authentication_failed", EQ_ERROR_UNKNOWN);
}

if(ftp_put($conn_id, $remote_file, $tariffs_csv)) {
    trigger_error("FTP::upload successful of $remote_file to $ftp_server", EQ_REPORT_INFO);
} else {
    throw new Exception("ftp_upload_failed", EQ_ERROR_UNKNOWN);
}

ftp_close($conn_id);

$context->httpResponse()
        ->status(204)
        ->send();