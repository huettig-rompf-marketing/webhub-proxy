# Hüttig & Rompf - WebHub Proxy

This script can be installed on your webserver to proxy all requests to the Huettig and Rompf Webhub trough your own server.
What that means is, that there is absolutely no privacy related data (like your users ip address) sent to Huettig and Rompf servers.
Therefore, you are safe to use the snippets/calculators without additional "opt-in" feature for GDPR.

## Requirements

- PHP >=7.2
- PHP gzip
- Apache Webserver with mod-rewrite enabled

## Installation
Install this package using Composer:

```
composer require huettig-und-rompf/webhub-proxy
```

After you installed the bundle:

1. Copy the "webhub-proxy" directory somewhere into your website's public docroot.
2. Open the "index.php" in the copied "webhub-proxy" directory in an editor.
3. Adjust the $VENDOR_DIR path (line 25) to match the requirements in your setup.
4. Deploy the script to your production machine

## Usage

1. Go to [Online Marketing](https://www.huettig-rompf.de/immobilienvertriebe/online-marketing/)
2. Select one of the available snippets
3. Copy the embed-code into your templates
4. Adjust the first script tag to match your local proxy setup
  e.g ```<script type="text/javascript" src="https://webhub.huettig-rompf.de/js/snippet"></script>```
  becomes: ```<script type="text/javascript" src="https://example.org/webhub-proxy/js/snippet"></script>```

