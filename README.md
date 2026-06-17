# What is it?

CLI toolbox for WP devs

## Help

`wp help onionbox`

### Redirection Audit

Audit http redirects from the Redirection plugin to check for 404's, loops etc

```
  wp onionbox redirection-audit

  [--export-only]
    Just export the redirects from Redirection. Don't run the audit. All flags other than --module are irrelevant

  [--module=<wordpress|apache|nginx|all>]
    Which module to test. Defaults to 'all'

  [--max-redirects=<count>]
    How many redirects to follow before giving up. Defaults to 5.

  [--max-age=<days>]
    How many days since a redirect was hit is considered "old". Defaults to 365

  [--verbose]
    Show passes as well as failures, and extra info in general.

  [--ids=<id>...]
    Array of redirect IDs to test. Useful for retesting a subset from an earlier full audit
  
  [--id-from=<id>]
    Start at <id> and continue
 
  [--id-to=<id>]
   End at <id>

  [--match-url=<url>]
    Check a single match-url. Copy and paste this into quotes from the Redirection page in wp-admin
```

### ld+json audit & validator

Checks URLs for valid ld+json Structured data

This currently uses the latest schema RDF from schema.org, saved to src/Validators/LdJson:
https://github.com/schemaorg/schemaorg/blob/main/data/releases/30.0/schemaorg-current-https.ttl

```
  wp onionbox ldjson 

  [--target-post-types=<post-type>...]
    Test all of a post type. Pass in a csv to do multiple

  [--target-path=<path>]
    Test a specific path

  [--target-ids=<ids>...]
    Test a specific post ID. Pass in a csv for multiple.

  [--follow-links]
    Follow things like image links to see if they are resolving correctly

  [--verbose]
    Show passes as well as failures, and extra info in general.

  [--vverbose]
    Dump out the ld+json etc. Only use this if you are testing individual posts, or you really like large log files
```