# flysystem-msgraph
A Flysystem Adapter that supports Microsoft OneDrive and Sharepoint Document Libraries using Microsoft Graph

## Setting Up A Background App For Microsoft Graph
This flysystem adapter operates as a Background Service. See https://docs.microsoft.com/en-us/graph/auth-v2-service for more detailed information
on how to configure an application.

When setting up permissions, you must add Application Permissions to specify File.ReadWrite.All.

Once your app is completed, you'll need your Administrator to consent to the application using the Azure Portal at https://portal.azure.com/.

## Using with OneDrive
* Currently OneDrive is not supported. Development is continuing to support OneDrive.

## Using with Sharepoint Document Libraries
Adapter Constructor Definition:
```php
$adapter = new MSGraphAppSharepoint();

// Generate a token.
$adapter->authorize($tenantId, $appId, $appPassword);

// Setup the site and drive ids.
$adapter->initialize($targetId, $driveName);

$this->fs = new Filesystem($adapter);

$this->fs->write('hello.txt', 'Hello World!');
```
* $appId : The Application ID which can be found in the Application Registration
* $appPassword : The generated password created in the Application Registration
* $tenantId : The GUID of the tenant.
* $tokenEndpoint : The OAuth2 Access Token Endpoint for your Azure/Office365 Tenant. (Ex: https://login.microsoftonline.com/example.onmicrosoft.com/oauth2/v2.0/token)
* $mode : Either 'sharepoint' or 'onedrive' which will specify the target mode. (Note, only Sharepoint is supported currently)
* $targetId : The target ID for specifying where the files should reside.
  * For Sharepoint, the sharepoint url (Ex: example.sharepoint.com) can be used as well as a URL to the site (Ex: example.sharepoint.com:/sites/EXAMPLE)
* $driveName : The name of the document library or drive for the specified sharepoint site.

## Running Tests
The tests are functional in which a valid Microsoft Office 365/Azure environment must be available. Once you have your
application id and password and the application has consent from the administrator, you must provide the credentials
through environment variables and then run the phpunit test suites.  The environment variables requires are:

* APP_ID : The application ID you registered
* APP_PASSWORD : The application Password you generated
* TENANT_ID : The GUID of the tenant.
* TEST_SHAREPOINT_SITE_ID : The Sharepoint Site ID to utilize. This should ideally be the FQDN of the sharepoint site (Ex: example.sharepoint.com)
* OAUTH_AUTHORITY (optional) : The OAuth2 Authority URL to use. Defaults to https://login.microsoftonline.com/common
* OAUTH_AUTHORIZE_ENDPOINT (optional) : The OAuth2 Authorize Endpoint to use. Defaults to /oauth2/authorize?api-version=1.0
* OAUTH_TOKEN_ENDPOINT (optional) : The OAuth2 Token Endpoint to use. Defaults to /oauth2/token?api-version=1.0
* TEST_FILE_PREFIX (optional) : The prefix to add to all file paths for this test suite. Default to no prefix

### Testing large file uploads.
To test the large file upload, generate a large file here https://fastest.fish/generate-file and save it to `tests/files.50MB.bin`.
