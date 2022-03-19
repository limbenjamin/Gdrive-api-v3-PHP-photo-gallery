# Google Drive API v3 PHP Photo Gallery

PHP Photo gallery with photo storage backed by Google Drive. 

An improved version of dansoutner's gdrive-nanogallery which uses a legacy Google API which does not support storage of photos on Google Shared Drive.

This version uses Google OAuth 2.0 authentication which is more secure and supports Google Shared Drive backed storage.

#### Installation 

1. Setting up Google OAuth 2.0 and sample PHP code to verify successful setup  
	https://developers.google.com/identity/protocols/oauth2/web-server
2. Installing Google API client library
`php composer.phar require google/apiclient:^2.0`
3. Modify $defaultFolderId & $defaultPath to location of photos
4. Change credentials.json and token.json to secure path

#### References
1. https://github.com/dansoutner/gdrive-nanogallery
2. https://nanogallery.brisbois.fr/


#### License
The software is distributed "as is". No warranty of any kind is expressed or implied. You use at your own risk. The author will not be liable for data loss, damages, loss of profits or any other kind of loss while using or misusing this software.

The Licensee is allowed to freely redistribute the software subject to the following conditions.  
1.	The Software may be installed and used by the Licensee for any legal purpose.
2.	The Licensee will not charge money or fees for the software product, except to cover distribution costs.  
3.  The Licensor retains all copyrights and other proprietary rights in and to the Software.  
4.	Use within the scope of this License is free of charge and no royalty or licensing fees shall be paid by the Licensee. 