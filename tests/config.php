<?php
namespace BitsnBolts\Flysystem\Adapter\MSGraph\Test;

use Exception;

/**
 * To run the tests, you must supply your Microsoft Azure Application
 * ID and Password. This must be done via environment variables before
 * loading the tests.
 *
 *
*/
if (!getenv("test_app_id") || !getenv("test_app_password")) {
    throw new Exception("No application ID or password specified in environment.");
}

define("APP_ID", getenv("test_app_id"));
define("APP_PASSWORD", getenv("test_app_password"));
define("TENANT_ID", getenv("test_tenant_id"));
define("OAUTH_AUTHORITY", getenv("test_oauth_authority") ? getenv("test_oauth_authority") : "https://login.microsoftonline.com/common");
define("OAUTH_AUTHORIZE_ENDPOINT", getenv("test_oauth_authorize_endpoint") ? getenv("test_oauth_authorize_endpoint") : "/oauth2/authorize?api-version=1.0");
define("OAUTH_TOKEN_ENDPOINT", getenv("test_oauth_token_endpoint") ? getenv("test_oauth_token_endpoint") : "/oauth2/token?api-version=1.0");
define("SHAREPOINT_SITE_ID", getenv("test_sharepoint_site_id") ? getenv("test_sharepoint_site_id") : "example.com");
define("SHAREPOINT_DRIVE_NAME", getenv("test_sharepoint_drive_name") ? getenv("test_sharepoint_drive_name") : "testDrive");
define("SHAREPOINT_INVITE_USER", getenv("test_sharepoint_invite_user") ? getenv("test_sharepoint_invite_user") : "user@example.com");
define("TEST_FILE_PREFIX", getenv("test_file_prefix") ? getenv("test_file_prefix") : "");
