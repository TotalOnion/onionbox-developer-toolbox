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

This currently uses the latest schema RDF from schema.org:
https://github.com/schemaorg/schemaorg/blob/main/data/releases/30.0/schemaorg-current-https.ttl